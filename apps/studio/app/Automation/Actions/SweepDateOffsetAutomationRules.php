<?php

namespace App\Automation\Actions;

use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Models\AutomationRule;
use App\Crm\Models\Shoot;
use Carbon\CarbonImmutable;

/**
 * The date-offset half of E-08 — run daily from bootstrap/app.php's own
 * withSchedule(), the same pattern G-01a's reaper and T-07's overdue scan
 * already use. Finds every shoot crossing today ± each active rule's offset
 * and fires it: the "seven days before the shoot" case.
 *
 * Two anchors, one query each: `per_shoot` matches EVERY shoot in the
 * tenant on its own date and fires against the Shoot — an engagement shoot
 * and a wedding day on the same project each get their own reminder, on
 * their own day. `primary_shoot` matches only shoots flagged is_primary and
 * fires against the Shoot's Project instead — "after the wedding" is a
 * project-level event, not a fact about that one Shoot row.
 *
 * A redeployed scheduler re-running today's sweep a second time fires
 * nothing new: this class's job is only to find what's due; App\Automation\
 * Actions\FireAutomationRule's idempotency claim is what actually holds.
 */
class SweepDateOffsetAutomationRules
{
    public function __construct(private readonly FireAutomationRule $fire) {}

    public function __invoke(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::now('UTC'))->startOfDay();
        $fired = 0;

        foreach (AutomationRule::query()->active()->dateOffset()->get() as $rule) {
            $targetDate = $this->targetDateFor($rule, $today);
            $isPrimary = $rule->anchor === AutomationAnchor::PrimaryShoot;

            $shoots = Shoot::query()
                ->uncancelled()
                ->whereDate('date', $targetDate->toDateString())
                ->when($isPrimary, fn ($query) => $query->where('is_primary', true))
                ->with('project')
                ->get();

            foreach ($shoots as $shoot) {
                $anchor = $isPrimary ? $shoot->project : $shoot;

                if (($this->fire)($rule, $anchor, $targetDate->toDateString()) !== null) {
                    $fired++;
                }
            }
        }

        return $fired;
    }

    private function targetDateFor(AutomationRule $rule, CarbonImmutable $today): CarbonImmutable
    {
        return $rule->direction === AutomationOffsetDirection::Before
            ? $today->addDays($rule->offset_days)
            : $today->subDays($rule->offset_days);
    }
}
