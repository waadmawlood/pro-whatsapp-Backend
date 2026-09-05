<?php

namespace Tests\Feature;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\WhatsAppAccountStatus;
use App\Enums\WhatsAppConnectionType;
use App\Jobs\ProcessBridgeStatusWebhookJob;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppBridgeWebhookService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppBridgeWebhookTest extends TestCase
{
    public function test_bridge_webhook_rejects_invalid_secret(): void
    {
        $account = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Web,
        ]);

        $this->postJson('/api/v1/webhooks/whatsapp-bridge/'.$account->id.'/connection', [
            'status' => 'qr',
            'qr' => 'data:image/png;base64,abc',
        ])->assertForbidden();
    }

    public function test_connection_webhook_updates_account(): void
    {
        config(['whatsapp.bridge.secret' => 'bridge-secret']);

        $account = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Web,
            'status' => WhatsAppAccountStatus::Pending,
        ]);

        $this->withHeaders(['X-Bridge-Secret' => 'bridge-secret'])
            ->postJson('/api/v1/webhooks/whatsapp-bridge/'.$account->id.'/connection', [
                'status' => 'connected',
                'phone_number' => '966555000222',
            ])
            ->assertOk();

        $account->refresh();

        $this->assertSame(WhatsAppAccountStatus::Connected, $account->status);
        $this->assertSame('966555000222', $account->phone_number);
        $this->assertNotNull($account->bridge_connected_at);
    }

    public function test_inbound_bridge_message_creates_customer_and_conversation(): void
    {
        config(['whatsapp.bridge.secret' => 'bridge-secret']);

        $account = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Web,
        ]);

        $this->withHeaders(['X-Bridge-Secret' => 'bridge-secret'])
            ->postJson('/api/v1/webhooks/whatsapp-bridge/'.$account->id.'/message', [
                'whatsapp_message_id' => 'bridge-msg-001',
                'remote_jid' => '966555000333@s.whatsapp.net',
                'from' => '966555000333',
                'push_name' => 'Sara',
                'type' => 'text',
                'body' => 'Hello from bridge',
                'timestamp' => now()->timestamp,
            ])
            ->assertOk();

        $this->assertDatabaseHas('customers', [
            'company_id' => $this->company->id,
            'whatsapp_number' => '966555000333',
            'whatsapp_jid' => '966555000333@s.whatsapp.net',
            'chat_type' => 'direct',
            'name' => 'Sara',
        ]);

        $this->assertDatabaseHas('messages', [
            'whatsapp_message_id' => 'bridge-msg-001',
            'body' => 'Hello from bridge',
            'direction' => MessageDirection::Inbound->value,
        ]);

        $this->assertTrue(Customer::withoutGlobalScopes()->where('whatsapp_number', '966555000333')->exists());
        $this->assertTrue(Message::withoutGlobalScopes()->where('whatsapp_message_id', 'bridge-msg-001')->exists());
    }

    public function test_inbound_group_message_creates_group_customer_and_stores_sender(): void
    {
        config(['whatsapp.bridge.secret' => 'bridge-secret']);

        $account = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Web,
        ]);

        $this->withHeaders(['X-Bridge-Secret' => 'bridge-secret'])
            ->postJson('/api/v1/webhooks/whatsapp-bridge/'.$account->id.'/message', [
                'whatsapp_message_id' => 'bridge-group-001',
                'chat_type' => 'group',
                'remote_jid' => '120363402028185588@g.us',
                'from' => '120363402028185588',
                'group_subject' => 'Super Speed Team',
                'participant_jid' => '9647713960790@s.whatsapp.net',
                'participant_name' => 'Ahmed',
                'type' => 'text',
                'body' => 'مرحبا من الكروب',
                'timestamp' => now()->timestamp,
            ])
            ->assertOk();

        $this->assertDatabaseHas('customers', [
            'company_id' => $this->company->id,
            'whatsapp_number' => '120363402028185588',
            'whatsapp_jid' => '120363402028185588@g.us',
            'chat_type' => 'group',
            'name' => 'Super Speed Team',
        ]);

        $message = Message::withoutGlobalScopes()->where('whatsapp_message_id', 'bridge-group-001')->first();

        $this->assertNotNull($message);
        $this->assertSame('Ahmed', $message->metadata['sender_name']);
        $this->assertSame('group', $message->metadata['chat_type']);
    }

    public function test_inbound_group_message_accepts_group_id_when_remote_jid_is_missing(): void
    {
        config(['whatsapp.bridge.secret' => 'bridge-secret']);

        $account = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Web,
        ]);

        $this->withHeaders(['X-Bridge-Secret' => 'bridge-secret'])
            ->postJson('/api/v1/webhooks/whatsapp-bridge/'.$account->id.'/message', [
                'whatsapp_message_id' => 'bridge-group-002',
                'chat_type' => 'group',
                'group_id' => '120363402028185588',
                'from' => '9647713960790',
                'group_subject' => 'Super Speed Team',
                'type' => 'text',
                'body' => 'رسالة المجموعة',
            ])
            ->assertOk();

        $this->assertDatabaseHas('customers', [
            'whatsapp_number' => '120363402028185588',
            'whatsapp_jid' => '120363402028185588@g.us',
            'chat_type' => 'group',
        ]);
    }

    public function test_admin_can_create_web_account_and_connect_bridge(): void
    {
        config([
            'whatsapp.bridge.secret' => 'bridge-secret',
            'whatsapp.bridge.url' => 'http://bridge.test',
        ]);

        Http::fake([
            'bridge.test/sessions/*/start' => Http::response([
                'success' => true,
                'status' => 'qr',
                'qr' => 'data:image/png;base64,abc',
            ], 200),
        ]);

        $admin = $this->makeAdmin();
        $this->actingAsUser($admin);

        $response = $this->postJson('/api/v1/whatsapp-accounts', [
            'name' => 'Main WhatsApp',
            'connection_type' => 'web',
            'is_default' => true,
        ])
            ->assertCreated();

        $accountId = $response->json('data.id');

        $this->postJson('/api/v1/whatsapp-accounts/'.$accountId.'/bridge/connect')
            ->assertOk()
            ->assertJsonPath('data.bridge.status', 'qr')
            ->assertJsonPath('data.bridge.qr_available', true)
            ->assertJsonPath('data.bridge.qr_data_url', 'data:image/png;base64,abc');

        $this->getJson('/api/v1/whatsapp-accounts/'.$accountId.'/bridge')
            ->assertOk()
            ->assertJsonPath('data.qr_data_url', 'data:image/png;base64,abc')
            ->assertJsonPath('data.poll_after_ms', 8000);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sessions/')
                && $request->hasHeader('X-Bridge-Secret', 'bridge-secret');
        });
    }

    public function test_bridge_status_cannot_update_message_belonging_to_another_account(): void
    {
        config(['whatsapp.bridge.secret' => 'bridge-secret']);

        $webAccount = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Web,
        ]);

        $otherAccount = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Web,
        ]);

        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $conversation = Conversation::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'whatsapp_account_id' => $otherAccount->id,
        ]);
        $message = Message::factory()->create([
            'company_id' => $this->company->id,
            'conversation_id' => $conversation->id,
            'whatsapp_account_id' => $otherAccount->id,
            'whatsapp_message_id' => 'account-bound-status-001',
            'status' => MessageStatus::Sending,
        ]);

        (new ProcessBridgeStatusWebhookJob($webAccount->id, [
            'whatsapp_message_id' => $message->whatsapp_message_id,
            'status' => 'sent',
        ]))->handle(app(WhatsAppBridgeWebhookService::class));

        $this->assertSame(MessageStatus::Sending, $message->fresh()->status);
    }
}
