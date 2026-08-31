<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use App\Support\Permissions;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::TAGS_VIEW) || $user->can(Permissions::TAGS_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::TAGS_MANAGE);
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->company_id === $tag->company_id
            && $user->can(Permissions::TAGS_MANAGE);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $this->update($user, $tag);
    }
}
