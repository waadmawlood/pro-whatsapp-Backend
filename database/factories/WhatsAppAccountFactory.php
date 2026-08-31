<?php

namespace Database\Factories;

use App\Enums\WhatsAppAccountStatus;
use App\Enums\WhatsAppConnectionType;
use App\Models\Company;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsAppAccount>
 */
class WhatsAppAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Main Line',
            'connection_type' => WhatsAppConnectionType::Cloud,
            'phone_number' => fake()->unique()->numerify('9665########'),
            'phone_number_id' => fake()->unique()->numerify('##########'),
            'waba_id' => fake()->numerify('##########'),
            'access_token' => 'test-token',
            'app_secret' => 'test-secret',
            'webhook_verify_token' => Str::random(32),
            'status' => WhatsAppAccountStatus::Connected,
            'is_default' => true,
        ];
    }
}
