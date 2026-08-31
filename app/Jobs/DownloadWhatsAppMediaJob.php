<?php

namespace App\Jobs;

use App\Enums\MessageType;
use App\Models\Message;
use App\Services\MessageService;
use App\Services\WhatsApp\WhatsAppCloudClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DownloadWhatsAppMediaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $messageId,
        public string $whatsappMediaId,
        public string $type,
    ) {}

    public function handle(MessageService $messages): void
    {
        $message = Message::withoutGlobalScopes()
            ->with('whatsappAccount')
            ->find($this->messageId);

        if (! $message || ! $message->whatsappAccount) {
            return;
        }

        try {
            $client = new WhatsAppCloudClient($message->whatsappAccount);
            $downloaded = $client->downloadMedia($this->whatsappMediaId);

            $messages->storeBinaryMedia(
                $message,
                $downloaded['contents'],
                $downloaded['mime_type'] ?? 'application/octet-stream',
                MessageType::from($this->type),
            );
        } catch (Throwable $exception) {
            Log::error('Failed to download WhatsApp media', [
                'message_id' => $this->messageId,
                'media_id' => $this->whatsappMediaId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
