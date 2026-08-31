<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_admin_can_view_dashboard_stats(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAsUser($admin);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'customers' => ['total', 'new', 'new_today'],
                    'conversations' => ['total', 'open', 'closed', 'unassigned'],
                    'messages' => ['today', 'this_month'],
                    'employees',
                ],
            ]);
    }

    public function test_employee_cannot_view_dashboard(): void
    {
        $employee = $this->makeEmployee();
        $this->actingAsUser($employee);

        $this->getJson('/api/v1/dashboard')->assertForbidden();
    }
}
