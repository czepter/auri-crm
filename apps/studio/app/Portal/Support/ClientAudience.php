<?php

namespace App\Portal\Support;

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use Illuminate\Support\Collection;

/**
 * Who on the client side hears about something on a project — the studio's
 * StudioAudience, from the other end.
 *
 * The primary contact where one is named, every contact with portal access
 * otherwise. Access is the project's visibility and the contact's own
 * revocation together: a reminder pointing at a portal the reader cannot
 * open is worse than none.
 */
class ClientAudience
{
    /** @return Collection<int, Contact> */
    public function forCase(?ClientCase $project): Collection
    {
        if ($project === null || ! $project->portal_visible) {
            return collect();
        }

        $reachable = $project->contacts()
            ->whereNull('portal_access_revoked_at')
            ->whereNotNull('email')
            ->get();

        $primary = $reachable->first(
            fn (Contact $contact) => $contact->pivot->role === CaseContactRole::Primary->value
        );

        return $primary !== null ? collect([$primary]) : $reachable;
    }
}
