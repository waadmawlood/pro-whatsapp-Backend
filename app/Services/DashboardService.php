<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\CustomerStatus;
use App\Enums\MessageDirection;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(Company $company): array
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        $conversations = Conversation::query()->where('company_id', $company->id);
        $messages = Message::query()->where('company_id', $company->id);
        $customers = Customer::query()->where('company_id', $company->id);

        return [
            'customers' => [
                'total' => (clone $customers)->count(),
                'new' => (clone $customers)->where('status', CustomerStatus::New)->count(),
                'new_today' => (clone $customers)->whereDate('created_at', $today)->count(),
            ],
            'conversations' => [
                'total' => (clone $conversations)->count(),
                'open' => (clone $conversations)->where('status', ConversationStatus::Open)->count(),
                'closed' => (clone $conversations)->where('status', ConversationStatus::Closed)->count(),
                'unassigned' => (clone $conversations)
                    ->where('status', ConversationStatus::Open)
                    ->whereNull('assigned_user_id')
                    ->count(),
            ],
            'messages' => [
                'today' => (clone $messages)->whereDate('created_at', $today)->count(),
                'this_month' => (clone $messages)->where('created_at', '>=', $monthStart)->count(),
            ],
            'employees' => $this->employeePerformance($company, $monthStart),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function employeePerformance(Company $company, Carbon $since): array
    {
        $users = User::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $users->map(function (User $user) use ($since) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'is_online' => $user->isOnline(),
                'conversations' => Conversation::query()
                    ->where('assigned_user_id', $user->id)
                    ->count(),
                'open_conversations' => Conversation::query()
                    ->where('assigned_user_id', $user->id)
                    ->where('status', ConversationStatus::Open)
                    ->count(),
                'messages_sent' => Message::query()
                    ->where('user_id', $user->id)
                    ->where('direction', MessageDirection::Outbound)
                    ->where('created_at', '>=', $since)
                    ->count(),
                'customers' => Customer::query()
                    ->where('assigned_user_id', $user->id)
                    ->count(),
            ];
        })->values()->all();
    }
}
