<?php

namespace App\Policies;

use App\Catalog\Models\Package;
use App\Enums\Capability;
use App\Models\User;

/**
 * The catalog is ordinary, reversible studio work — the same shape as the
 * funnel. AuditLogger records who defined or retired a package, which answers
 * "who did this" without a gate stopping the question being asked, so every
 * role that may write here holds ManagePackages (see StaffRole::capabilities()).
 *
 * Viewing is open to all staff, same as LeadPolicy — Reader sees the catalog
 * and nothing else here.
 */
class PackagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManagePackages->value);
    }

    public function update(User $user, Package $package): bool
    {
        return $user->can(Capability::ManagePackages->value);
    }

    public function delete(User $user, Package $package): bool
    {
        return $user->can(Capability::ManagePackages->value);
    }
}
