<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\WhatsAppAccount;
use Tests\TestCase;

class ConversationAssignmentTest extends TestCase
{
    public function test_admin_can_assign_conversation_to_employee(): void
    {
        $admin = $this->makeAdmin();
        $employee = $this->makeEmployee(['email' => 'ahmed@example.com']);
        $account = WhatsAppAccount::factory()->create(['company_id' => $this->company->id]);
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $conversation = Conversation::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $account->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => null,
        ]);

        $this->actingAsUser($admin);

        $this->postJson('/api/v1/conversations/'.$conversation->id.'/assign', [
            'user_id' => $employee->id,
        ])->assertOk()
            ->assertJsonPath('data.assigned_user_id', $employee->id);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'assigned_user_id' => $employee->id,
        ]);
    }

    public function test_employee_cannot_view_unassigned_conversations(): void
    {
        $employee = $this->makeEmployee();
        $account = WhatsAppAccount::factory()->create(['company_id' => $this->company->id]);
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $conversation = Conversation::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $account->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => null,
        ]);

        $this->actingAsUser($employee);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/conversations/'.$conversation->id)
            ->assertForbidden();
    }

    public function test_employee_can_view_assigned_conversation(): void
    {
        $employee = $this->makeEmployee();
        $account = WhatsAppAccount::factory()->create(['company_id' => $this->company->id]);
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $conversation = Conversation::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $account->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $employee->id,
        ]);

        $this->actingAsUser($employee);

        $this->getJson('/api/v1/conversations/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id);
    }

    public function test_admin_can_set_and_clear_conversation_link_id(): void
    {
        $admin = $this->makeAdmin();
        $account = WhatsAppAccount::factory()->create(['company_id' => $this->company->id]);
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $conversation = Conversation::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $account->id,
            'customer_id' => $customer->id,
            'link_id' => null,
        ]);

        $this->actingAsUser($admin);

        $this->patchJson('/api/v1/conversations/'.$conversation->id, [
            'link_id' => 'ticket-1001',
        ])->assertOk()
            ->assertJsonPath('data.link_id', 'ticket-1001');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'link_id' => 'ticket-1001',
        ]);

        $this->getJson('/api/v1/conversations?link_id=ticket-1001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id);

        $this->patchJson('/api/v1/conversations/'.$conversation->id, [
            'link_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.link_id', null);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'link_id' => null,
        ]);
    }

    public function test_link_id_must_be_unique_within_company(): void
    {
        $admin = $this->makeAdmin();
        $account = WhatsAppAccount::factory()->create(['company_id' => $this->company->id]);
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        Conversation::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $account->id,
            'customer_id' => $customer->id,
            'link_id' => 'shared-link',
        ]);

        $conversation = Conversation::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $account->id,
            'customer_id' => $customer->id,
            'link_id' => null,
        ]);

        $this->actingAsUser($admin);

        $this->patchJson('/api/v1/conversations/'.$conversation->id, [
            'link_id' => 'shared-link',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['link_id']);
    }
}
