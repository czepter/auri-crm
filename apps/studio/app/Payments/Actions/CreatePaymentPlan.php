<?php

namespace App\Payments\Actions;

use App\Crm\Models\ClientCase;
use App\Payments\Models\PaymentPlan;
use App\Payments\Support\PaymentInstallmentSplitter;
use App\Payments\Support\PaymentStageSplitter;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of payment_plans. A plan is created against what the
 * client agreed to, and that is the accepted quote — accepted in the portal
 * with the AGB ticked, or a yes recorded by staff. A signed contract used to
 * be the precondition; it shut out every studio that signs on paper or
 * signs nothing at all.
 *
 * One transaction for plan and installments together, same reasoning as
 * BookProject pairing the booking with its shoots: the deferred constraint
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
    public function __invoke(ClientCase $project, int $total, array $dueDates): PaymentPlan
    {
        abort_if($project->paymentPlan()->exists(), 422, 'This project already has a payment plan.');

        $quote = $this->acceptedQuoteFor($project);

        abort_if($quote === null, 422, 'A payment plan requires an accepted quote.');

        // Q-11: where the accepted quote carries stages, they decide the
        // amounts and their wording; the dates stay this action's own, since
        // a stage has a text and no date. An even split otherwise — that is
        // every plan that exists today.
        $stages = $quote->paymentStages;
        $rows = $stages->isEmpty()
            ? collect(PaymentInstallmentSplitter::evenly($total, count($dueDates)))
                ->map(fn (int $amount) => ['amount' => $amount, 'title' => null])
                ->all()
            : collect(PaymentStageSplitter::split($total, $stages))
                ->map(fn (array $row) => ['amount' => $row['amount'], 'title' => $row['label']])
                ->all();

        return DB::transaction(function () use ($project, $total, $dueDates, $rows) {
            $plan = PaymentPlan::create(['case_id' => $project->id, 'total' => $total]);

            foreach (array_values($rows) as $index => $row) {
                $plan->installments()->create([
                    'amount' => $row['amount'],
                    // due_on is NOT NULL, and a stage carries no date of its
                    // own — so a stage past the last date supplied falls back
                    // to it rather than inventing one. ponytail: derive the
                    // dates from the project's own shoots when a story asks the
                    // booking screen to stop typing them.
                    'due_on' => array_values($dueDates)[$index] ?? end($dueDates),
                    'sort_order' => $index,
                    'title' => $row['title'],
                ]);
            }

            AuditLogger::record(
                event: 'payment_plan.created',
                actor: auth('staff')->user(),
                context: ['total' => $total, 'installments' => count($rows)],
                subject: $plan->public_id,
                projectId: $project->id,
            );

            return $plan;
        });
    }

    /**
     * Read off the quote rather than copied onto the project: the quote is the
     * document the client agreed to, and it is the only place that answers
     * what was agreed.
     */
    private function acceptedQuoteFor(ClientCase $project): ?Quote
    {
        return Quote::query()
            ->where('case_id', $project->id)
            ->where('status', QuoteStatus::Accepted)
            ->latest('accepted_at')
            ->first();
    }
}
