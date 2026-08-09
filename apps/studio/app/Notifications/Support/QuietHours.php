<?php

namespace App\Notifications\Support;

use App\Settings\Support\StudioSettingsResolver;
use Carbon\CarbonImmutable;

/**
 * T-11: "Ihre Galerie ist fertig" at 03:00 is a support call — whether right
 * now falls inside the tenant's own quiet window, and when that window next
 * opens. Reuses T-13's tenant timezone (StudioSettingsResolver::timezone())
 * rather than a second per-notification notion of "when" — the studio has
 * exactly one clock, the same one Allgemein's pane already sets.
 *
 * The window may cross midnight (e.g. 22:00–07:00); a start equal to or
 * after the end is read as crossing rather than as a same-day window that
 * never opens.
 */
class QuietHours
{
    public function __construct(private readonly StudioSettingsResolver $settings) {}

    public function isQuiet(): bool
    {
        if (! $this->settings->quietHoursEnabled()) {
            return false;
        }

        [$now, $start, $end] = $this->boundsForToday();

        if ($start === null || $end === null) {
            return false;
        }

        return $start->lte($end)
            ? $now->betweenIncluded($start, $end)
            : ($now->gte($start) || $now->lte($end));
    }

    /** The instant the window opens — now, if it is not currently quiet. */
    public function opensAt(): CarbonImmutable
    {
        [$now, $start, $end] = $this->boundsForToday();

        if ($start === null || $end === null || ! $this->isQuiet()) {
            return $now;
        }

        // Wrapped past midnight and still before the start: today's end is
        // still ahead. Wrapped and already past the start: tonight's window
        // closes on tomorrow's end.
        return $start->gt($end) && $now->gte($start) ? $end->addDay() : $end;
    }

    /** @return array{0: CarbonImmutable, 1: ?CarbonImmutable, 2: ?CarbonImmutable} */
    private function boundsForToday(): array
    {
        $now = CarbonImmutable::now($this->settings->timezone());
        $start = $this->settings->quietHoursStart();
        $end = $this->settings->quietHoursEnd();

        return [
            $now,
            $start !== null ? $now->startOfDay()->setTimeFromTimeString($start) : null,
            $end !== null ? $now->startOfDay()->setTimeFromTimeString($end) : null,
        ];
    }
}
