<?php

namespace App\Crm\Listeners;

use App\Contracts\Events\ContractSigned;
use App\Crm\Actions\BookProject;
use App\Crm\Enums\BookedVia;
use App\Crm\Models\Project;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Events\InstallmentPaid;
use App\Payments\Models\PaymentInstallment;
use App\Support\AuditLogger;

/**
 * Converts a project the moment BOTH a signed contract and a paid deposit are
 * true — whichever of ContractSigned / InstallmentPaid arrives second is the
 * one that notices and books it. Routes through BookProject, the same action
 * P-05a's manual button calls: this listener decides WHEN to ask, never how
 * to book.
 *
 * "Deposit paid" names the plan's first installment by sort_order (M-03's own
 * ordering — see PaymentPlan::installments()), never "whichever installment
 * happened to clear first".
 *
 * BookProject's own idempotency guard (already-booked is a no-op) is the
 * whole safety net for a race against the manual button — this listener adds
 * no guard of its own. What it does add is its own audit row on that no-op:
 * the button and this listener are two different actors, and a loss in the
 * race still deserves a record distinguishable from whichever row won.
 *
 * A schema note, not a TODO: payment_plans.job_id is required (M-03), and
 * CreatePaymentPlan additionally refuses a plan without a signed contract
 * (T-07) — so by the time any installment can be marked paid, a Job already
 * exists and its contract is already signed. In today's schema this listener
 * can therefore only ever observe the no-op branch; it is written to the
 * general two-trigger spec anyway, so it converges correctly the day a
 * deposit can be collected ahead of booking. See the PR description.
 */
class BookProjectOnContractAndDeposit
{
    public function __construct(private readonly BookProject $book) {}

    public function handleContractSigned(ContractSigned $event): void
    {
        $project = $event->contract->project;

        if ($this->depositPaid($project)) {
            $this->attempt($project);
        }
    }

    public function handleInstallmentPaid(InstallmentPaid $event): void
    {
        $installment = $event->installment;

        if (! $this->isFirst($installment)) {
            return;
        }

        $project = $installment->plan->job->project;

        if ($this->contractSigned($project)) {
            $this->attempt($project);
        }
    }

    private function depositPaid(Project $project): bool
    {
        $plan = $project->job?->paymentPlan;

        if ($plan === null) {
            return false;
        }

        $first = $plan->installments()->first();

        return $first !== null && $first->status === PaymentInstallmentStatus::Paid;
    }

    private function contractSigned(Project $project): bool
    {
        return $project->contracts()->whereHas('signature')->exists();
    }

    private function isFirst(PaymentInstallment $installment): bool
    {
        $first = $installment->plan->installments()->first();

        return $first !== null && $first->is($installment);
    }

    private function attempt(Project $project): void
    {
        $job = ($this->book)($project, null, BookedVia::Contract);

        if ($job === null) {
            AuditLogger::record(
                event: 'project.booking_attempted',
                context: ['via' => BookedVia::Contract->value, 'result' => 'already_booked'],
                subject: $project->public_id,
                projectId: $project->id,
            );
        }
    }
}
