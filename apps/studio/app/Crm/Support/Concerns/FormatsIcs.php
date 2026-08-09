<?php

namespace App\Crm\Support\Concerns;

use Illuminate\Support\Carbon;

/**
 * The parts of RFC 5545 that are actually easy to get wrong — escaping a
 * value, folding a content line, and reading a clock time at the venue —
 * shared between ShootCalendar (one VEVENT per shoot) and TimelineCalendar
 * (one VEVENT per TimelineEntry, E-04) so neither reimplements the other's
 * bug fixes.
 */
trait FormatsIcs
{
    /**
     * Every venue in this studio's calendar is in Germany.
     *
     * ponytail: one studio, one zone. When tenants land this becomes a column
     * on the tenant, read here instead of a constant.
     */
    private const VENUE_TIMEZONE = 'Europe/Berlin';

    private function atVenue(string $date, string $time): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$time, self::VENUE_TIMEZONE);
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
