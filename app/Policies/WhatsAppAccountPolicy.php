<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Support\Permissions;

class WhatsAppAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::WHATSAPP_MANAGE);
    }

    public function view(User $user, WhatsAppAccount $account): bool
    {
        return $user->company_id === $account->company_id
            && $user->can(Permissions::WHATSAPP_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::WHATSAPP_MANAGE);
    }

    public function update(User $user, WhatsAppAccount $account): bool
    {
        return $this->view($user, $account);
    }

    public function delete(User $user, WhatsAppAccount $account): bool
    {
        return $this->view($user, $account);
    }
}
