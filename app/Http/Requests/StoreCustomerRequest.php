<?php

namespace App\Http\Requests;

use App\Enums\CustomerChatType;
use App\Enums\CustomerStatus;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permissions::CUSTOMERS_CREATE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp_number' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('customers', 'whatsapp_number')->where('company_id', $this->user()->company_id),
            ],
            'assigned_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('company_id', $this->user()->company_id),
            ],
            'whatsapp_account_id' => [
                'nullable',
                Rule::exists('whatsapp_accounts', 'id')->where('company_id', $this->user()->company_id),
            ],
            'whatsapp_jid' => ['nullable', 'string', 'max:255'],
            'chat_type' => ['nullable', Rule::enum(CustomerChatType::class)],
            'status' => ['nullable', Rule::enum(CustomerStatus::class)],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                Rule::exists('tags', 'id')->where('company_id', $this->user()->company_id),
            ],
        ];
    }
}
