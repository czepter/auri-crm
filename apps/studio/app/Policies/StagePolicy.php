<?php

namespace App\Policies;

use App\Crm\Models\Stage;
use App\Enums\Capability;
use App\Models\User;

/**
 * The funnel is tenant-wide configuration, so ManageSettings rather than
 * ManageLeads: moving one lead is daily work, renaming the stage every lead
 * passes through is not.
 */
class StagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Capability::ManageSettings);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Capability::ManageSettings);
    }

    public function update(User $user, Stage $stage): bool
    {
        return $user->role->allows(Capability::ManageSettings);
    }

    public function delete(User $user, Stage $stage): bool
    {
        return $user->role->allows(Capability::ManageSettings);
    }
}
