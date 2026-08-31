<?php

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'conversation_id' => Conversation::factory(),
            'whatsapp_account_id' => WhatsAppAccount::factory(),
            'direction' => MessageDirection::Inbound,
            'type' => MessageType::Text,
            'body' => fake()->sentence(),
            'status' => MessageStatus::Delivered,
            'sent_at' => now(),
        ];
    }
}
