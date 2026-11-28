<?php

namespace App\Payments\Listeners;

use App\Crm\Events\CaseBooked;
use App\Payments\Actions\CreatePaymentPlan;
use App\Quotes\Enums\QuoteStatus;

/**
 * Only a quote with stages decides amounts on its own; without stages the
 * studio still types the dates and the split.
 */
class CreatePaymentPlanOnBooking
{
    public function __construct(private readonly CreatePaymentPlan $create) {}

    public function handle(CaseBooked $event): void
    {
        $project = $event->project;

        if ($project->paymentPlan()->exists()) {
            return;
        }

        $quote = $project->quotes()->where('status', QuoteStatus::Accepted)->latest('accepted_at')->first();
        $stages = $quote?->paymentStages()->count() ?? 0;

        if ($stages === 0) {
            return;
        }

        $today = today()->toDateString();
        $last = $project->primaryShoot()->value('date') ?? $today;

        ($this->create)($project, $quote->gross(), array_pad([$today], $stages, substr((string) $last, 0, 10)));
    }
}
