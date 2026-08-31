<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'phone' => $this->phone,
            'whatsapp_number' => $this->whatsapp_number,
            'whatsapp_jid' => $this->whatsapp_jid,
            'chat_type' => $this->chat_type,
            'avatar' => $this->avatar,
            'status' => $this->status,
            'assigned_user_id' => $this->assigned_user_id,
            'whatsapp_account_id' => $this->whatsapp_account_id,
            'last_contacted_at' => $this->last_contacted_at,
            'created_at' => $this->created_at,
            'assigned_user' => new UserResource($this->whenLoaded('assignedUser')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
