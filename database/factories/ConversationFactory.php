<?php

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'whatsapp_account_id' => WhatsAppAccount::factory(),
            'customer_id' => Customer::factory(),
            'status' => ConversationStatus::Open,
            'unread_count' => 0,
            'last_message_at' => now(),
        ];
    }
}
