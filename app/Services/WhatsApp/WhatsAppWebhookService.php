<?php

namespace App\Services\WhatsApp;

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\WhatsAppAccountStatus;
use App\Events\MessageStatusUpdated;
use App\Jobs\DownloadWhatsAppMediaJob;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Services\ConversationService;
use App\Services\CustomerService;
use App\Services\MessageService;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

class WhatsAppWebhookService
{
    public function __construct(
        protected CustomerService $customers,
        protected ConversationService $conversations,
        protected MessageService $messages,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                if (! $phoneNumberId) {
                    continue;
                }

                $account = WhatsAppAccount::withoutGlobalScopes()
                    ->where('phone_number_id', $phoneNumberId)
                    ->first();

                if (! $account) {
                    Log::warning('WhatsApp webhook for unknown phone_number_id', ['phone_number_id' => $phoneNumberId]);

                    continue;
                }

                $account->forceFill([
                    'status' => WhatsAppAccountStatus::Connected,
                    'last_webhook_at' => now(),
                ])->save();

                app()->instance('current_company_id', $account->company_id);
                app(PermissionRegistrar::class)->setPermissionsTeamId($account->company_id);

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->handleStatus($status);
                }

                foreach ($value['messages'] ?? [] as $incoming) {
                    $this->handleIncomingMessage($account, $incoming, $value['contacts'][0] ?? []);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $status
     */
    protected function handleStatus(array $status): void
    {
        $whatsappId = $status['id'] ?? null;

        if (! $whatsappId) {
            return;
        }

        $message = Message::withoutGlobalScopes()
            ->where('whatsapp_message_id', $whatsappId)
            ->first();

        if (! $message) {
            return;
        }

        $mapped = match ($status['status'] ?? null) {
            'sent' => MessageStatus::Sent,
            'delivered' => MessageStatus::Delivered,
            'read' => MessageStatus::Read,
            'failed' => MessageStatus::Failed,
            default => null,
        };

        if (! $mapped) {
            return;
        }

        $timestamp = isset($status['timestamp'])
            ? Carbon::createFromTimestamp((int) $status['timestamp'])
            : now();

        $updates = ['status' => $mapped];

        if ($mapped === MessageStatus::Delivered) {
            $updates['delivered_at'] = $timestamp;
        }

        if ($mapped === MessageStatus::Read) {
            $updates['read_at'] = $timestamp;
            $updates['delivered_at'] ??= $timestamp;
        }

        if ($mapped === MessageStatus::Failed) {
            $updates['error_message'] = $status['errors'][0]['message'] ?? 'WhatsApp delivery failed';
        }

        $message->update($updates);

        MessageStatusUpdated::dispatch($message->fresh(['conversation']));
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $contact
     */
    protected function handleIncomingMessage(WhatsAppAccount $account, array $incoming, array $contact): void
    {
        $whatsappId = $incoming['id'] ?? null;

        if ($whatsappId && Message::withoutGlobalScopes()->where('whatsapp_message_id', $whatsappId)->exists()) {
            return;
        }

        $from = PhoneNumber::normalize($incoming['from'] ?? '');
        $profileName = $contact['profile']['name'] ?? null;
        $type = $this->mapType($incoming['type'] ?? 'text');
        $body = $this->extractBody($incoming, $type);
        $sentAt = isset($incoming['timestamp'])
            ? Carbon::createFromTimestamp((int) $incoming['timestamp'])
            : now();

        $customer = $this->customers->findOrCreateFromWhatsApp($account, $from, $profileName);
        $conversation = $this->conversations->findOrCreateOpen($customer, $account);

        $message = $this->messages->storeInbound($conversation, [
            'type' => $type,
            'body' => $body,
            'whatsapp_message_id' => $whatsappId,
            'metadata' => $incoming,
            'sent_at' => $sentAt,
        ]);

        if ($type->isMedia()) {
            $mediaId = $incoming[$type->value]['id'] ?? null;

            if ($mediaId) {
                DownloadWhatsAppMediaJob::dispatch($message->id, $mediaId, $type->value);
            }
        }
    }

    protected function mapType(string $type): MessageType
    {
        return match ($type) {
            'text' => MessageType::Text,
            'image' => MessageType::Image,
            'video' => MessageType::Video,
            'document' => MessageType::Document,
            'audio', 'voice' => MessageType::Audio,
            'sticker' => MessageType::Sticker,
            'location' => MessageType::Location,
            'contacts' => MessageType::Contacts,
            default => MessageType::Unknown,
        };
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    protected function extractBody(array $incoming, MessageType $type): ?string
    {
        return match ($type) {
            MessageType::Text => $incoming['text']['body'] ?? null,
            MessageType::Image, MessageType::Video, MessageType::Document => $incoming[$type->value]['caption'] ?? null,
            MessageType::Location => trim(($incoming['location']['name'] ?? '').' '.($incoming['location']['address'] ?? '')),
            default => null,
        };
    }

    public function verifySignature(WhatsAppAccount $account, string $payload, ?string $signature): bool
    {
        if (! $account->app_secret || ! $signature) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $account->app_secret);

        return hash_equals($expected, $signature);
    }
}
