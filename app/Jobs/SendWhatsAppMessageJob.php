<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\WhatsAppConnectionType;
use App\Events\MessageStatusUpdated;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Models\Customer;
use App\Services\WhatsApp\WhatsAppBridgeClient;
use App\Services\WhatsApp\WhatsAppCloudClient;
use App\Support\WhatsAppJid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $message = Message::withoutGlobalScopes()
            ->with(['conversation.customer', 'whatsappAccount', 'media'])
            ->find($this->messageId);

        if (! $message || ! $message->whatsappAccount) {
            return;
        }

        $account = $message->whatsappAccount;
        $customer = $message->conversation->customer;
        $to = $customer->whatsapp_number;
        $jid = $this->resolveBridgeJid($customer);

        // Groups can only be sent through the WhatsApp Web bridge (Baileys);
        // Meta Cloud API rejects @g.us recipients. Fall back to a web account
        // for the company when the conversation is tied to a cloud account.
        if ($customer->isGroup() && ! $account->isWebConnection()) {
            $account = $this->resolveWebAccountForCompany($account->company_id);

            if (! $account) {
                $message->update([
                    'status' => MessageStatus::Failed,
                    'error_message' => 'WhatsApp groups require a WhatsApp Web (bridge) account. Connect a web account for this company.',
                ]);
                MessageStatusUpdated::dispatch($message->fresh(['conversation', 'media', 'user']));

                return;
            }
        }

        try {
            $response = $account->isWebConnection()
                ? $this->dispatchToBridge($account, $message, $to, $jid)
                : $this->dispatchToCloud(new WhatsAppCloudClient($account), $message, $to, $jid);

            $whatsappId = $account->isWebConnection()
                ? ($response['whatsapp_message_id'] ?? null)
                : ($response['messages'][0]['id'] ?? null);

            $message->update([
                'whatsapp_message_id' => $whatsappId,
                'status' => MessageStatus::Sent,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to send WhatsApp message', [
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            $message->update([
                'status' => MessageStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
        }

        MessageStatusUpdated::dispatch($message->fresh(['conversation', 'media', 'user']));
    }

    /**
     * @return array<string, mixed>
     */
    protected function dispatchToCloud(WhatsAppCloudClient $client, Message $message, string $to, ?string $jid = null): array
    {
        if ($message->type === MessageType::Text) {
            return $client->sendText($to, (string) $message->body, $jid);
        }

        $media = $message->media->first();

        if (! $media) {
            return $client->sendText($to, (string) $message->body, $jid);
        }

        $absolute = $media->path
            ? storage_path('app/public/'.$media->path)
            : null;

        $mediaId = $media->whatsapp_media_id;

        if (! $mediaId && $absolute && is_file($absolute)) {
            $mediaId = $client->uploadMedia($absolute, $media->mime_type ?: 'application/octet-stream');
            $media->update(['whatsapp_media_id' => $mediaId]);
        }

        if (! $mediaId) {
            throw new \RuntimeException('Media file is missing for outbound WhatsApp message.');
        }

        return $client->sendMedia(
            $to,
            $message->type->value,
            $mediaId,
            $media->caption ?? $message->body,
            $media->filename,
            $jid,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function dispatchToBridge(WhatsAppAccount $account, Message $message, string $to, ?string $jid = null): array
    {
        $client = new WhatsAppBridgeClient($account);

        if ($message->type === MessageType::Text) {
            return $client->sendText($to, (string) $message->body, $jid);
        }

        $media = $message->media->first();

        if (! $media || ! $media->path) {
            return $client->sendText($to, (string) ($media?->caption ?? $message->body), $jid);
        }

        $absolute = storage_path('app/public/'.$media->path);

        return $client->sendMedia(
            $to,
            $message->type->value,
            $absolute,
            $media->caption ?? $message->body,
            $media->mime_type,
            $jid,
        );
    }

    protected function resolveBridgeJid(Customer $customer): ?string
    {
        if ($customer->whatsapp_jid) {
            return $customer->whatsapp_jid;
        }

        if ($customer->isGroup()) {
            return WhatsAppJid::groupJidFromNumber($customer->whatsapp_number);
        }

        return WhatsAppJid::inferFromStoredNumber($customer->whatsapp_number);
    }

    protected function resolveWebAccountForCompany(int $companyId): ?WhatsAppAccount
    {
        return WhatsAppAccount::query()
            ->where('company_id', $companyId)
            ->where('connection_type', WhatsAppConnectionType::Web)
            ->latest('id')
            ->first();
    }
}
