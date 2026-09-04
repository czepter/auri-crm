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
        return $user->role->allows(Capability::ManageSettings);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Capability::ManageSettings);
    }

    public function update(User $user, ProjectType $type): bool
    {
        return $user->role->allows(Capability::ManageSettings);
    }

    public function delete(User $user, ProjectType $type): bool
    {
        return $user->role->allows(Capability::ManageSettings);
    }
}
