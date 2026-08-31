<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Support\Permissions;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::CUSTOMERS_VIEW);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->company_id === $customer->company_id
            && $user->can(Permissions::CUSTOMERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::CUSTOMERS_CREATE);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->company_id === $customer->company_id
            && $user->can(Permissions::CUSTOMERS_UPDATE);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->company_id === $customer->company_id
            && $user->can(Permissions::CUSTOMERS_DELETE);
    }
}
