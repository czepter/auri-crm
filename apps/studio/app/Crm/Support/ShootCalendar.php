<?php

namespace App\Crm\Support;

use App\Crm\Models\Shoot;
use App\Crm\Support\Concerns\FormatsIcs;
use Illuminate\Support\Collection;

/**
 * Builds an RFC 5545 feed of shooting days.
 *
 * Hand-rolled rather than pulling a package in: the subset a shooting calendar
 * needs is one VEVENT shape, and the parts that are actually easy to get wrong
 * — escaping, line folding and the timezone — are the parts a package would
 * not save us from reading anyway. See TimelineCalendar (E-04) for the other
 * shape this codebase needs: one VEVENT per TimelineEntry rather than per shoot.
 */
class ShootCalendar
{
    use FormatsIcs;

    /** @param  Collection<int, Shoot>  $shoots */
    public function render(Collection $shoots, string $name): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Studio//Studio//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($name),
            // Without this most clients refetch on their own slow schedule; an
            // hour is what a changed call time can afford to take.
            'X-PUBLISHED-TTL:PT1H',
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
        ];

        foreach ($shoots as $shoot) {
            $lines = [...$lines, ...$this->event($shoot)];
        }

        $lines[] = 'END:VCALENDAR';

        // CRLF, per the spec. Clients are forgiving about it and the one that
        // is not would be very hard to diagnose from here.
        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /** @return list<string> */
    private function event(Shoot $shoot): array
    {
        $summary = trim(($shoot->label ?? $shoot->kind).' · '.$shoot->project->name);

        $lines = [
            'BEGIN:VEVENT',
            // Stable across regenerations, so a client updates the event it
            // already has rather than showing a second one beside it.
            'UID:'.$this->uid($shoot->public_id),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($summary),
            ...$this->timing($shoot),
        ];

        if ($shoot->venue() !== null) {
            $lines[] = 'LOCATION:'.$this->escape($shoot->venue());
        }

        $crew = $shoot->crew->map(fn ($assignment) => $assignment->user->name.' ('.$assignment->role->label().')');

        if ($crew->isNotEmpty()) {
            $lines[] = 'DESCRIPTION:'.$this->escape('Crew: '.$crew->join(', '));
        }

        $lines[] = 'STATUS:'.($shoot->isCancelled() ? 'CANCELLED' : 'CONFIRMED');
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * A shoot with a call time is a timed event, converted from the venue's
     * clock to UTC so it lands correctly on a phone in another country. One
     * without is an all-day event — which is the honest rendering of "that
     * day, time not settled yet" and avoids inventing a 00:00 start.
     *
     * @return list<string>
     */
    private function timing(Shoot $shoot): array
    {
        $date = $shoot->date->format('Y-m-d');

        if ($shoot->call_time === null) {
            // DTEND is exclusive for all-day events, so it names the next day.
            return [
                'DTSTART;VALUE=DATE:'.$shoot->date->format('Ymd'),
                'DTEND;VALUE=DATE:'.$shoot->date->addDay()->format('Ymd'),
            ];
        }

        $start = $this->atVenue($date, substr((string) $shoot->call_time, 0, 8));

        // A shooting day that runs past midnight would otherwise end before it
        // starts; the end simply moves to the next day. ->copy() matters here:
        // Carbon::addMinutes() mutates in place, and without it $end would be
        // the same object as $start rather than a value derived from it.
        $end = $shoot->end_time === null
            ? $start->copy()->addMinutes($shoot->defaultDurationMinutes())
            : $this->atVenue($date, substr((string) $shoot->end_time, 0, 8));

        if ($end <= $start) {
            $end = $end->addDay();
        }

        return [
            'DTSTART:'.$start->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->utc()->format('Ymd\THis\Z'),
        ];
    }
}
