<?php

namespace App\Http\Requests;

use App\Enums\CustomerChatType;
use App\Enums\CustomerStatus;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permissions::CUSTOMERS_UPDATE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'whatsapp_number' => ['sometimes', 'string', 'max:30'],
            'whatsapp_jid' => ['sometimes', 'string', 'max:255'],
            'chat_type' => ['sometimes', Rule::enum(CustomerChatType::class)],
            'assigned_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('company_id', $this->user()->company_id),
            ],
            'whatsapp_account_id' => [
                'nullable',
                Rule::exists('whatsapp_accounts', 'id')->where('company_id', $this->user()->company_id),
            ],
            'status' => ['sometimes', Rule::enum(CustomerStatus::class)],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => [
                Rule::exists('tags', 'id')->where('company_id', $this->user()->company_id),
            ],
        ];
    }
}
