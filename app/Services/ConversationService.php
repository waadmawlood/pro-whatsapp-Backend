<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\CustomerStatus;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Notifications\ConversationAssignedNotification;
use App\Notifications\NewConversationNotification;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class ConversationService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function findOrCreateOpen(Customer $customer, WhatsAppAccount $account): Conversation
    {
        $existing = Conversation::withoutGlobalScopes()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('whatsapp_account_id', $account->id)
            ->where('status', ConversationStatus::Open)
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversation = Conversation::withoutGlobalScopes()->create([
            'company_id' => $customer->company_id,
            'whatsapp_account_id' => $account->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $customer->assigned_user_id,
            'status' => ConversationStatus::Open,
            'unread_count' => 0,
        ]);

        $this->notifyNewConversation($conversation);

        ConversationUpdated::dispatch($conversation->load(['customer.tags', 'assignedUser']), 'created');

        return $conversation;
    }

    public function assign(Conversation $conversation, ?User $assignee, User $actor): Conversation
    {
        $oldAssigneeId = $conversation->assigned_user_id;

        DB::transaction(function () use ($conversation, $assignee, $actor, $oldAssigneeId): void {
            $conversation->update([
                'assigned_user_id' => $assignee?->id,
            ]);

            if ($assignee && $conversation->customer) {
                $conversation->customer->update([
                    'assigned_user_id' => $assignee->id,
                    'status' => $conversation->customer->status === CustomerStatus::New
                        ? CustomerStatus::Active
                        : $conversation->customer->status,
                ]);
            }

            $this->auditLogger->log(
                'conversation.assigned',
                $conversation,
                sprintf(
                    '%s assigned conversation #%d to %s',
                    $actor->name,
                    $conversation->id,
                    $assignee?->name ?? 'Unassigned',
                ),
                ['assigned_user_id' => $oldAssigneeId],
                ['assigned_user_id' => $assignee?->id],
            );
        });

        $conversation->refresh()->load(['customer.tags', 'assignedUser', 'whatsappAccount']);

        if ($assignee && $assignee->id !== $actor->id) {
            $assignee->notify(new ConversationAssignedNotification($conversation));
        }

        ConversationUpdated::dispatch($conversation, 'assigned');

        return $conversation;
    }

    public function close(Conversation $conversation, User $actor): Conversation
    {
        $conversation->update([
            'status' => ConversationStatus::Closed,
            'closed_at' => now(),
        ]);

        if ($conversation->customer && $conversation->customer->status !== CustomerStatus::Blocked) {
            $conversation->customer->update(['status' => CustomerStatus::Completed]);
        }

        $this->auditLogger->log('conversation.closed', $conversation, sprintf(
            '%s closed conversation #%d',
            $actor->name,
            $conversation->id,
        ));

        $conversation->load(['customer.tags', 'assignedUser']);
        ConversationUpdated::dispatch($conversation, 'closed');

        return $conversation;
    }

    public function reopen(Conversation $conversation, User $actor): Conversation
    {
        $conversation->update([
            'status' => ConversationStatus::Open,
            'closed_at' => null,
        ]);

        if ($conversation->customer && $conversation->customer->status === CustomerStatus::Completed) {
            $conversation->customer->update(['status' => CustomerStatus::Active]);
        }

        $this->auditLogger->log('conversation.reopened', $conversation, sprintf(
            '%s reopened conversation #%d',
            $actor->name,
            $conversation->id,
        ));

        $conversation->load(['customer.tags', 'assignedUser']);
        ConversationUpdated::dispatch($conversation, 'reopened');

        return $conversation;
    }

    public function markRead(Conversation $conversation): void
    {
        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
            ConversationUpdated::dispatch($conversation->fresh(['customer.tags', 'assignedUser']), 'read');
        }
    }

    public function update(Conversation $conversation, User $actor, array $data): Conversation
    {
        $old = $conversation->only(['link_id']);

        $conversation->update($data);

        $this->auditLogger->log(
            'conversation.updated',
            $conversation,
            sprintf('%s updated conversation #%d', $actor->name, $conversation->id),
            $old,
            $conversation->only(['link_id']),
        );

        $conversation->load(['customer.tags', 'assignedUser', 'whatsappAccount']);
        ConversationUpdated::dispatch($conversation, 'updated');

        return $conversation;
    }

    protected function notifyNewConversation(Conversation $conversation): void
    {
        app()->instance('current_company_id', $conversation->company_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($conversation->company_id);

        $conversation->loadMissing('customer');

        if ($conversation->assigned_user_id) {
            $conversation->assignedUser?->notify(new NewConversationNotification($conversation));

            return;
        }

        User::query()
            ->where('company_id', $conversation->company_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->can(Permissions::CONVERSATIONS_VIEW_ALL))
            ->each(fn (User $user) => $user->notify(new NewConversationNotification($conversation)));
    }
}
