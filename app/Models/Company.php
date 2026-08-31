<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'slug',
    'locale',
    'timezone',
    'is_active',
    'settings',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function whatsappAccounts(): HasMany
    {
        return $this->hasMany(WhatsAppAccount::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }
}
