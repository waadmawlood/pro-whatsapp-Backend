<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'is_online' => $this->isOnline(),
            'last_login_at' => $this->last_login_at,
            'last_seen_at' => $this->last_seen_at,
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'permissions' => $this->when(
                $this->relationLoaded('roles') || $this->relationLoaded('permissions'),
                fn () => $this->getAllPermissions()->pluck('name')->values(),
            ),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'created_at' => $this->created_at,
        ];
    }
}
