<?php

namespace App\Crm\Actions;

use App\Crm\Models\Contact;

class UpdateContact
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(Contact $contact, array $attributes): Contact
    {
        $contact->update($attributes);

        return $contact;
    }
}
