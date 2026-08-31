<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use Database\Seeders\RolePermissionSeeder;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    public function test_company_cannot_view_another_company_customers(): void
    {
        $adminA = $this->makeAdmin();
        $customerA = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Customer A',
        ]);

        $companyB = Company::factory()->create();
        app(RolePermissionSeeder::class)->seedForCompany($companyB);
        $this->setCompanyContext($companyB);

        $adminB = $this->makeEmployee([
            'company_id' => $companyB->id,
            'email' => 'admin-b@example.com',
        ]);
        $adminB->syncRoles(['admin']);

        $this->actingAsUser($adminB);

        $this->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/customers/'.$customerA->id)
            ->assertNotFound();
    }
}
