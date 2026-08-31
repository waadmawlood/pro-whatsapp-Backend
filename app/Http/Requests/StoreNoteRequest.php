<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permissions::NOTES_CREATE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'integer'],
        ];
    }
}
