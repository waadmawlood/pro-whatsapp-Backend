<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $company = Company::query()->firstOrCreate(
            ['slug' => 'pro-whatsapp'],
            [
                'name' => 'Pro WhatsApp',
                'locale' => 'ar',
                'timezone' => 'Asia/Riyadh',
                'is_active' => true,
            ],
        );

        $roles = new RolePermissionSeeder;
        $roles->seedForCompany($company);

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'company_id' => $company->id,
                'name' => 'Admin',
                'phone' => '966500000001',
                'password' => Hash::make('password'),
                'locale' => 'ar',
                'is_active' => true,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ],
        );
        $admin->syncRoles(['admin']);

        $employee = User::query()->firstOrCreate(
            ['email' => 'ahmed@example.com'],
            [
                'company_id' => $company->id,
                'name' => 'Ahmed',
                'phone' => '966500000002',
                'password' => Hash::make('password'),
                'locale' => 'ar',
                'is_active' => true,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ],
        );
        $employee->syncRoles(['employee']);

        (new DefaultTagSeeder)->seedForCompany($company);
    }
}
