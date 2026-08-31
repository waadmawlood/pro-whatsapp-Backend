<?php

namespace App\Http\Requests;

use App\Enums\MessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(MessageType::class)],
            'body' => ['required_without:file', 'nullable', 'string', 'max:4096'],
            'caption' => ['nullable', 'string', 'max:1024'],
            'file' => ['nullable', 'file', 'max:16384', 'mimes:jpg,jpeg,png,webp,gif,mp4,pdf,doc,docx,xls,xlsx,txt,ogg,mp3,m4a,aac,zip'],
        ];
    }
}
