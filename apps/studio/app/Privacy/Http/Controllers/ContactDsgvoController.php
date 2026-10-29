<?php

namespace App\Privacy\Http\Controllers;

use App\Crm\Models\Contact;
use App\Crm\Models\Task;
use App\Http\Controllers\Controller;
use App\Privacy\Actions\EraseContactDsgvo;
use App\Privacy\Actions\ExportContactDsgvoData;
use App\Privacy\Models\DsgvoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * X-04: DSGVO subject-access export and deletion, as admin actions rather
 * than a support ticket — the obligation carries a deadline a manual
 * database procedure will miss. Both actions are Admin-only
 * (Capability::ManageDsgvo); see ContactPolicy for why.
 */
class ContactDsgvoController extends Controller
{
    public function export(Contact $contact, ExportContactDsgvoData $export): Response
    {
        $this->authorize('exportDsgvoData', $contact);

        $json = json_encode($export($contact), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $this->close($contact, DsgvoRequest::EXPORT);

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

        $this->close($contact, DsgvoRequest::ERASURE);

        return redirect('/kontakte');
    }

    /**
     * P-05 (#224): the portal-side request this answers, if there was one.
     * Doing the thing is what closes the obligation — a separate "mark as
     * handled" button would be a second step someone forgets, and then the
     * open list lies.
     */
    private function close(Contact $contact, string $kind): void
    {
        $open = DsgvoRequest::query()->open()
            ->where('contact_id', $contact->id)
            ->where('kind', $kind);

        Task::query()->open()
            ->whereIn('id', (clone $open)->select('task_id'))
            ->update(['completed_at' => now(), 'completed_by_id' => auth('staff')->id()]);

        $open->update(['handled_at' => now(), 'handled_by_id' => auth('staff')->id()]);
    }
}
