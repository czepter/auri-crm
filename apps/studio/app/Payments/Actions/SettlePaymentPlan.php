<?php

namespace App\Payments\Actions;

use App\Models\User;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * #418. AdvanceShootStatus::cancel() sets `cancelled_at` on the shoot and
 * nothing else, so the plan kept running: open rates stayed due, the
 * reminder ladder chased a client for a shoot that will not happen, and
 * Stripe Checkout went on taking money.
 *
 * Deliberately not a listener on `shoot.cancelled`. What happens to the
 * Anzahlung comes out of the contract, not out of a status change, so the
 * photographer decides per rate and this action executes that decision.
 *
 * Three outcomes, one per state the rate is in:
 *
 * - nothing issued: the row goes. No document names it and no money touched
 *   it, so there is nothing to keep.
 * - issued and unpaid: Storno kind `void` through CancelInvoice (#408) — the
 *   number stays allocated and the PDF stays readable, which is what GoBD
 *   asks of a retired invoice.
 * - money on it: kept as Ausfallhonorar, or given back through
 *   IssueCreditNote (#409), in full or in part.
 *
 * All of it in one transaction, because the plan's total moves with the rows
 * and the deferred sum trigger (2026_08_09_002500) checks the pair at commit.
 * The total afterwards is what the live rows still hold — a Gutschrift is a
 * document against a rate, never an edit of its amount, so it shows the way
 * it shows everywhere else and not by shrinking the plan.
 *
 * `partial` (#429) reaches the "money on it" branch, where keeping is the
 * only decision it can take: IssueCreditNote is for `paid` and refuses it,
 * since what a part payment gets back is the contract's question and not
 * this dialog's.
 */
class SettlePaymentPlan
{
    public function __construct(
        private readonly CancelInvoice $cancelInvoice,
        private readonly IssueCreditNote $issueCreditNote,
    ) {}

    /**
     * @param  array<string, array{action?: string, amount?: int|null}>  $decisions  keyed by installment public_id
     */
    public function __invoke(PaymentPlan $plan, array $decisions = [], ?User $actor = null): PaymentPlan
    {
        if ($plan->isSettled()) {
            throw ValidationException::withMessages(['plan' => __('payments.settleAlreadySettled')]);
        }

        return DB::transaction(function () use ($plan, $decisions, $actor) {
            $recorded = [];

            foreach ($plan->installments()->orderBy('sort_order')->get() as $installment) {
                if ($installment->isCancelled()) {
                    continue;
                }

                $recorded[$installment->public_id] = (int) $installment->paid_amount > 0
                    ? $this->settlePaid($installment, $decisions[$installment->public_id] ?? [], $actor)
                    : $this->dropOpen($installment, $actor);
            }

            $live = (int) $plan->installments()->whereNull('cancelled_at')->sum('amount');

            $plan->forceFill(['total' => $live, 'settled_at' => now()])->save();

            AuditLogger::record(
                event: 'payment_plan.settled',
                actor: $actor,
                context: ['total' => $live, 'decisions' => $recorded],
                subject: $plan->public_id,
                projectId: $plan->project_id,
            );

            return $plan->fresh();
        });
    }

    /**
     * @param  array{action?: string, amount?: int|null}  $decision
     * @return array<string, mixed>
     */
    private function settlePaid(PaymentInstallment $installment, array $decision, ?User $actor): array
    {
        if (($decision['action'] ?? 'keep') !== 'credit' || $installment->invoice === null) {
            return ['action' => 'keep', 'amount' => (int) $installment->paid_amount];
        }

        $note = ($this->issueCreditNote)(
            $installment->invoice,
            (int) ($decision['amount'] ?? $installment->paid_amount),
            __('payments.settleCreditReason'),
            $actor,
        );

        return ['action' => 'credit', 'amount' => (int) $note->amount, 'number' => $note->number];
    }

    /** @return array<string, mixed> */
    private function dropOpen(PaymentInstallment $installment, ?User $actor): array
    {
        $invoice = $installment->invoice;

        if ($invoice === null) {
            $amount = (int) $installment->amount;
            $installment->delete();

            return ['action' => 'delete', 'amount' => $amount];
        }

        // #480: an invoice that also carries a rate with money on it cannot be
        // retired whole. ponytail: the open rate alone leaves the plan and the
        // invoice keeps its number; a Gutschrift covers the paid part.
        if ($invoice->paidAmount() > 0) {
            $installment->update(['status' => PaymentInstallmentStatus::Cancelled, 'cancelled_at' => now()]);
            $installment->plan->decrement('total', $installment->amount);

            return ['action' => 'void', 'amount' => (int) $installment->amount, 'number' => $invoice->number];
        }

        if (! $invoice->isCancelled()) {
            ($this->cancelInvoice)($invoice, CancellationKind::Void, __('payments.settleVoidReason'), $actor);
        }

        return ['action' => 'void', 'amount' => (int) $installment->amount, 'number' => $invoice->number];
    }
}
