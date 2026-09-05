<?php

namespace App\Models;

use App\Enums\CustomerChatType;
use App\Enums\CustomerStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Support\WhatsAppJid;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id',
    'whatsapp_account_id',
    'assigned_user_id',
    'name',
    'phone',
    'whatsapp_number',
    'whatsapp_jid',
    'chat_type',
    'avatar',
    'status',
    'last_contacted_at',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'chat_type' => CustomerChatType::class,
            'last_contacted_at' => 'datetime',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'customer_tag');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $builder) use ($like): void {
            $builder->where('name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('whatsapp_number', 'like', $like)
                ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', $like));
        });
    }

    public function isGroup(): bool
    {
        return $this->chat_type === CustomerChatType::Group;
    }

    public function isGroupRecipient(?string $resolvedJid = null): bool
    {
        if ($this->isGroup()) {
            return true;
        }

        if ($resolvedJid && WhatsAppJid::isGroupJid($resolvedJid)) {
            return true;
        }

        if ($this->whatsapp_jid && WhatsAppJid::isGroupJid($this->whatsapp_jid)) {
            return true;
        }

        $digits = preg_replace('/\D/', '', (string) $this->whatsapp_number) ?? '';

        return strlen($digits) > 15;
    }

    public function displayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        if ($this->isGroup()) {
            return 'مجموعة '.$this->whatsapp_number;
        }

        return $this->whatsapp_number;
    }
}
