<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'locale' => ['nullable', 'in:ar,en'],
            'is_active' => ['sometimes', 'boolean'],
            'role' => ['required', 'in:admin,employee'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ];
    }
}
