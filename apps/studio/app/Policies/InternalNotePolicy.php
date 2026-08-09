<?php

namespace App\Policies;

use App\Crm\Models\InternalNote;
use App\Models\User;

/**
 * No author check on update or delete: the audience is the whole studio and
 * audit_logs records who changed what, which is the ruling C-07 shipped with.
 * What a role may reach is a different question, and it is the one here.
 */
class InternalNotePolicy
{
    public function __construct(private readonly ProjectPolicy $projects) {}

    public function view(User $user, InternalNote $note): bool
    {
        return $this->projects->view($user, $note->project);
    }

    public function update(User $user, InternalNote $note): bool
    {
        return $this->projects->manageNotes($user, $note->project);
    }
}
