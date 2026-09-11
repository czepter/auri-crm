<?php

namespace App\Quotes\Actions;

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;

/** Wer zahlt, bekommt den Brief; sonst der Hauptkontakt. */
class ResolveQuoteRecipient
{
    public function __invoke(Project $project): ?Contact
    {
        $contacts = $project->contacts()->get();

        return $contacts->firstWhere('pivot.role', ProjectContactRole::Payer->value)
            ?? $contacts->firstWhere('pivot.role', ProjectContactRole::Primary->value)
            ?? $contacts->first();
    }
}
