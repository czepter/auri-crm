<?php

namespace App\Automation\Support;

use App\Automation\Enums\AutomationCondition;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Project;
use App\Gallery\Models\GalleryView;
use App\Payments\Enums\PaymentInstallmentStatus;

/**
 * E-15: whether a rule's one condition holds for the project it is about to
 * fire against. Three questions, each over state that already exists — no
 * expression language, and a fourth case arrives as a fourth arm.
 *
 * A rule without a condition always holds: the check is an addition to E-08,
 * never a new hurdle in front of the rules that already ran.
 */
class AutomationConditions
{
    public function holds(?AutomationCondition $condition, ?Project $project): bool
    {
        if ($condition === null) {
            return true;
        }

        if ($project === null) {
            return false;
        }

        return match ($condition) {
            AutomationCondition::PaymentOpen => $project->job?->paymentPlan
                ?->installments()
                ->where('status', '!=', PaymentInstallmentStatus::Paid)
                ->exists() ?? false,

            AutomationCondition::QuestionnaireOpen => $project->tasks()
                ->open()
                ->where('audience', TaskAudience::Client)
                ->exists(),

            // Published and never opened. GalleryView is folded to one row
            // per visitor per day (C-21), so "no row at all" is the only
            // reading of "nobody has looked".
            AutomationCondition::GalleryUnopened => $project->galleries()
                ->whereHas('currentVersion')
                ->whereNotIn('id', GalleryView::query()->select('gallery_id'))
                ->exists(),
        };
    }
}
