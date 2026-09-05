<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
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
        /** @var Conversation|null $conversation */
        $conversation = $this->route('conversation');

        return [
            'link_id' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('conversations', 'link_id')
                    ->where(fn ($query) => $query
                        ->where('company_id', $this->user()->company_id)
                        ->whereNull('deleted_at'))
                    ->ignore($conversation?->id),
            ],
        ];
    }
}
