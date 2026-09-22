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
        return $user->can(Capability::ManageContacts->value);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can(Capability::ManageContacts->value);
    }

    /** Cut this one contact's portal access off entirely (A-04) — never a project. */
    public function revokePortalAccess(User $user, Contact $contact): bool
    {
        return $user->can(Capability::ManagePortalAccess->value);
    }

    public function sendPortalAccess(User $user, Contact $contact): bool
    {
        return $user->can(Capability::ManagePortalAccess->value);
    }

    /** X-04: download everything the studio holds about this contact. */
    public function exportDsgvoData(User $user, Contact $contact): bool
    {
        return $user->can(Capability::ManageDsgvo->value);
    }

    /** X-04: erase this contact, and everything reachable from them, for good. */
    public function eraseDsgvoData(User $user, Contact $contact): bool
    {
        return $user->can(Capability::ManageDsgvo->value);
    }
}
