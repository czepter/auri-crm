<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\Absence;
use App\Models\User;

/**
 * The person owns the row. A second shooter records and edits their own
 * absence without ManageStaff — that capability is "invite and configure the
 * studio", a different question from "I am away next week" — and ManageCrew,
 * the same capability that already sees and edits everyone's CrewAssignment,
 * sees and edits everyone's Absence here too, so the owner does not become
 * the calendar secretary for a studio with more than one hand.
 */
class AbsencePolicy
{
    public function view(User $user, Absence $absence): bool
    {
        return $this->owns($user, $absence) || $user->role->allows(Capability::ManageCrew);
    }

    public function update(User $user, Absence $absence): bool
    {
        return $this->view($user, $absence);
    }

    public function delete(User $user, Absence $absence): bool
    {
        return $this->view($user, $absence);
    }

    private function owns(User $user, Absence $absence): bool
    {
        return $absence->user_id === $user->id;
    }
}
