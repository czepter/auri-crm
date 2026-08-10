<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\EraseContactDsgvo;
use App\Crm\Actions\ExportContactDsgvoData;
use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * X-04: DSGVO subject-access export and deletion, as admin actions rather
 * than a support ticket — the obligation carries a deadline a manual
 * database procedure will miss. Both actions are Owner-only
 * (Capability::ManageDsgvo); see ContactPolicy for why.
 */
class ContactDsgvoController extends Controller
{
    public function export(Contact $contact, ExportContactDsgvoData $export): Response
    {
        $this->authorize('exportDsgvoData', $contact);

        $json = json_encode($export($contact), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"dsgvo-export-{$contact->public_id}.json\"",
        ]);
    }

    public function erase(Contact $contact, EraseContactDsgvo $erase): RedirectResponse
    {
        $this->authorize('eraseDsgvoData', $contact);

        abort_if($contact->isDsgvoErased(), 409, 'Dieser Kontakt wurde bereits gelöscht.');

        $erase($contact, auth('staff')->user());

        return redirect('/kontakte');
    }
}
