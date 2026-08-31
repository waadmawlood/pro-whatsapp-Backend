<?php

namespace Tests;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        app(RolePermissionSeeder::class)->seedForCompany($this->company);
        $this->setCompanyContext($this->company);
    }

    protected function setCompanyContext(?Company $company = null): void
    {
        $company ??= $this->company;
        app()->instance('current_company_id', $company->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
    }

    protected function makeAdmin(array $overrides = []): User
    {
        $company = $this->companyFromOverrides($overrides);
        $this->setCompanyContext($company);

        $user = User::factory()->create(array_merge([
            'company_id' => $company->id,
        ], $overrides));

        $user->assignRole('admin');

        return $user;
    }

    protected function makeEmployee(array $overrides = []): User
    {
        $company = $this->companyFromOverrides($overrides);
        $this->setCompanyContext($company);

        $user = User::factory()->create(array_merge([
            'company_id' => $company->id,
        ], $overrides));

        $user->assignRole('employee');

        return $user;
    }

    protected function companyFromOverrides(array $overrides): Company
    {
        if (! isset($overrides['company_id'])) {
            return $this->company;
        }

        return $overrides['company_id'] instanceof Company
            ? $overrides['company_id']
            : Company::query()->findOrFail($overrides['company_id']);
    }

    protected function actingAsUser(User $user): User
    {
        $this->setCompanyContext($user->company);
        Sanctum::actingAs($user);

        return $user;
    }
}
