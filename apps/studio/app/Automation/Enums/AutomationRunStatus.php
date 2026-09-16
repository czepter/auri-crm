<?php

namespace App\Automation\Enums;

/**
 * Pending exists only for the instant between App\Automation\Actions\
 * FireAutomationRule claiming the idempotency row (the insert the unique
 * constraint on automation_runs guards) and the action it then takes.
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
     * #438: also a draft the studio discarded.
     */
    case Skipped = 'skipped';

    /** #438: a client mail drafted and waiting for the studio to send or discard it. */
    case AwaitingApproval = 'awaiting_approval';
}
