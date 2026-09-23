<?php

namespace App\Quotes\Actions;

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;

/** Wer zahlt, bekommt den Brief; sonst der Hauptkontakt. */
class ResolveQuoteRecipient
{
    public function __invoke(ClientCase $project): ?Contact
    {
        $contacts = $project->contacts()->get();

        return $contacts->firstWhere('pivot.role', CaseContactRole::Payer->value)
            ?? $contacts->firstWhere('pivot.role', CaseContactRole::Primary->value)
            ?? $contacts->first();
    }
}
