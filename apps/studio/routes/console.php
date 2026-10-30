<?php

use App\Integration\Models\IdempotencyKey;
use App\Models\StudioSettings;
use App\Privacy\Models\StudioExport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Studio time, not container time. The three morning runs below and the
// quiet hours read the same clock, and an hour's drift in winter looks like
// nothing at all rather than like a fault. rescue(), because this file is
// parsed by every artisan command — including the migrate that first creates
// the settings row.
$studioTimezone = rescue(
    fn () => StudioSettings::current()->localTimezone(),
    config('app.timezone'),
    report: false,
);

// T-07's overdue scan — the first schedule this codebase registers. Daily is
// enough for a due-date scan (the installment does not stop being overdue
// between runs); M-04/M-05's Stripe signal is meant to arrive as its own,
// separate trigger for the same InstallmentOverdue event, not a reason to
// run this more often.
Schedule::command('payments:detect-overdue-installments')->dailyAt('06:30')->timezone($studioTimezone);

// T-19 and T-20: what is owed and what has not moved, scanned before the
// morning briefing at 07:00 collects them.
Schedule::command('tasks:remind-due')->dailyAt('06:30')->timezone($studioTimezone);
Schedule::command('leads:remind-stalled')->dailyAt('06:30')->timezone($studioTimezone);

// T-23: one mail for the day, after the scans and before the summary. The
// three are half an hour apart because two mails at the same minute compete
// for the same first glance, not because either is slow.
Schedule::command('notifications:send-morning-briefing')->dailyAt('07:00')->timezone($studioTimezone);

// T-11's summary. Daily whatever the rhythm — SendStudioDigests reads each
// person's own last send to decide who is due, so a missed run catches up
// rather than skipping a week. 07:30 so it is read at the desk, not at
// 00:00, and half an hour clear of T-23's briefing.
Schedule::command('notifications:send-digests')->dailyAt('07:30')->timezone($studioTimezone);

// The onboarding nudge — see App\Staff\Actions\RemindOnboarding for
// the three-condition guard. 09:00 so it lands with the rest of the morning
// mail, not overnight.
Schedule::command('notifications:remind-onboarding')->dailyAt('09:00')->timezone($studioTimezone);

// Die Versionsliste aus release-log. Nachts, weil ein Release von Hand
// geschnitten wird und der Punkt in der Seitenleiste ein paar Stunden später
// früh genug steht. Der Screen liest nur den Cache, den dieser Lauf schreibt.
Schedule::command('releases:fetch')->dailyAt('04:00')->timezone($studioTimezone);

// #395: rows past IdempotencyKey::WINDOW_HOURS no longer replay anything —
// this just reclaims the table. A StudioExport past its expiry (#489) loses its archive.
Schedule::command('model:prune', ['--model' => [IdempotencyKey::class, StudioExport::class]])->daily();

// #490: der Löschtag zählt in Studiozeit.
Schedule::command('studio:delete-due')->dailyAt('03:00')->timezone($studioTimezone);

// #501: nur aktiv, wenn das Studio eine Frist gesetzt hat.
Schedule::command('retention:erase')->dailyAt('03:30')->timezone($studioTimezone);
