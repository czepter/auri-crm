<?php

namespace App\Crm\Support;

use App\Crm\Models\Shoot;
use App\Crm\Models\TimelineEntry;
use App\Crm\Support\Concerns\FormatsIcs;
use Illuminate\Support\Carbon;

/**
 * Builds an RFC 5545 feed of one shoot's day-of schedule (E-04) — a VEVENT
 * per TimelineEntry rather than the single all-day block ShootCalendar gives
 * the shoot itself, because "the crew's calendars" want the day to unfold:
 * Getting Ready, then First Look, then the ceremony, each its own event with
 * its own reminder. Same hand-rolled shape as ShootCalendar and for the same
 * reason — see that class's own note.
 */
class TimelineCalendar
{
    use FormatsIcs;

    /** $shoot must have `timeline` and `project` loaded, and carry a date. */
    public function render(Shoot $shoot): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Studio//Studio//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($shoot->title()),
        ];

        $entries = $shoot->timeline;
        $date = $shoot->date->format('Y-m-d');
        $minutes = $shoot->defaultDurationMinutes();

        foreach ($entries as $index => $entry) {
            $lines = [...$lines, ...$this->event($entry, $date, $entries->get($index + 1), $minutes)];
        }

        $lines[] = 'END:VCALENDAR';

        // CRLF, per the spec. Clients are forgiving about it and the one that
        // is not would be very hard to diagnose from here.
        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /** @return list<string> */
    private function event(TimelineEntry $entry, string $date, ?TimelineEntry $next, int $minutes): array
    {
        [$start, $end] = $this->timing($entry, $date, $next, $minutes);

        $lines = [
            'BEGIN:VEVENT',
            // Stable across regenerations, so a client updates the event it
            // already has rather than showing a second one beside it.
            'UID:'.$this->uid($entry->public_id),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($entry->title),
            'DTSTART:'.$start->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->utc()->format('Ymd\THis\Z'),
        ];

        if ($entry->location !== null) {
            $lines[] = 'LOCATION:'.$this->escape($entry->location);
        }

        if ($entry->note !== null) {
            $lines[] = 'DESCRIPTION:'.$this->escape($entry->note);
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * An entry runs until the next one starts — the honest reading of a
     * day-of schedule, where "First Look" simply lasts until "Trauung"
     * begins. The last entry has no next one to borrow an end from, so it
     * takes the default length of its project's type instead, the same one
     * ShootCalendar falls back to for a shoot with no end_time.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function timing(TimelineEntry $entry, string $date, ?TimelineEntry $next, int $minutes): array
    {
        $start = $this->atVenue($date, substr((string) $entry->time, 0, 8));

        $end = $next !== null
            ? $this->atVenue($date, substr((string) $next->time, 0, 8))
            : $start->copy()->addMinutes($minutes);

        // A schedule that runs past midnight would otherwise end before it
        // starts; the end simply moves to the next day.
        if ($end <= $start) {
            $end = $end->addDay();
        }

        return [$start, $end];
    }
}
