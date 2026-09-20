<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\CalendarDayData;
use App\Crm\Data\CalendarEntryData;
use App\Crm\Data\ShootData;
use App\Crm\Enums\StageAnchor;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Crm\Support\ShootCalendar;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\User;
use App\Settings\Models\StudioClosure;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CalendarController extends Controller
{
    /**
     * C-13 (#249): a month grid, not a chronological index. The operation
     * plans in weeks, and a free Saturday is the most important thing in the
     * picture — which is why an empty cell stays empty rather than saying so.
     *
     * Six week rows always, starting on the Monday on or before the first:
     * a fixed frame is what lets the eye compare two months, and a grid that
     * is five rows in February and six in March is a different shape every
     * time it is opened.
     *
     * The subscription URL is not on this screen any more. It is a credential
     * — anyone holding it reads this person's schedule — and it was the
     * widest block above the working area; it lives under
     * /profil/kalender now.
     */
    public function index(Request $request, CrmVocabulary $vocabulary): InertiaResponse
    {
        $user = auth('staff')->user();
        $month = $this->month($request);
        $view = in_array($request->query('ansicht'), ['monat', 'liste', 'saison'], true) ? $request->query('ansicht') : 'monat';

        $from = $month->startOfMonth()->startOfWeek();
        $to = $from->addWeeks(6)->subDay();

        $shoots = Shoot::query()
            ->with(['project', 'crew.user'])
            // A crew role's calendar is the days they are on, not the studio's
            // whole diary.
            ->whereIn('shoots.project_id', Project::query()->visibleTo($user)->select('projects.id'))
            ->uncancelled()
            ->whereNotNull('date')
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->orderBy('date')
            ->orderBy('call_time')
            ->get();

        $absences = Absence::query()
            ->with('user')
            ->visibleTo($user)
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->get();

        $closures = StudioClosure::query()
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->get();

        $days = [];
        $solo = User::soloStudio();

        for ($day = $from; $day <= $to; $day = $day->addDay()) {
            $date = $day->toDateString();

            $days[] = new CalendarDayData(
                date: $date,
                inMonth: $day->month === $month->month,
                today: $date === now()->toDateString(),
                entries: [
                    ...$shoots->filter(fn (Shoot $shoot) => $shoot->date->toDateString() === $date)
                        ->map(fn (Shoot $shoot) => new CalendarEntryData(
                            kind: $shoot->status?->anchor === StageAnchor::Entry ? 'hold' : 'shoot',
                            time: $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
                            what: $shoot->project->name,
                            who: $solo ? null : ($shoot->crew->map(fn ($assignment) => $assignment->user->name)->join(', ') ?: null),
                            projectId: $shoot->project->public_id,
                        ))->values()->all(),
                    ...$absences->filter(fn (Absence $absence) => $absence->coversDate($date))
                        ->map(fn (Absence $absence) => new CalendarEntryData(
                            kind: 'away',
                            time: null,
                            what: $absence->user->name,
                            who: $absence->category->label(),
                            projectId: null,
                        ))->values()->all(),
                    ...$closures->filter(fn (StudioClosure $closure) => $closure->coversDate($date))
                        ->map(fn (StudioClosure $closure) => new CalendarEntryData(
                            kind: 'away',
                            time: null,
                            what: $closure->label,
                            who: null,
                            projectId: null,
                        ))->values()->all(),
                ],
            );
        }

        return Inertia::render('Kalender', [
            'view' => $view,
            'month' => $month->format('Y-m'),
            'label' => $month->translatedFormat('F Y'),
            'previous' => $month->subMonthNoOverflow()->format('Y-m'),
            'next' => $month->addMonthNoOverflow()->format('Y-m'),
            'days' => CalendarDayData::collect($days),
            // The list view reads the same month, grouped by week rather than
            // laid out in one — a list answers "what is next", a grid answers
            // "how does the month look".
            'shoots' => ShootData::collect($shoots->map(fn (Shoot $shoot) => ShootData::fromShoot($shoot))),
            'projects' => $shoots->mapWithKeys(fn (Shoot $shoot) => [
                $shoot->public_id => ['id' => $shoot->project->public_id, 'name' => $shoot->project->name],
            ]),
            'mine' => $shoots
                ->filter(fn (Shoot $shoot) => $shoot->crew->contains('user_id', $user->id))
                ->pluck('public_id')
                ->values(),
            'season' => $view === 'saison' ? $this->season($user, $vocabulary) : null,
        ]);
    }

    /**
     * #365: every dated shoot and every absence, not one month of them — the
     * season view answers "how full is June next year", which no single
     * month can. Holds stay out: a requested shoot is an inquiry, and a lost
     * one would sit on its Saturday for good.
     *
     * @return array{shoots: list<array<string, string>>, away: list<array<string, string|null>>}
     */
    private function season(User $user, CrmVocabulary $vocabulary): array
    {
        $shoots = Shoot::query()
            ->with('project')
            ->whereIn('shoots.project_id', Project::query()->visibleTo($user)->select('projects.id'))
            ->uncancelled()
            ->whereNotNull('date')
            ->orderBy('date')
            ->get()
            ->filter(fn (Shoot $shoot) => $shoot->status !== null && $shoot->status->anchor !== StageAnchor::Entry);

        return [
            'shoots' => $shoots->map(fn (Shoot $shoot) => [
                'date' => $shoot->date->toDateString(),
                'name' => $shoot->project->name,
                'projectId' => $shoot->project->public_id,
                'status' => $shoot->status->name,
                'phase' => $vocabulary->phase($shoot->status),
            ])->values()->all(),
            'away' => [
                ...Absence::query()->with('user')->visibleTo($user)->get()
                    ->map(fn (Absence $absence) => [
                        'from' => $absence->starts_on->toDateString(),
                        'to' => $absence->ends_on->toDateString(),
                        'what' => $absence->user->name,
                        'who' => $absence->category->label(),
                    ])->all(),
                ...StudioClosure::query()->get()
                    ->map(fn (StudioClosure $closure) => [
                        'from' => $closure->starts_on->toDateString(),
                        'to' => $closure->ends_on->toDateString(),
                        'what' => $closure->label,
                        'who' => null,
                    ])->all(),
            ],
        ];
    }

    private function month(Request $request): CarbonImmutable
    {
        $asked = (string) $request->query('monat', '');

        return preg_match('/^\d{4}-\d{2}$/', $asked) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', $asked.'-01')->startOfDay()
            : CarbonImmutable::now()->startOfMonth();
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

        // Alone in the studio nobody gets assigned, so every shoot is theirs.
        $shoots = Shoot::query()
            ->with(['project', 'crew.user'])
            ->unless(User::soloStudio(), fn ($query) => $query->whereHas('crew', fn ($crew) => $crew->where('user_id', $user->id)))
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
