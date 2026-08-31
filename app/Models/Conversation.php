<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Permissions;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id',
    'whatsapp_account_id',
    'customer_id',
    'link_id',
    'assigned_user_id',
    'status',
    'unread_count',
    'last_message_preview',
    'last_message_at',
    'closed_at',
])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function isUnassigned(): bool
    {
        return $this->assigned_user_id === null;
    }

    public function isOpen(): bool
    {
        return $this->status === ConversationStatus::Open;
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_user_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ConversationStatus::Open);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(Permissions::CONVERSATIONS_VIEW_ALL)) {
            return $query;
        }

        return $query->where('assigned_user_id', $user->id);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $builder) use ($like): void {
            $builder->where('link_id', 'like', $like)
                ->orWhere('last_message_preview', 'like', $like)
                ->orWhereHas('customer', function (Builder $customer) use ($like): void {
                    $customer->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('whatsapp_number', 'like', $like);
                })
                ->orWhereHas('customer.tags', fn (Builder $tags) => $tags->where('name', 'like', $like))
                ->orWhereHas('assignedUser', fn (Builder $user) => $user->where('name', 'like', $like))
                ->orWhereHas('messages', fn (Builder $messages) => $messages->where('body', 'like', $like));
        });
    }
}
