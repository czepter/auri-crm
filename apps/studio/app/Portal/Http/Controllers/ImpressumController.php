<?php

namespace App\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-08: § 5 TMG. Reads the same legal-entity fields RenderContractJob's
 * `studio.*` merge fields already read (T-02, docs/documents.md) — never a
 * second, independently-maintained copy of a studio's own particulars.
 *
 * Public and reachable regardless of auth state or the maintenance closure
 * (see EnsurePortalMaintenanceClosure and routes/portal.php): a portal
 * without an Impressum reachable from any page a client can see is
 * abmahnfähig, closure or not.
 */
class ImpressumController extends Controller
{
    public function __invoke(): Response
    {
        $attrs = StudioSettings::current()->profile();

        return Inertia::render('Impressum', [
            // Same fallback RenderContractJob's `studio.name` merge field
            // uses — a studio that has not filled in its registered name yet
            // still shows the one it trades under.
            'name' => $attrs['legal_name'] ?: $attrs['display_name'],
            'legalForm' => $attrs['legal_form'],
            'street' => $attrs['street'],
            'postcode' => $attrs['postcode'],
            'city' => $attrs['city'],
            'country' => $attrs['country'],
            'taxNumber' => $attrs['tax_number'],
            'vatId' => $attrs['vat_id'],
            'registerNumber' => $attrs['register_number'],
            'registerCourt' => $attrs['register_court'],
            'representative' => $attrs['representative'],
            'website' => $attrs['website'],
        ]);
    }
}
