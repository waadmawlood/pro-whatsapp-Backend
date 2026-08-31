<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'link_id' => $this->link_id,
            'status' => $this->status,
            'assigned_user_id' => $this->assigned_user_id,
            'whatsapp_account_id' => $this->whatsapp_account_id,
            'unread_count' => $this->unread_count,
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->last_message_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'assigned_user' => new UserResource($this->whenLoaded('assignedUser')),
            'whatsapp_account' => new WhatsAppAccountResource($this->whenLoaded('whatsappAccount')),
        ];
    }
}
