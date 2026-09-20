<?php

namespace App\Policies;

use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Models\User;

/**
 * The address book is the client relationship, which is the same authority as
 * the funnel: whoever may pursue a client may hold their details.
 */
class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->allows(Capability::ManageContacts);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->role->allows(Capability::ManageContacts);
    }

    /** Cut this one contact's portal access off entirely (A-04) — never a project. */
    public function revokePortalAccess(User $user, Contact $contact): bool
    {
        return $user->role->allows(Capability::ManagePortalAccess);
    }

    public function sendPortalAccess(User $user, Contact $contact): bool
    {
        return $user->role->allows(Capability::ManagePortalAccess);
    }

    /** X-04: download everything the studio holds about this contact. */
    public function exportDsgvoData(User $user, Contact $contact): bool
    {
        return $user->role->allows(Capability::ManageDsgvo);
    }

    /** X-04: erase this contact, and everything reachable from them, for good. */
    public function eraseDsgvoData(User $user, Contact $contact): bool
    {
        return $user->role->allows(Capability::ManageDsgvo);
    }
}
