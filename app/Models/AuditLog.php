<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'company_id',
    'user_id',
    'action',
    'entity_type',
    'entity_id',
    'description',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
    'created_at',
])]
class AuditLog extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }
}
