<?php

namespace App\Payments\Actions;

use App\Crm\Models\Job;
use App\Payments\Models\PaymentPlan;
use App\Payments\Support\PaymentInstallmentSplitter;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of payment_plans. M-02's own issue text: "a plan is
 * created against a signed contract" — so this refuses a Job whose Project
 * has no Contract with a signature, the same abort_unless shape
 * App\Contracts\Actions\SignContract uses for its own precondition.
 *
 * One transaction for plan and installments together, same reasoning as
 * BookProject pairing Job creation with its shoots: the deferred constraint
 * trigger on payment_installments (2026_08_09_002500) only checks at COMMIT,
 * so the plan row and the installments that must sum to its total have to
 * land in the same transaction or an intermediate state gets checked and
 * rejected on its own.
 *
 * T-07 allocated an Invoice-type document number here, at plan creation, as
 * the Verwendungszweck the portal prints beside the studio's IBAN. **M-06
 * stopped it**, and the reason is worth keeping: once invoices exist, every
 * number this drew was a number no invoice ever carried, and an invoice
 * sequence with holes in it is exactly what a Betriebsprüfung asks about.
 * The Verwendungszweck is now the issued invoice's own number — which is
 * what a German client is used to typing into a transfer form anyway.
 *
 * `payment_plans.reference` stays, holding the numbers already handed out
 * before this changed. Nothing writes it any more.
 */
class CreatePaymentPlan
{
    public function __invoke(Job $job, int $total, array $dueDates): PaymentPlan
    {
        abort_if($job->paymentPlan()->exists(), 422, 'This job already has a payment plan.');
        abort_unless(
            $job->project->contracts()->whereHas('signature')->exists(),
            422,
            'A payment plan requires a signed contract.'
        );

        $amounts = PaymentInstallmentSplitter::evenly($total, count($dueDates));

        return DB::transaction(function () use ($job, $total, $dueDates, $amounts) {
            $plan = PaymentPlan::create(['job_id' => $job->id, 'total' => $total]);

            foreach (array_values($dueDates) as $index => $dueOn) {
                $plan->installments()->create([
                    'amount' => $amounts[$index],
                    'due_on' => $dueOn,
                    'sort_order' => $index,
                ]);
            }

            AuditLogger::record(
                event: 'payment_plan.created',
                actor: auth('staff')->user(),
                context: ['job' => $job->public_id, 'total' => $total, 'installments' => count($dueDates)],
                subject: $plan->public_id,
                projectId: $job->project_id,
            );

            return $plan;
        });
    }
}
