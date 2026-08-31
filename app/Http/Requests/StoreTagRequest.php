<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permissions::TAGS_MANAGE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:16'],
        ];
    }
}
