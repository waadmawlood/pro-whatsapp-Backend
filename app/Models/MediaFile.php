<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'company_id',
    'message_id',
    'type',
    'filename',
    'mime_type',
    'size',
    'disk',
    'path',
    'whatsapp_media_id',
    'caption',
])]
class MediaFile extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'size' => 'integer',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function url(): ?string
    {
        if (! $this->path) {
            return null;
        }

        if (! Storage::disk($this->disk)->exists($this->path)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'api.v1.media-files.show',
            now()->addHours(12),
            ['mediaFile' => $this->id],
        );
    }
}
