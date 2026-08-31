<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        $phone = fake()->unique()->numerify('9665########');

        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'phone' => $phone,
            'whatsapp_number' => $phone,
            'status' => CustomerStatus::New,
        ];
    }
}
