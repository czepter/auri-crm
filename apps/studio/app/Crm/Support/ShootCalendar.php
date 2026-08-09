<?php

namespace App\Crm\Support;

use App\Crm\Models\Shoot;
use Illuminate\Support\Collection;

/**
 * Builds an RFC 5545 feed of shooting days.
 *
 * Hand-rolled rather than pulling a package in: the subset a shooting calendar
 * needs is one VEVENT shape, and the parts that are actually easy to get wrong
 * — escaping, line folding and the timezone — are the parts a package would
 * not save us from reading anyway.
 */
class ShootCalendar
{
    /**
     * A shoot's call time is a clock reading at the venue, and every venue is
     * in Germany.
     *
     * ponytail: one studio, one zone. When tenants land this becomes a column
     * on the tenant, read here instead of a constant.
     */
    private const VENUE_TIMEZONE = 'Europe/Berlin';

    /** @param  Collection<int, Shoot>  $shoots */
    public function render(Collection $shoots, string $name): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Atelier Nordlicht//Studio//DE',
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
        $summary = trim(($shoot->label ?? $shoot->kind->label()).' · '.$shoot->project->name);

        $lines = [
            'BEGIN:VEVENT',
            // Stable across regenerations, so a client updates the event it
            // already has rather than showing a second one beside it.
            'UID:'.$shoot->public_id.'@studio.nordlicht',
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
        // starts; the end simply moves to the next day.
        $end = $shoot->end_time === null
            ? $start->addHours(2)
            : $this->atVenue($date, substr((string) $shoot->end_time, 0, 8));

        if ($end <= $start) {
            $end = $end->addDay();
        }

        return [
            'DTSTART:'.$start->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->utc()->format('Ymd\THis\Z'),
        ];
    }

    private function atVenue(string $date, string $time): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date.' '.$time,
            self::VENUE_TIMEZONE,
        );
    }

    /** Backslash, semicolon, comma and newline carry meaning in a property value. */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n'],
            $value,
        );
    }

    /**
     * Content lines are limited to 75 octets. Folding splits on octets rather
     * than characters, so a German umlaut cannot be cut in half.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $parts = [];
        $remaining = $line;

        while (strlen($remaining) > 75) {
            $take = 75;

            // Never split inside a UTF-8 sequence: continuation bytes are
            // 10xxxxxx, so walk back to the start of the character.
            while ($take > 1 && (ord($remaining[$take]) & 0xC0) === 0x80) {
                $take--;
            }

            $parts[] = substr($remaining, 0, $take);
            // A leading space marks the continuation, and costs one of the 75.
            $remaining = ' '.substr($remaining, $take);
        }

        $parts[] = $remaining;

        return implode("\r\n", $parts);
    }
}
