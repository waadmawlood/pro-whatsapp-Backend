<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppAccountRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'phone_number' => ['sometimes', 'string', 'max:30'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'waba_id' => ['nullable', 'string', 'max:64'],
            'access_token' => ['nullable', 'string'],
            'app_secret' => ['nullable', 'string'],
            'webhook_verify_token' => ['nullable', 'string', 'max:80'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:pending,connected,disconnected,error'],
        ];
    }
}
