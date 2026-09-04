<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// T-07's overdue scan — the first schedule this codebase registers. Daily is
// enough for a due-date scan (the installment does not stop being overdue
// between runs); M-04/M-05's Stripe signal is meant to arrive as its own,
// separate trigger for the same InstallmentOverdue event, not a reason to
// run this more often.
Schedule::command('payments:detect-overdue-installments')->daily();

// T-11's summary. Daily whatever the rhythm — SendStudioDigests reads each
// person's own last send to decide who is due, so a missed run catches up
// rather than skipping a week. 07:00 so it is read at the desk, not at 00:00.
Schedule::command('notifications:send-digests')->dailyAt('07:00');
