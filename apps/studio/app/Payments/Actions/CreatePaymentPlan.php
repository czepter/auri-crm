<?php

namespace App\Payments\Actions;

use App\Crm\Models\Job;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
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
 * T-07's own scope: a plan created here already has its signed contract, so
 * unlike Q-06's "allocate only at send" this is never a draft that could
 * burn a number for nothing — the Invoice-type document number allocated
 * inside the same transaction becomes `reference`, the Verwendungszweck the
 * portal payment screen prints beside the studio's IBAN.
 */
class CreatePaymentPlan
{
    public function __construct(private readonly AllocateDocumentNumber $allocate) {}

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
            $reference = ($this->allocate)(documentType: DocumentType::Invoice)['number'];

            $plan = PaymentPlan::create(['job_id' => $job->id, 'total' => $total, 'reference' => $reference]);

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
            );

            return $plan;
        });
    }
}
