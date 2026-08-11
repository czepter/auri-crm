<?php

namespace App\Policies;

use App\Crm\Models\FormTemplate;
use App\Enums\Capability;
use App\Models\User;

/**
 * Templates are tenant-wide, not per-project — see ContractTemplatePolicy,
 * the same shape.
 */
class FormTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Capability::ManageFormTemplates);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Capability::ManageFormTemplates);
    }

    public function update(User $user, FormTemplate $template): bool
    {
        return $user->role->allows(Capability::ManageFormTemplates);
    }

    public function delete(User $user, FormTemplate $template): bool
    {
        return $user->role->allows(Capability::ManageFormTemplates);
    }
}
