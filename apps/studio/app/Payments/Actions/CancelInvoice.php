<?php

namespace App\Payments\Actions;

use App\Models\User;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentInstallment;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * #408. An issued invoice with the wrong amount or the wrong recipient is
 * retired, never rewritten: the number stays allocated, the PDF stays
 * readable, and the correction is a new document (GoBD Rz. 93,
 * § 31 Abs. 5 UStDV).
 *
 * Money already booked on it is refused rather than handled. That is a
 * Gutschrift (#409) or an Ausfallhonorar, and which of the two it is comes
 * out of the contract — not out of this button.
 *
 * #480: every rate the invoice carries goes with it, in one transaction.
 * Korrektur hands each amount back to its plan as a fresh scheduled rate, so
 * the total stands; Wegfall lowers the total instead. The sum trigger
 * (2026_09_14_000200) ignores the cancelled rows. An invoice without a plan
 * has nothing to hand back to.
 */
class CancelInvoice
{
    public function __invoke(
        Invoice $invoice,
        CancellationKind $kind,
        string $reason,
        ?User $actor = null,
    ): Invoice {
        if ($invoice->isCancelled()) {
            throw ValidationException::withMessages(['kind' => __('payments.cancelNotInvoiced')]);
        }

        if ($invoice->paidAmount() > 0) {
            throw ValidationException::withMessages(['kind' => __('payments.cancelPaidRefused')]);
        }

        return DB::transaction(function () use ($invoice, $kind, $reason, $actor) {
            $installments = PaymentInstallment::query()
                ->where('invoice_id', $invoice->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($installments->sum('paid_amount') > 0) {
                throw ValidationException::withMessages(['kind' => __('payments.cancelPaidRefused')]);
            }

            $invoice->update([
                'cancelled_at' => now(),
                'cancellation_kind' => $kind,
                'cancellation_reason' => $reason,
            ]);

            foreach ($installments as $installment) {
                $this->retire($installment, $kind);
            }

            AuditLogger::record(
                event: 'payments.invoice.cancelled',
                actor: $actor,
                context: ['number' => $invoice->number, 'kind' => $kind->value, 'reason' => $reason],
                subject: $invoice->public_id,
                projectId: $invoice->case_id,
            );

            return $invoice;
        });
    }

    private function retire(PaymentInstallment $installment, CancellationKind $kind): void
    {
        $label = $installment->plan === null ? null : PaymentPlanData::labelForInstallment($installment);

        $installment->update([
            'status' => PaymentInstallmentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        if ($installment->plan === null) {
            return;
        }

        if ($kind === CancellationKind::Correction) {
            $installment->plan->installments()->create([
                'amount' => $installment->amount,
                'due_on' => $installment->due_on,
                'title' => $label,
                'status' => PaymentInstallmentStatus::Scheduled,
                'sort_order' => (int) $installment->plan->installments()->max('sort_order') + 1,
            ]);
        } else {
            $installment->plan->decrement('total', $installment->amount);
        }
    }
}
