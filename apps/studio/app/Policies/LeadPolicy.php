<?php

namespace App\Policies;

use App\Crm\Models\Lead;
use App\Enums\Capability;
use App\Models\User;

/**
 * The funnel is open to all staff, and the audit log is what makes that safe:
 * AdvanceLeadStage records who moved a lead and when, so "who did this" is a
 * question with an answer rather than one a permission had to prevent.
 *
 * Unfiltered, deliberately. There is no such thing as "the leads I am crewed
 * on" — a lead has no crew until it becomes a booked shoot — so a per-person
 * funnel would be an empty screen rather than a smaller one.
 *
 * The capability stays in the vocabulary even though every role currently
 * holds it, because P6's I-01 issues API tokens against these same values and
 * a token that may read the board is not the same as one that may move it.
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
