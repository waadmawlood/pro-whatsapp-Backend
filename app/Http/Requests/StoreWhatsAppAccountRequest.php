<?php

namespace App\Http\Requests;

use App\Enums\WhatsAppConnectionType;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsAppAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permissions::WHATSAPP_MANAGE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $connectionType = $this->input('connection_type', WhatsAppConnectionType::Web->value);
        $isCloud = $connectionType === WhatsAppConnectionType::Cloud->value;

        return [
            'name' => ['required', 'string', 'max:120'],
            'connection_type' => ['sometimes', Rule::enum(WhatsAppConnectionType::class)],
            'phone_number' => [
                Rule::requiredIf($isCloud),
                'nullable',
                'string',
                'max:30',
                Rule::unique('whatsapp_accounts', 'phone_number')->where('company_id', $this->user()->company_id),
            ],
            'phone_number_id' => [Rule::requiredIf($isCloud), 'nullable', 'string', 'max:64'],
            'waba_id' => ['nullable', 'string', 'max:64'],
            'access_token' => [Rule::requiredIf($isCloud), 'nullable', 'string'],
            'app_secret' => ['nullable', 'string'],
            'webhook_verify_token' => ['nullable', 'string', 'max:80'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
