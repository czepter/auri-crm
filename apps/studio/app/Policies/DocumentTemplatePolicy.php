<?php

namespace App\Policies;

use App\Documents\Models\DocumentTemplate;
use App\Enums\Capability;
use App\Models\User;

/**
 * Templates are tenant-wide, not per-project — there is no "your" template to
 * check ownership of, only the capability. Gated on ManageContracts for
 * every document type; the capability kept its name when contracts went so
 * issued API tokens keep their scope.
 */
class DocumentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Capability::ManageContracts->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageContracts->value);
    }

    public function update(User $user, DocumentTemplate $template): bool
    {
        return $user->can(Capability::ManageContracts->value);
    }
}
