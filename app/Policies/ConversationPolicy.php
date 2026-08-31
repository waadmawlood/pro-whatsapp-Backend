<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Support\Permissions;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::CONVERSATIONS_VIEW);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        if ($user->company_id !== $conversation->company_id) {
            return false;
        }

        if ($user->can(Permissions::CONVERSATIONS_VIEW_ALL)) {
            return true;
        }

        return $conversation->assigned_user_id === $user->id;
    }

    public function assign(User $user, Conversation $conversation): bool
    {
        return $user->company_id === $conversation->company_id
            && $user->can(Permissions::CONVERSATIONS_ASSIGN);
    }

    public function close(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && $user->can(Permissions::CONVERSATIONS_CLOSE);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $user->company_id === $conversation->company_id
            && $user->can(Permissions::CONVERSATIONS_DELETE);
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && $user->can(Permissions::MESSAGES_SEND);
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
