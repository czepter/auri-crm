<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Models\ClientCase;
use App\Crm\Models\InternalNote;
use App\Http\Controllers\Controller;
use App\Support\LiveUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Studio-only, and only ever registered in routes/admin.php. Nothing here may
 * ever be lifted into a portal surface — InternalNoteTest holds that line.
 *
 * No ownership rule on update or delete: the audience is the whole studio, and
 * audit_logs already records who changed what.
 */
class InternalNoteController extends Controller
{
    public function store(Request $request, ClientCase $case): RedirectResponse
    {
        $this->authorize('manageNotes', $case);

        $case->notes()->create([
            'author_id' => auth('staff')->id(),
            ...$this->validated($request),
        ]);

        LiveUpdate::publish();

        return back();
    }

    public function update(Request $request, InternalNote $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $note->update($this->validated($request));

        LiveUpdate::publish();

        return back();
    }

    public function destroy(InternalNote $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $note->delete();

        LiveUpdate::publish();

        return back();
    }

    /**
     * @return array{body: string, occurred_at?: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            // A call from last week, written up today. Never the future: the
            // thread is a record, not a calendar.
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);
    }
}
