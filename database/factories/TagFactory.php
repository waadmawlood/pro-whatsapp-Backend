<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['VIP', 'Complaint', 'Order', 'Follow Up', 'Important', 'New Customer']);

        return [
            'company_id' => Company::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'color' => fake()->hexColor(),
        ];
    }
}
