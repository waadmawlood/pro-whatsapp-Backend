<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(
        protected ?User $user = null,
        protected ?Request $request = null,
    ) {
        $this->request ??= request();
        $this->user ??= $this->request?->user();
    }

    public static function make(?User $user = null): self
    {
        return new self($user);
    }

    public function log(
        string $action,
        ?Model $entity = null,
        ?string $description = null,
        ?array $old = null,
        ?array $new = null,
    ): AuditLog {
        return AuditLog::withoutGlobalScopes()->create([
            'company_id' => $this->user?->company_id ?? $entity?->getAttribute('company_id'),
            'user_id' => $this->user?->id,
            'action' => $action,
            'entity_type' => $entity ? $entity->getMorphClass() : null,
            'entity_id' => $entity?->getKey(),
            'description' => $description,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
