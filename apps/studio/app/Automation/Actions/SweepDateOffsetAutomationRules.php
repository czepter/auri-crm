<?php

namespace App\Automation\Actions;

use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Models\AutomationRule;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Gallery\Models\Gallery;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The date-offset half of E-08 — run daily from bootstrap/app.php's own
 * withSchedule(), the same pattern G-01a's reaper and T-07's overdue scan
 * already use. Finds every shoot or quote crossing today ± each active
 * rule's offset and fires it: the "seven days before the shoot" case, and
 * now "seven days before the quote expires".
 *
 * Three anchors, two shapes of query: `per_shoot` matches EVERY shoot in the
 * tenant on its own date and fires against the Shoot — an engagement shoot
 * and a wedding day on the same project each get their own reminder, on
 * their own day. `primary_shoot` matches only shoots flagged is_primary and
 * fires against the Shoot's Project instead — "after the wedding" is a
 * project-level event, not a fact about that one Shoot row. `quote_validity`
 * matches sent quotes by valid_until and also fires against the Project —
 * see AutomationAnchor::QuoteValidity's own docblock for why the Quote row
 * itself is the wrong anchor.
 *
 * A redeployed scheduler re-running today's sweep a second time fires
 * nothing new: this class's job is only to find what's due; App\Automation\
 * Actions\FireAutomationRule's idempotency claim is what actually holds.
 */
class SweepDateOffsetAutomationRules
{
    public function __construct(
        private readonly FireAutomationRule $fire,
        private readonly StudioSettingsResolver $settings,
    ) {}

    public function __invoke(?CarbonImmutable $today = null): int
    {
        // The schedule runs at 00:00 studio time, when UTC east of Greenwich
        // still reads yesterday.
        $today = ($today ?? CarbonImmutable::now($this->settings->timezone()))->startOfDay();
        $fired = 0;

        foreach (AutomationRule::query()->active()->dateOffset()->get() as $rule) {
            $targetDate = $this->targetDateFor($rule, $today);

            foreach ($this->anchorsFor($rule, $targetDate) as $anchor) {
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

    /**
     * What this rule fires against today. Two shapes of query, kept apart by
     * the anchor rather than by a flag: shoots carry the date for the first
     * two, a sent quote's validity carries it for the third.
     *
     * @return iterable<int, Model>
     */
    private function anchorsFor(AutomationRule $rule, CarbonImmutable $targetDate): iterable
    {
        $day = $targetDate->toDateString();

        // E-16: three more dates, each with its own "still open" filter — the
        // same shape uncancelled() gives the shoots below. A paid rate and a
        // deleted gallery are not reminded about.
        if ($rule->anchor === AutomationAnchor::InstallmentDue) {
            return PaymentInstallment::query()
                ->whereIn('status', [PaymentInstallmentStatus::Scheduled, PaymentInstallmentStatus::Invoiced])
                ->whereDate('due_on', $day)
                ->with('plan.job.project')
                ->get();
        }

        if ($rule->anchor === AutomationAnchor::GalleryExpiry) {
            return Gallery::query()
                ->whereDate('expires_at', $day)
                ->with('project')
                ->get();
        }

        if ($rule->anchor === AutomationAnchor::QuestionnaireDue) {
            return Task::query()
                ->open()
                ->where('audience', TaskAudience::Client)
                ->whereDate('due_on', $day)
                ->with('project')
                ->get();
        }

        if ($rule->anchor === AutomationAnchor::QuoteValidity) {
            return Quote::query()
                ->where('status', QuoteStatus::Sent)
                ->whereDate('valid_until', $targetDate->toDateString())
                ->with('project')
                ->get()
                ->map(fn (Quote $quote) => $quote->project);
        }

        $isPrimary = $rule->anchor === AutomationAnchor::PrimaryShoot;

        return Shoot::query()
            ->uncancelled()
            ->whereDate('date', $targetDate->toDateString())
            ->when($isPrimary, fn ($query) => $query->where('is_primary', true))
            ->with('project')
            ->get()
            ->map(fn (Shoot $shoot) => $isPrimary ? $shoot->project : $shoot);
    }
}
