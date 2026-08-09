<?php

namespace App\Gallery\Listeners;

use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Events\InstallmentPaid;

/**
 * Keeps Gallery::$balance_settled current whenever an installment clears.
 * Nothing reads the flag yet — delivery/download flows are future stories —
 * so this listener stays deliberately inert beyond the write itself: no
 * gating decision, no notification, no UI. Wired to the real InstallmentPaid
 * event M-05 already delivers, so those future stories find the flag already
 * correct rather than needing to backfill it.
 */
class RecomputeGalleryBalanceSettled
{
    public function handle(InstallmentPaid $event): void
    {
        $project = $event->installment->plan->job->project;

        Gallery::where('project_id', $project->id)
            ->update(['balance_settled' => $this->isSettled($project)]);
    }

    /** True once every installment on the project's payment plan is paid — false with no plan at all. */
    private function isSettled(Project $project): bool
    {
        $plan = $project->job?->paymentPlan;

        if ($plan === null) {
            return false;
        }

        return ! $plan->installments()
            ->where('status', '!=', PaymentInstallmentStatus::Paid)
            ->exists();
    }
}
