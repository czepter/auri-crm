<?php

namespace App\Policies;

use App\Crm\Models\ProjectType;
use App\Enums\Capability;
use App\Models\User;

/** Tenant-wide configuration, gated like Stage. */
class ProjectTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Capability::ManageSettings->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageSettings->value);
    }

    public function update(User $user, ProjectType $type): bool
    {
        return $user->can(Capability::ManageSettings->value);
    }

    public function delete(User $user, ProjectType $type): bool
    {
        return $user->can(Capability::ManageSettings->value);
    }
}
