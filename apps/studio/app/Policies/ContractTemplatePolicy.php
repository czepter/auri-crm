<?php

namespace App\Policies;

use App\Contracts\Models\ContractTemplate;
use App\Enums\Capability;
use App\Models\User;

/**
 * Templates are tenant-wide, not per-project — there is no "your" template to
 * check ownership of, only the capability. See ProjectPolicy::manageContracts()
 * for the per-project half: generating a contract from one of these.
 */
class ContractTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Capability::ManageContracts);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Capability::ManageContracts);
    }

    public function update(User $user, ContractTemplate $template): bool
    {
        return $user->role->allows(Capability::ManageContracts);
    }
}
