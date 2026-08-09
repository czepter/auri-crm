<?php

namespace App\Automation\Enums;

/**
 * Pending exists only for the instant between App\Automation\Actions\
 * FireAutomationRule claiming the idempotency row (the insert the unique
 * constraint on automation_runs guards) and the send it then attempts —
 * every row this app ever reads back is Sent or Failed.
 */
enum AutomationRunStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
