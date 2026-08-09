<?php

namespace App\Policies;

use App\Catalog\Models\Package;
use App\Enums\Capability;
use App\Models\User;

/**
 * The catalog is ordinary, reversible studio work — the same shape as the
 * funnel. AuditLogger records who defined or retired a package, which answers
 * "who did this" without a gate stopping the question being asked, so every
 * role holds the capability (see StaffRole::capabilities()).
 *
 * The capability stays in the vocabulary anyway, for the reason LeadPolicy
 * gives: P6's I-01 issues API tokens against these same values, and a token
 * that may read the catalog is not the same as one that may change it.
 */
class PackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Capability::ManagePackages);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Capability::ManagePackages);
    }

    public function update(User $user, Package $package): bool
    {
        return $user->role->allows(Capability::ManagePackages);
    }
}
