<?php

use App\Models\Conversation;
use App\Support\Permissions;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('company.{companyId}', function ($user, int $companyId) {
    return (int) $user->company_id === $companyId;
});

Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    $conversation = Conversation::query()->find($conversationId);

    if (! $conversation || (int) $conversation->company_id !== (int) $user->company_id) {
        return false;
    }

    return $user->can(Permissions::CONVERSATIONS_VIEW_ALL)
        || (int) $conversation->assigned_user_id === (int) $user->id;
});
