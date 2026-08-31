<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaFileController extends Controller
{
    public function show(int $mediaFile): StreamedResponse
    {
        $file = MediaFile::withoutGlobalScopes()->findOrFail($mediaFile);

        if (! $file->path || ! Storage::disk($file->disk)->exists($file->path)) {
            abort(404);
        }

        return Storage::disk($file->disk)->response(
            $file->path,
            $file->filename,
            ['Content-Type' => $file->mime_type ?: 'application/octet-stream'],
        );
    }
}
