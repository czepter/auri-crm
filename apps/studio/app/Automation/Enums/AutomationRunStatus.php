<?php

namespace App\Automation\Enums;

/**
 * Pending exists only for the instant between App\Automation\Actions\
 * FireAutomationRule claiming the idempotency row (the insert the unique
 * constraint on automation_runs guards) and the send it then attempts —
 * every row this app ever reads back is Sent, Skipped or Failed.
 */
enum AutomationRunStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    /**
     * E-15: the rule ran and its condition did not hold. Its own state
     * rather than a silent absence, so "why did nothing arrive" has an
     * answer in the rule's history instead of looking like an outage.
     */
    case Skipped = 'skipped';
}
