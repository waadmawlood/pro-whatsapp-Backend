<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudClient
{
    public function __construct(protected WhatsAppAccount $account) {}

    public function sendText(string $to, string $body): array
    {
        return $this->send($to, [
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ]);
    }

    public function sendMedia(string $to, string $type, string $mediaId, ?string $caption = null, ?string $filename = null): array
    {
        $payload = ['id' => $mediaId];

        if ($caption && in_array($type, ['image', 'video', 'document'], true)) {
            $payload['caption'] = $caption;
        }

        if ($filename && $type === 'document') {
            $payload['filename'] = $filename;
        }

        return $this->send($to, [
            'type' => $type,
            $type => $payload,
        ]);
    }

    public function uploadMedia(UploadedFile|string $file, string $mimeType): string
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename((string) $path);

        $response = $this->http()
            ->attach('file', file_get_contents($path), $name, ['Content-Type' => $mimeType])
            ->post($this->graphUrl($this->account->phone_number_id.'/media'), [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp media upload failed: '.$response->body());
        }

        $id = $response->json('id');

        if (! $id) {
            throw new RuntimeException('WhatsApp media upload returned no id.');
        }

        return $id;
    }

    public function downloadMedia(string $mediaId): array
    {
        $meta = $this->http()->get($this->graphUrl($mediaId));

        if ($meta->failed()) {
            throw new RuntimeException('WhatsApp media metadata failed: '.$meta->body());
        }

        $url = $meta->json('url');
        $mime = $meta->json('mime_type');
        $size = (int) $meta->json('file_size', 0);

        $binary = $this->http()->get($url);

        if ($binary->failed()) {
            throw new RuntimeException('WhatsApp media download failed.');
        }

        return [
            'contents' => $binary->body(),
            'mime_type' => $mime,
            'size' => $size,
        ];
    }

    public function markRead(string $whatsappMessageId): void
    {
        $this->http()->post($this->graphUrl($this->account->phone_number_id.'/messages'), [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $whatsappMessageId,
        ]);
    }

    protected function send(string $to, array $payload): array
    {
        $this->assertReady();

        $response = $this->http()->post($this->graphUrl($this->account->phone_number_id.'/messages'), array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ], $payload));

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp send failed: '.$response->json('error.message', $response->body()));
        }

        return $response->json();
    }

    protected function http(): PendingRequest
    {
        $this->assertReady();

        return Http::timeout(30)
            ->withToken($this->account->access_token)
            ->acceptJson();
    }

    protected function graphUrl(string $path): string
    {
        $version = config('whatsapp.graph_version', 'v21.0');

        return 'https://graph.facebook.com/'.$version.'/'.ltrim($path, '/');
    }

    protected function assertReady(): void
    {
        if (! $this->account->phone_number_id || ! $this->account->access_token) {
            throw new RuntimeException('WhatsApp account is not connected.');
        }
    }
}
