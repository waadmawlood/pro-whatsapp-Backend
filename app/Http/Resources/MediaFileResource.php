<?php

namespace App\Http\Resources;

use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MediaFile */
class MediaFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => $this->url(),
            'caption' => $this->caption,
        ];
    }
}
