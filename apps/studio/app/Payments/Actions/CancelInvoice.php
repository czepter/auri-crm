<?php

namespace App\Payments\Actions;

use App\Models\User;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\PaymentInstallmentStatus;
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
 * `paid` is refused rather than handled. Money that already arrived is a
 * Gutschrift (#409) or an Ausfallhonorar, and which of the two it is comes
 * out of the contract — not out of this button.
 *
 * One transaction for both kinds, because both move the plan and the row
 * together: Korrektur hands the amount back as a fresh scheduled row, so
 * the total stands; Wegfall lowers the total instead. The sum trigger
 * (2026_09_14_000200) ignores the cancelled row and checks the result of
 * whichever half ran.
 */
class CancelInvoice
{
    public function __invoke(
        PaymentInstallment $installment,
        CancellationKind $kind,
        string $reason,
        ?User $actor = null,
    ): PaymentInstallment {
        if ($installment->status === PaymentInstallmentStatus::Paid) {
            throw ValidationException::withMessages(['kind' => __('payments.cancelPaidRefused')]);
        }

        if (! $installment->isInvoiced() || $installment->isCancelled()) {
            throw ValidationException::withMessages(['kind' => __('payments.cancelNotInvoiced')]);
        }

        return DB::transaction(function () use ($installment, $kind, $reason, $actor) {
            $label = PaymentPlanData::labelForInstallment($installment);

            $installment->update([
                'status' => PaymentInstallmentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_kind' => $kind,
                'cancellation_reason' => $reason,
            ]);

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

            AuditLogger::record(
                event: 'payments.invoice.cancelled',
                actor: $actor,
                context: [
                    'number' => $installment->invoice_number,
                    'kind' => $kind->value,
                    'reason' => $reason,
                ],
                subject: $installment->public_id,
                projectId: $installment->plan?->job?->project_id,
            );

            return $installment;
        });
    }
}
