<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Models\Shoot;
use App\Crm\Models\TimelineEntry;
use App\Crm\Support\TimelineCalendar;
use App\Http\Controllers\Controller;
use App\Support\GotenbergClient;
use App\Support\LiveUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * A shoot's day-of schedule (E-04): who is where, when. CRUD sits behind the
 * same `manageShoots` capability ShootController's own mutations do — editing
 * the Ablauf is shoot management, not a separate concern — and both exports
 * regenerate from the live rows on every request rather than serving a stored
 * artifact, the same way ShootCalendar's feed never caches a rendered ICS.
 */
class TimelineController extends Controller
{
    public function store(Request $request, Shoot $shoot): RedirectResponse
    {
        $this->authorize('update', $shoot);

        $validated = $request->validate([
            'time' => ['required', 'date_format:H:i'],
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'is_transit' => ['nullable', 'boolean'],
        ]);

        $shoot->timeline()->create($validated);

        LiveUpdate::publish();

        return back();
    }

    public function destroy(TimelineEntry $entry): RedirectResponse
    {
        $this->authorize('update', $entry->shoot);

        $entry->delete();

        LiveUpdate::publish();

        return back();
    }

    /**
     * Print handout of the Ablauf. Reuses M-01's Gotenberg pipeline — the one
     * place in this app that turns HTML into a PDF — rather than a second
     * library for the same job.
     */
    public function pdf(Shoot $shoot, GotenbergClient $gotenberg): SymfonyResponse
    {
        $this->authorize('view', $shoot);

        abort_if($shoot->date === null, 404);

        $shoot->loadMissing(['project', 'timeline']);

        $pdf = $gotenberg->htmlToPdf(view('pdf.shoot-timeline', ['shoot' => $shoot])->render());

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"ablauf-{$shoot->public_id}.pdf\"",
        ]);
    }

    /** One VEVENT per timeline entry — see TimelineCalendar's own note. */
    public function ics(Shoot $shoot, TimelineCalendar $calendar): SymfonyResponse
    {
        $this->authorize('view', $shoot);

        abort_if($shoot->date === null, 404);

        $shoot->loadMissing(['project', 'timeline']);

        return response($calendar->render($shoot), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"ablauf-{$shoot->public_id}.ics\"",
        ]);
    }
}
