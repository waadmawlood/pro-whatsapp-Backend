<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'locale' => ['sometimes', 'in:ar,en'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
