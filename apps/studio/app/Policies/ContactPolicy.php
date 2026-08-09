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
}
