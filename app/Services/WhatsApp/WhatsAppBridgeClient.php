<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppBridgeClient
{
    public function __construct(protected WhatsAppAccount $account) {}

    /**
     * @return array<string, mixed>
     */
    public function startSession(): array
    {
        return $this->post("/sessions/{$this->account->id}/start");
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->get("/sessions/{$this->account->id}/status");
    }

    /**
     * @return array<string, mixed>
     */
    public function qr(): array
    {
        return $this->get("/sessions/{$this->account->id}/qr");
    }

    /**
     * @return array<string, mixed>
     */
    public function logout(): array
    {
        return $this->post("/sessions/{$this->account->id}/logout");
    }

    /**
     * @return array<string, mixed>
     */
    public function sendText(string $to, string $body, ?string $jid = null): array
    {
        return $this->post("/sessions/{$this->account->id}/send-text", array_filter([
            'to' => $to,
            'body' => $body,
            'jid' => $jid,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function sendMedia(string $to, string $type, string $filePath, ?string $caption = null, ?string $mimetype = null, ?string $jid = null): array
    {
        if (! is_file($filePath)) {
            throw new RuntimeException('Media file not found: '.$filePath);
        }

        return $this->post("/sessions/{$this->account->id}/send-media", array_filter([
            'to' => $to,
            'type' => $type,
            'caption' => $caption,
            'mimetype' => $mimetype,
            'filename' => basename($filePath),
            'file_base64' => base64_encode(file_get_contents($filePath)),
            'jid' => $jid,
        ], fn ($value) => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function post(string $path, array $data = []): array
    {
        $response = $this->http()->post($this->url($path), $data);

        if ($response->failed()) {
            throw new RuntimeException('Bridge error: '.$response->json('message', $response->body()));
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function get(string $path): array
    {
        $response = $this->http()->get($this->url($path));

        if ($response->failed()) {
            throw new RuntimeException('Bridge error: '.$response->json('message', $response->body()));
        }

        return $response->json() ?? [];
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(config('whatsapp.bridge.timeout', 30))
            ->withHeaders([
                'X-Bridge-Secret' => config('whatsapp.bridge.secret'),
                'Accept' => 'application/json',
            ]);
    }

    protected function url(string $path): string
    {
        return rtrim(config('whatsapp.bridge.url'), '/').$path;
    }
}
