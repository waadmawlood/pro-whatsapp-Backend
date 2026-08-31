<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'direction' => $this->direction,
            'type' => $this->type,
            'body' => $this->body,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'sender_name' => $this->metadata['sender_name'] ?? $this->metadata['participant_name'] ?? null,
            'sender_jid' => $this->metadata['sender_jid'] ?? $this->metadata['participant_jid'] ?? null,
            'chat_type' => $this->metadata['chat_type'] ?? null,
            'sent_at' => $this->sent_at,
            'delivered_at' => $this->delivered_at,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'media' => MediaFileResource::collection($this->whenLoaded('media')),
        ];
    }
}
