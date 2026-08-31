<?php

namespace App\Models;

use App\Enums\WhatsAppAccountStatus;
use App\Enums\WhatsAppConnectionType;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\WhatsAppAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'company_id',
    'name',
    'connection_type',
    'phone_number',
    'phone_number_id',
    'waba_id',
    'access_token',
    'app_secret',
    'webhook_verify_token',
    'bridge_qr',
    'bridge_connected_at',
    'status',
    'is_default',
    'metadata',
    'last_webhook_at',
])]
#[Hidden(['access_token', 'app_secret', 'webhook_verify_token'])]
class WhatsAppAccount extends Model
{
    /** @use HasFactory<WhatsAppAccountFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $table = 'whatsapp_accounts';

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'app_secret' => 'encrypted',
            'connection_type' => WhatsAppConnectionType::class,
            'status' => WhatsAppAccountStatus::class,
            'is_default' => 'boolean',
            'metadata' => 'array',
            'last_webhook_at' => 'datetime',
            'bridge_connected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $account): void {
            if (! $account->webhook_verify_token) {
                $account->webhook_verify_token = Str::random(40);
            }
        });
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isWebConnection(): bool
    {
        return $this->connection_type === WhatsAppConnectionType::Web;
    }

    public function isCloudConnection(): bool
    {
        return $this->connection_type === WhatsAppConnectionType::Cloud;
    }
}
