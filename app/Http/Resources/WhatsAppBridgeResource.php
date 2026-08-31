<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppBridgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $state */
        $state = $this->resource;

        return [
            'status' => $state['status'] ?? 'disconnected',
            'qr_available' => (bool) ($state['qr_available'] ?? false),
            'qr_data_url' => $state['qr_data_url'] ?? null,
            'qr_rotates' => (bool) ($state['qr_rotates'] ?? false),
            'phone_number' => $state['phone_number'] ?? null,
            'message' => $state['message'] ?? null,
            'source' => $state['source'] ?? null,
            'is_connected' => (bool) ($state['is_connected'] ?? false),
            'poll_after_ms' => $state['poll_after_ms'] ?? null,
            'account' => isset($state['account'])
                ? new WhatsAppAccountResource($state['account'])
                : null,
        ];
    }
}
