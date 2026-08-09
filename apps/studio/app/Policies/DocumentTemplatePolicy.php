<?php

namespace App\Policies;

use App\Documents\Models\DocumentTemplate;
use App\Enums\Capability;
use App\Models\User;

/**
 * Templates are tenant-wide, not per-project — there is no "your" template to
 * check ownership of, only the capability. Gated on ManageContracts for
 * every document type, not only DocumentTemplateType::Contract: that
 * capability already reads as "author contract templates and generate a
 * rendered contract for a project" (its own doc comment), and T-12 does not
 * introduce a narrower gate for the quote/mail templates it adds beside
 * that one. See ProjectPolicy::manageContracts() for the per-project half:
 * generating a contract from one of these.
 */
class DocumentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Capability::ManageContracts);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Capability::ManageContracts);
    }

    public function update(User $user, DocumentTemplate $template): bool
    {
        return $user->role->allows(Capability::ManageContracts);
    }
}
