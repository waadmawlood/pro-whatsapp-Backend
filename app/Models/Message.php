<?php

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'conversation_id',
    'whatsapp_account_id',
    'user_id',
    'direction',
    'type',
    'body',
    'whatsapp_message_id',
    'status',
    'error_message',
    'metadata',
    'sent_at',
    'delivered_at',
    'read_at',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'type' => MessageType::class,
            'status' => MessageStatus::class,
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === MessageDirection::Inbound;
    }

    public function preview(): string
    {
        $body = $this->body;

        if ($this->isInbound() && ($this->metadata['chat_type'] ?? null) === 'group') {
            $sender = $this->metadata['sender_name'] ?? $this->metadata['participant_name'] ?? null;

            if ($sender && $body) {
                $body = $sender.': '.$body;
            }
        }

        if ($body) {
            return mb_substr($body, 0, 140);
        }

        return match ($this->type) {
            MessageType::Image => '[image]',
            MessageType::Video => '[video]',
            MessageType::Document => '[document]',
            MessageType::Audio => '[audio]',
            default => '['.$this->type->value.']',
        };
    }
}
