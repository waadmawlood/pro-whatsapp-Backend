<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::USERS_VIEW) || $user->can(Permissions::USERS_MANAGE);
    }

    public function view(User $user, User $model): bool
    {
        return $user->company_id === $model->company_id
            && ($user->id === $model->id || $user->can(Permissions::USERS_VIEW) || $user->can(Permissions::USERS_MANAGE));
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::USERS_MANAGE);
    }

    public function update(User $user, User $model): bool
    {
        return $user->company_id === $model->company_id
            && ($user->id === $model->id || $user->can(Permissions::USERS_MANAGE));
    }

    public function delete(User $user, User $model): bool
    {
        return $user->company_id === $model->company_id
            && $user->id !== $model->id
            && $user->can(Permissions::USERS_MANAGE);
    }
}
