<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'company_id',
    'name',
    'email',
    'phone',
    'avatar',
    'locale',
    'is_active',
    'last_login_at',
    'last_login_ip',
    'last_seen_at',
    'password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'assigned_user_id');
    }

    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(2));
    }

    public function receivesBroadcastNotificationsOn(): string
    {
        return 'user.'.$this->id;
    }
}
