<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permissions::USERS_MANAGE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'locale' => ['nullable', 'in:ar,en'],
            'is_active' => ['sometimes', 'boolean'],
            'role' => ['sometimes', 'in:admin,employee'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ];
    }
}
