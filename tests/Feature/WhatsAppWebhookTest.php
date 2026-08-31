<?php

namespace Tests\Feature;

use App\Enums\MessageDirection;
use App\Jobs\ProcessIncomingWhatsAppWebhookJob;
use App\Models\Customer;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppWebhookService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    public function test_webhook_verify_token(): void
    {
        $account = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'webhook_verify_token' => 'secret-token',
        ]);

        $this->get('/api/v1/webhooks/whatsapp/'.$account->id.'?hub.mode=subscribe&hub.verify_token=secret-token&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_webhook_rejects_invalid_verify_token(): void
    {
        $account = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'webhook_verify_token' => 'secret-token',
        ]);

        $this->getJson('/api/v1/webhooks/whatsapp/'.$account->id.'?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')
            ->assertForbidden();
    }

    public function test_inbound_message_creates_customer_and_conversation(): void
    {
        $account = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'phone_number_id' => '111222333',
            'app_secret' => null,
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '111222333'],
                        'contacts' => [[
                            'profile' => ['name' => 'Layla'],
                            'wa_id' => '966555000111',
                        ]],
                        'messages' => [[
                            'from' => '966555000111',
                            'id' => 'wamid.abc123',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'Hello from WhatsApp'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/v1/webhooks/whatsapp/'.$account->id, $payload)
            ->assertOk();

        (new ProcessIncomingWhatsAppWebhookJob($payload))->handle(app(WhatsAppWebhookService::class));

        $this->assertDatabaseHas('customers', [
            'company_id' => $this->company->id,
            'whatsapp_number' => '966555000111',
            'name' => 'Layla',
        ]);

        $this->assertDatabaseHas('messages', [
            'whatsapp_message_id' => 'wamid.abc123',
            'body' => 'Hello from WhatsApp',
            'direction' => MessageDirection::Inbound->value,
        ]);

        $this->assertTrue(Message::withoutGlobalScopes()->where('whatsapp_message_id', 'wamid.abc123')->exists());
        $this->assertTrue(Customer::withoutGlobalScopes()->where('whatsapp_number', '966555000111')->exists());
    }

    public function test_webhook_is_queued(): void
    {
        Queue::fake();

        $account = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'app_secret' => null,
        ]);

        $this->postJson('/api/v1/webhooks/whatsapp/'.$account->id, [
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ])->assertOk();

        Queue::assertPushed(ProcessIncomingWhatsAppWebhookJob::class);
    }
}
