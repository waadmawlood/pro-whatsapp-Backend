<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    public function test_admin_can_create_and_search_customers(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAsUser($admin);

        $this->postJson('/api/v1/customers', [
            'name' => 'Sara Ali',
            'phone' => '+966 55 123 4567',
            'status' => CustomerStatus::New->value,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Sara Ali')
            ->assertJsonPath('data.whatsapp_number', '966551234567');

        $this->getJson('/api/v1/customers?q=Sara')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Sara Ali');
    }

    public function test_employee_without_delete_permission_cannot_delete_customer(): void
    {
        $employee = $this->makeEmployee();
        $this->actingAsUser($employee);

        $create = $this->postJson('/api/v1/customers', [
            'name' => 'Omar',
            'phone' => '966501111111',
        ])->assertCreated();

        $this->deleteJson('/api/v1/customers/'.$create->json('data.id'))
            ->assertForbidden();
    }
}
