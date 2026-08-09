<?php

namespace App\Policies;

use App\Crm\Models\Lead;
use App\Enums\Capability;
use App\Models\User;

/**
 * The funnel is sales work. A crew role has no view of it at all — not a
 * filtered one, none — because there is no such thing as "the leads I am
 * crewed on": a lead has no crew until it becomes a booked shoot.
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Capability::ManageLeads);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Capability::ManageLeads);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->role->allows(Capability::ManageLeads);
    }
}
