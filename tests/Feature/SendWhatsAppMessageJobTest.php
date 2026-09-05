<?php

namespace Tests\Feature;

use App\Enums\CustomerChatType;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\WhatsAppAccountStatus;
use App\Enums\WhatsAppConnectionType;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendWhatsAppMessageJobTest extends TestCase
{
    public function test_group_message_on_cloud_account_uses_linked_web_account(): void
    {
        config([
            'whatsapp.bridge.secret' => 'bridge-secret',
            'whatsapp.bridge.url' => 'http://bridge.test',
        ]);

        Http::fake([
            'bridge.test/sessions/*/send-text' => Http::response([
                'success' => true,
                'whatsapp_message_id' => 'group-msg-001',
                'status' => 'sent',
                'destination' => '120363402028185588@g.us',
            ], 200),
        ]);

        $webAccount = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Web,
            'status' => WhatsAppAccountStatus::Connected,
            'bridge_connected_at' => now(),
        ]);

        $cloudAccount = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Cloud,
            'status' => WhatsAppAccountStatus::Connected,
            'is_default' => false,
        ]);

        $customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $webAccount->id,
            'whatsapp_number' => '120363402028185588',
            'whatsapp_jid' => '120363402028185588@g.us',
            'chat_type' => CustomerChatType::Group,
        ]);

        $conversation = Conversation::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $cloudAccount->id,
            'customer_id' => $customer->id,
        ]);

        $message = Message::factory()->create([
            'company_id' => $this->company->id,
            'conversation_id' => $conversation->id,
            'whatsapp_account_id' => $cloudAccount->id,
            'direction' => MessageDirection::Outbound,
            'body' => 'مرحبا من الكروب',
            'status' => MessageStatus::Sending,
        ]);

        SendWhatsAppMessageJob::dispatchSync($message->id);

        $message->refresh();

        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertSame('group-msg-001', $message->whatsapp_message_id);

        Http::assertSent(function ($request) use ($webAccount) {
            return $request->url() === 'http://bridge.test/sessions/'.$webAccount->id.'/send-text'
                && $request['jid'] === '120363402028185588@g.us'
                && $request['body'] === 'مرحبا من الكروب';
        });
    }

    public function test_group_message_detected_by_jid_even_when_chat_type_is_direct(): void
    {
        config([
            'whatsapp.bridge.secret' => 'bridge-secret',
            'whatsapp.bridge.url' => 'http://bridge.test',
        ]);

        Http::fake([
            'bridge.test/sessions/*/send-text' => Http::response([
                'success' => true,
                'whatsapp_message_id' => 'group-msg-002',
                'status' => 'sent',
            ], 200),
        ]);

        $webAccount = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Web,
            'status' => WhatsAppAccountStatus::Connected,
            'bridge_connected_at' => now(),
        ]);

        $cloudAccount = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Cloud,
            'is_default' => false,
        ]);

        $customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $webAccount->id,
            'whatsapp_number' => '120363402028185588',
            'whatsapp_jid' => '120363402028185588@g.us',
            'chat_type' => CustomerChatType::Direct,
        ]);

        $conversation = Conversation::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $cloudAccount->id,
            'customer_id' => $customer->id,
        ]);

        $message = Message::factory()->create([
            'company_id' => $this->company->id,
            'conversation_id' => $conversation->id,
            'whatsapp_account_id' => $cloudAccount->id,
            'direction' => MessageDirection::Outbound,
            'body' => 'رسالة مجموعة',
            'status' => MessageStatus::Sending,
        ]);

        SendWhatsAppMessageJob::dispatchSync($message->id);

        $message->refresh();

        $this->assertSame(MessageStatus::Sent, $message->status);

        Http::assertSent(fn ($request) => $request->url() === 'http://bridge.test/sessions/'.$webAccount->id.'/send-text');
    }

    public function test_group_message_fails_when_no_web_account_exists(): void
    {
        $cloudAccount = WhatsAppAccount::factory()->create([
            'company_id' => $this->company->id,
            'connection_type' => WhatsAppConnectionType::Cloud,
        ]);

        $customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_number' => '120363402028185588',
            'whatsapp_jid' => '120363402028185588@g.us',
            'chat_type' => CustomerChatType::Group,
        ]);

        $conversation = Conversation::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $cloudAccount->id,
            'customer_id' => $customer->id,
        ]);

        $message = Message::factory()->create([
            'company_id' => $this->company->id,
            'conversation_id' => $conversation->id,
            'whatsapp_account_id' => $cloudAccount->id,
            'direction' => MessageDirection::Outbound,
            'body' => 'test',
            'status' => MessageStatus::Sending,
        ]);

        SendWhatsAppMessageJob::dispatchSync($message->id);

        $message->refresh();

        $this->assertSame(MessageStatus::Failed, $message->status);
        $this->assertStringContainsString('WhatsApp Web', (string) $message->error_message);
    }
}
