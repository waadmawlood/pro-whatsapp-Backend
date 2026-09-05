<?php

namespace App\Services\WhatsApp;

use App\Enums\CustomerChatType;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\WhatsAppAccountStatus;
use App\Enums\WhatsAppConnectionType;
use App\Events\MessageStatusUpdated;
use App\Events\WhatsAppConnectionUpdated;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Services\ConversationService;
use App\Services\CustomerService;
use App\Services\MessageService;
use App\Support\PhoneNumber;
use App\Support\WhatsAppJid;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

class WhatsAppBridgeWebhookService
{
    public function __construct(
        protected CustomerService $customers,
        protected ConversationService $conversations,
        protected MessageService $messages,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleConnection(WhatsAppAccount $account, array $payload): void
    {
        $this->setCompanyContext($account);

        $status = $payload['status'] ?? 'disconnected';
        $updates = [
            'last_webhook_at' => now(),
            'metadata' => array_merge($account->metadata ?? [], [
                'bridge_status' => $status,
                'bridge_message' => $payload['message'] ?? null,
            ]),
        ];

        if ($status === 'qr' && ! empty($payload['qr'])) {
            $updates['bridge_qr'] = $payload['qr'];
            $updates['status'] = WhatsAppAccountStatus::Pending;
        }

        if ($status === 'connecting') {
            $updates['status'] = WhatsAppAccountStatus::Pending;
            $updates['metadata']['bridge_status'] = 'connecting';

            if (filled($account->bridge_qr)) {
                $updates['bridge_qr'] = null;
            }
        }

        if ($status === 'connected') {
            $updates['status'] = WhatsAppAccountStatus::Connected;
            $updates['bridge_qr'] = null;
            $updates['bridge_connected_at'] = now();

            if (! empty($payload['phone_number'])) {
                $updates['phone_number'] = PhoneNumber::normalize($payload['phone_number']);
            }
        }

        if (in_array($status, ['disconnected', 'logged_out'], true)) {
            if ($status === 'logged_out') {
                $updates['status'] = WhatsAppAccountStatus::Disconnected;
                $updates['bridge_qr'] = null;
                $updates['bridge_connected_at'] = null;
            } elseif ($account->status === WhatsAppAccountStatus::Connected) {
                $updates['status'] = WhatsAppAccountStatus::Disconnected;
                $updates['bridge_qr'] = null;
                $updates['bridge_connected_at'] = null;
            }
            // Ignore transient disconnects while still pairing (pending + existing QR).
        }

        if ($status === 'error') {
            $updates['status'] = WhatsAppAccountStatus::Error;
        }

        $account->update($updates);

        WhatsAppConnectionUpdated::dispatch($account->fresh());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleIncomingMessage(WhatsAppAccount $account, array $payload): void
    {
        $this->setCompanyContext($account);

        $whatsappId = $payload['whatsapp_message_id'] ?? null;

        if ($whatsappId && Message::withoutGlobalScopes()->where('whatsapp_message_id', $whatsappId)->exists()) {
            return;
        }

        $remoteJid = $payload['remote_jid']
            ?? $payload['group_jid']
            ?? $payload['chat_jid']
            ?? $payload['group_id'] ?? null;

        if ($remoteJid && ! str_contains($remoteJid, '@')) {
            $remoteJid .= '@g.us';
        }

        $remoteJid ??= data_get($payload, 'group.id');

        if ($remoteJid && ! str_contains($remoteJid, '@')) {
            $remoteJid .= '@g.us';
        }
        $chatType = ($payload['chat_type'] ?? null) === 'group' || WhatsAppJid::isGroupJid($remoteJid)
            ? CustomerChatType::Group
            : CustomerChatType::Direct;

        if ($remoteJid && ! WhatsAppJid::isSupportedChatJid($remoteJid)) {
            Log::warning('Ignoring unsupported WhatsApp chat jid', [
                'account_id' => $account->id,
                'remote_jid' => $remoteJid,
            ]);

            return;
        }

        $from = PhoneNumber::normalize($payload['from'] ?? '')
            ?? WhatsAppJid::digitsFromJid($remoteJid);

        if (! $from) {
            Log::warning('Ignoring bridge message without a sender or remote JID', [
                'account_id' => $account->id,
            ]);

            return;
        }
        $type = $this->mapType($payload['type'] ?? 'text');
        $sentAt = isset($payload['timestamp'])
            ? Carbon::createFromTimestamp((int) $payload['timestamp'])
            : now();

        $customerName = $chatType->isGroup()
            ? ($payload['group_subject'] ?? $payload['push_name'] ?? null)
            : ($payload['push_name'] ?? null);

        $customer = $this->customers->findOrCreateFromWhatsApp(
            $account,
            $from,
            $customerName,
            null,
            $remoteJid,
            $chatType,
        );

        $conversation = $this->conversations->findOrCreateOpen($customer, $account);

        $messageMetadata = array_merge($payload, [
            'chat_type' => $chatType->value,
            'sender_jid' => $payload['participant_jid'] ?? null,
            'sender_name' => $payload['participant_name'] ?? null,
        ]);

        $message = $this->messages->storeInbound($conversation, [
            'type' => $type,
            'body' => $payload['body'] ?? null,
            'whatsapp_message_id' => $whatsappId,
            'metadata' => $messageMetadata,
            'sent_at' => $sentAt,
        ]);

        if ($type->isMedia() && ! empty($payload['media'])) {
            $this->storeInboundMedia($message, $type, $payload['media']);
        }

        $account->forceFill([
            'status' => WhatsAppAccountStatus::Connected,
            'last_webhook_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleStatus(WhatsAppAccount $account, array $payload): void
    {
        $this->setCompanyContext($account);

        $whatsappId = $payload['whatsapp_message_id'] ?? null;

        if (! $whatsappId) {
            return;
        }

        $message = Message::withoutGlobalScopes()
            ->where('whatsapp_message_id', $whatsappId)
            ->first();

        if (! $message) {
            return;
        }

        $mapped = match ($payload['status'] ?? null) {
            'sent', 'server_ack' => MessageStatus::Sent,
            'delivered', 'delivery_ack' => MessageStatus::Delivered,
            'read', 'read_ack' => MessageStatus::Read,
            'failed' => MessageStatus::Failed,
            default => null,
        };

        if (! $mapped) {
            return;
        }

        $updates = ['status' => $mapped];

        if ($mapped === MessageStatus::Delivered) {
            $updates['delivered_at'] = now();
        }

        if ($mapped === MessageStatus::Read) {
            $updates['read_at'] = now();
            $updates['delivered_at'] ??= now();
        }

        if ($mapped === MessageStatus::Failed) {
            $updates['error_message'] = $payload['error'] ?? 'WhatsApp Web delivery failed';
        }

        $message->update($updates);

        MessageStatusUpdated::dispatch($message->fresh(['conversation']));
    }

    /**
     * @param  array<string, mixed>  $media
     */
    protected function storeInboundMedia(Message $message, MessageType $type, array $media): void
    {
        $base64 = $media['file_base64'] ?? null;

        if (! $base64) {
            return;
        }

        $contents = base64_decode($base64, true);

        if ($contents === false) {
            Log::warning('Invalid base64 media from bridge', ['message_id' => $message->id]);

            return;
        }

        $mimetype = $media['mimetype'] ?? 'application/octet-stream';
        $filename = $media['filename'] ?? ('media-'.$message->id);

        $this->messages->storeBinaryMedia($message, $contents, $mimetype, $type, $filename);
    }

    protected function mapType(string $type): MessageType
    {
        return match ($type) {
            'text', 'conversation', 'extendedTextMessage' => MessageType::Text,
            'image', 'imageMessage' => MessageType::Image,
            'video', 'videoMessage' => MessageType::Video,
            'document', 'documentMessage' => MessageType::Document,
            'audio', 'ptt', 'audioMessage' => MessageType::Audio,
            'sticker', 'stickerMessage' => MessageType::Sticker,
            default => MessageType::Unknown,
        };
    }

    protected function setCompanyContext(WhatsAppAccount $account): void
    {
        if ($account->connection_type !== WhatsAppConnectionType::Web) {
            return;
        }

        app()->instance('current_company_id', $account->company_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($account->company_id);
    }
}
