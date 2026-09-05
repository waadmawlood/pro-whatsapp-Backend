<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\MediaFile;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Support\Permissions;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class MessageService
{
    public function __construct(protected ConversationService $conversations) {}

    public function send(Conversation $conversation, User $user, array $payload, ?UploadedFile $file = null): Message
    {
        $type = MessageType::from($payload['type'] ?? 'text');

        $message = Message::create([
            'company_id' => $conversation->company_id,
            'conversation_id' => $conversation->id,
            'whatsapp_account_id' => $conversation->whatsapp_account_id,
            'user_id' => $user->id,
            'direction' => MessageDirection::Outbound,
            'type' => $type,
            'body' => $payload['body'] ?? $payload['caption'] ?? null,
            'status' => MessageStatus::Sending,
            'sent_at' => now(),
        ]);

        if ($file) {
            $this->storeUploadedMedia($message, $file, $type, $payload['caption'] ?? null);
        }

        $this->touchConversation($conversation, $message, incrementUnread: false);

        MessageCreated::dispatch($message->load(['media', 'user', 'conversation.customer']));
        SendWhatsAppMessageJob::dispatch($message->id);

        return $message->fresh(['media', 'user']);
    }

    public function storeInbound(Conversation $conversation, array $payload): Message
    {
        $whatsappMessageId = $payload['whatsapp_message_id'] ?? null;

        if ($whatsappMessageId) {
            $existing = Message::withoutGlobalScopes()
                ->where('whatsapp_message_id', $whatsappMessageId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        try {
            $message = Message::withoutGlobalScopes()->create([
                'company_id' => $conversation->company_id,
                'conversation_id' => $conversation->id,
                'whatsapp_account_id' => $conversation->whatsapp_account_id,
                'user_id' => null,
                'direction' => MessageDirection::Inbound,
                'type' => $payload['type'] ?? MessageType::Text,
                'body' => $payload['body'] ?? null,
                'whatsapp_message_id' => $whatsappMessageId,
                'status' => MessageStatus::Delivered,
                'metadata' => $payload['metadata'] ?? null,
                'sent_at' => $payload['sent_at'] ?? now(),
            ]);
        } catch (QueryException $exception) {
            if (! $whatsappMessageId) {
                throw $exception;
            }

            $existing = Message::withoutGlobalScopes()
                ->where('whatsapp_message_id', $whatsappMessageId)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }

        $this->touchConversation($conversation, $message, incrementUnread: true);
        $this->notifyInbound($conversation->fresh(['customer', 'assignedUser']), $message);

        MessageCreated::dispatch($message->load(['media', 'conversation.customer']));

        return $message;
    }

    public function storeUploadedMedia(Message $message, UploadedFile $file, MessageType $type, ?string $caption = null): MediaFile
    {
        $path = $file->store('media/'.$message->company_id.'/'.$message->id, 'public');

        return MediaFile::create([
            'company_id' => $message->company_id,
            'message_id' => $message->id,
            'type' => $type,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'disk' => 'public',
            'path' => $path,
            'caption' => $caption,
        ]);
    }

    public function storeBinaryMedia(Message $message, string $contents, string $mimeType, MessageType $type, ?string $filename = null): MediaFile
    {
        $name = $this->ensureFilenameExtension($filename ?: (Str::uuid()->toString()), $mimeType);
        $path = 'media/'.$message->company_id.'/'.$message->id.'/'.$name;

        Storage::disk('public')->put($path, $contents);

        return MediaFile::withoutGlobalScopes()->create([
            'company_id' => $message->company_id,
            'message_id' => $message->id,
            'type' => $type,
            'filename' => $name,
            'mime_type' => $mimeType,
            'size' => strlen($contents),
            'disk' => 'public',
            'path' => $path,
        ]);
    }

    protected function touchConversation(Conversation $conversation, Message $message, bool $incrementUnread): void
    {
        $conversation->forceFill([
            'last_message_preview' => $message->preview(),
            'last_message_at' => $message->sent_at ?? now(),
            'unread_count' => $incrementUnread ? $conversation->unread_count + 1 : $conversation->unread_count,
        ])->save();

        $customer = $conversation->customer;

        if ($customer) {
            $customer->forceFill([
                'last_contacted_at' => now(),
                'status' => $customer->status === CustomerStatus::New
                    ? CustomerStatus::Active
                    : $customer->status,
            ])->save();
        }

        ConversationUpdated::dispatch($conversation->fresh(['customer.tags', 'assignedUser']), 'updated');
    }

    protected function notifyInbound(Conversation $conversation, Message $message): void
    {
        app()->instance('current_company_id', $conversation->company_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($conversation->company_id);

        if ($conversation->assigned_user_id) {
            $conversation->assignedUser?->notify(new NewMessageNotification($conversation, $message));

            return;
        }

        User::query()
            ->where('company_id', $conversation->company_id)
            ->where('is_active', true)
            ->chunkById(100, function ($users) use ($conversation, $message): void {
                $users
                    ->filter(fn (User $user) => $user->can(Permissions::CONVERSATIONS_VIEW_ALL))
                    ->each(fn (User $user) => $user->notify(new NewMessageNotification($conversation, $message)));
            });
    }

    protected function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            'image/gif' => '.gif',
            'video/mp4' => '.mp4',
            'audio/ogg' => '.ogg',
            'audio/mpeg' => '.mp3',
            'audio/mp4' => '.m4a',
            'application/pdf' => '.pdf',
            default => '',
        };
    }

    protected function ensureFilenameExtension(string $filename, string $mimeType): string
    {
        if (preg_match('/\.\w+$/', $filename)) {
            return $filename;
        }

        $extension = $this->extensionFromMime($mimeType);

        return $extension ? $filename.$extension : $filename;
    }
}
