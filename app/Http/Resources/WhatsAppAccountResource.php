<?php

namespace App\Http\Resources;

use App\Enums\WhatsAppConnectionType;
use App\Models\WhatsAppAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WhatsAppAccount */
class WhatsAppAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'connection_type' => $this->connection_type ?? WhatsAppConnectionType::Web,
            'phone_number' => $this->phone_number,
            'phone_number_id' => $this->phone_number_id,
            'waba_id' => $this->waba_id,
            'status' => $this->status,
            'is_default' => $this->is_default,
            'webhook_verify_token' => $this->when($this->isCloudConnection(), $this->webhook_verify_token),
            'has_access_token' => filled($this->access_token),
            'webhook_url' => $this->when($this->isCloudConnection(), url('/api/v1/webhooks/whatsapp/'.$this->id)),
            'bridge_qr_available' => $this->isWebConnection() && filled($this->bridge_qr),
            'bridge_connected_at' => $this->bridge_connected_at,
            'last_webhook_at' => $this->last_webhook_at,
            'created_at' => $this->created_at,
        ];
    }
}
