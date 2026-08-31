<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignConversationRequest extends FormRequest
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
            'user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('company_id', $this->user()->company_id)->where('is_active', true),
            ],
        ];
    }
}
