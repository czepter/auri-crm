<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\ShootData;
use App\Crm\Models\Shoot;
use App\Crm\Support\ShootCalendar;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CalendarController extends Controller
{
    public function index(): InertiaResponse
    {
        $user = auth('staff')->user();

        $shoots = Shoot::query()
            ->with(['project', 'crew.user'])
            ->uncancelled()
            ->whereNotNull('date')
            ->whereDate('date', '>=', now()->startOfDay())
            ->orderBy('date')
            ->orderBy('call_time')
            ->get();

        return Inertia::render('Kalender', [
            'shoots' => ShootData::collect($shoots->map(ShootData::fromShoot(...))),
            // Keyed by shoot, because the calendar lists occasions and each
            // one has to name the project it belongs to and link to it.
            'projects' => $shoots->mapWithKeys(fn (Shoot $shoot) => [
                $shoot->public_id => ['id' => $shoot->project->public_id, 'name' => $shoot->project->name],
            ]),
            'mine' => $shoots
                ->filter(fn (Shoot $shoot) => $shoot->crew->contains('user_id', $user->id))
                ->pluck('public_id')
                ->values(),
            // Built here rather than shared, so the secret only ever reaches
            // the one screen that has a reason to show it.
            'feedUrl' => url("/kalender/{$user->calendarToken()}/shootings.ics"),
        ]);
    }

    /** Revocation: every subscribed device stops receiving the feed at once. */
    public function regenerate(): RedirectResponse
    {
        auth('staff')->user()->regenerateCalendarToken();

        return back();
    }

    /**
     * The subscription feed. Public by necessity — a calendar app cannot log
     * in — and authenticated by the token in the path alone, which is why it
     * carries nothing but one person's own assigned shoots.
     */
    public function feed(string $token, ShootCalendar $calendar): SymfonyResponse
    {
        $user = User::query()->where('calendar_token', $token)->first();

        abort_if($user === null, 404);

        $shoots = Shoot::query()
            ->join('crew_assignments', 'crew_assignments.shoot_id', '=', 'shoots.id')
            ->select('shoots.*')
            ->with(['project', 'crew.user'])
            ->where('crew_assignments.user_id', $user->id)
            ->whereNotNull('date')
            ->orderBy('date')
            ->get();

        return new Response(
            $calendar->render($shoots, "Shootings · {$user->name}"),
            200,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="shootings.ics"',
                // The URL is a credential. Nothing between here and the phone
                // may keep a copy of the schedule it returns.
                'Cache-Control' => 'no-store, private',
                'X-Robots-Tag' => 'noindex, nofollow',
            ],
        );
    }
}
