<?php

use App\Settings\Support\StudioSettingsResolver;
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
    fn () => app(StudioSettingsResolver::class)->timezone(),
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

// The onboarding nudge — see App\Notifications\Actions\RemindOnboarding for
// the three-condition guard. 09:00 so it lands with the rest of the morning
// mail, not overnight.
Schedule::command('notifications:remind-onboarding')->dailyAt('09:00')->timezone($studioTimezone);
