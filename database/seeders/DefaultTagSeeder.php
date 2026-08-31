<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultTagSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->each(fn (Company $company) => $this->seedForCompany($company));
    }

    public function seedForCompany(Company $company): void
    {
        $tags = [
            ['name' => 'New Customer', 'color' => '#2563EB'],
            ['name' => 'VIP', 'color' => '#D97706'],
            ['name' => 'Complaint', 'color' => '#DC2626'],
            ['name' => 'Order', 'color' => '#059669'],
            ['name' => 'Follow Up', 'color' => '#7C3AED'],
            ['name' => 'Important', 'color' => '#DB2777'],
        ];

        foreach ($tags as $tag) {
            Tag::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'slug' => Str::slug($tag['name']),
                ],
                [
                    'name' => $tag['name'],
                    'color' => $tag['color'],
                ],
            );
        }
    }
}
