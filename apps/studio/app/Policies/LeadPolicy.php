<?php

namespace App\Policies;

use App\Crm\Models\Lead;
use App\Enums\Capability;
use App\Models\User;

/**
 * The funnel is open to all staff to see, and the audit log is what makes
 * moving it safe: AdvanceLeadStage records who moved a lead and when, so
 * "who did this" is a question with an answer rather than one a permission
 * had to prevent.
 *
 * Unfiltered, deliberately. There is no such thing as "the leads I am crewed
 * on" — a lead has no crew until it becomes a booked shoot — so a per-person
 * funnel would be an empty screen rather than a smaller one.
 *
 * ManageLeads gates writing to it. Reader holds no capability at all, so it
 * sees the board and nothing else here — the same shape as ManageContracts
 * and the rest: viewing is one question, changing is another.
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageLeads->value);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->can(Capability::ManageLeads->value);
    }
}
