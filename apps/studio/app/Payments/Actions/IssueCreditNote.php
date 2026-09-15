<?php

namespace App\Payments\Actions;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Models\User;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Payments\Jobs\RenderCreditNoteJob;
use App\Payments\Models\CreditNote;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\StripeClient;

/**
 * #409. The document for money that already arrived: its own sequence, a
 * reference to the invoice it corrects (§ 31 Abs. 5 UStDV), the same tax
 * rate as the supply it reverses.
 *
 * Only for `paid`. An unpaid invoice is retired by CancelInvoice (#408)
 * instead — there is nothing to give back, and issuing a Gutschrift for it
 * would book a refund that never happened.
 *
 * Partial by design, and several per installment: crediting one line of a
 * shoot that was half delivered is the normal case, so the cap is the amount
 * paid less every Gutschrift already written against it.
 *
 * The Stripe refund runs INSIDE the transaction that writes the row, so a
 * refused refund leaves no document claiming money went back. The number is
 * drawn in the same transaction, so a refused refund also burns no number.
 */
class IssueCreditNote
{
    public function __construct(
        private readonly AllocateDocumentNumber $allocate,
        private readonly StudioSettingsResolver $settings,
    ) {}

    public function __invoke(
        PaymentInstallment $installment,
        int $amount,
        string $reason,
        ?User $actor = null,
    ): CreditNote {
        if ($installment->status !== PaymentInstallmentStatus::Paid) {
            throw ValidationException::withMessages(['amount' => __('payments.creditNoteNotPaid')]);
        }

        $remaining = $installment->amount - (int) $installment->creditNotes()->sum('amount');

        if ($amount < 1 || $amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => __('payments.creditNoteTooLarge', ['amount' => $remaining / 100]),
            ]);
        }

        $template = DocumentTemplate::query()
            ->current()
            ->ofType(DocumentTemplateType::CreditNote)
            ->orderBy('id')
            ->first();

        $creditNote = DB::transaction(function () use ($installment, $amount, $reason, $template, $actor) {
            $number = ($this->allocate)(documentType: DocumentType::CreditNote)['number'];

            $creditNote = $installment->creditNotes()->create([
                'number' => $number,
                'issued_at' => now(),
                'amount' => $amount,
                'reason' => $reason,
                'template_id' => $template?->id,
            ]);

            if ($installment->settled_via === SettledVia::Stripe) {
                $creditNote->update(['stripe_refund_id' => $this->refund($installment, $creditNote, $amount)]);
            }

            AuditLogger::record(
                event: 'payments.credit_note.issued',
                actor: $actor,
                context: [
                    'number' => $number,
                    'invoice_number' => $installment->invoice_number,
                    'amount' => $amount,
                    'reason' => $reason,
                ],
                subject: $creditNote->public_id,
                projectId: $installment->plan?->job?->project_id,
            );

            return $creditNote;
        });

        if ($template !== null) {
            RenderCreditNoteJob::dispatch($creditNote);
        }

        return $creditNote;
    }

    /**
     * MarkInstallmentPaid stored the PaymentIntent id as the installment's
     * payment_reference (ConfirmInstallmentCheckoutSession passes it), which
     * is the only handle Stripe needs to refund against.
     *
     * ponytail: the idempotency key is the credit note's own public_id, so a
     * retried request cannot double-refund. A refund that succeeds and whose
     * transaction then fails to commit is still refunded with no row naming
     * it — reconcile against Stripe if that ever shows up.
     */
    private function refund(PaymentInstallment $installment, CreditNote $creditNote, int $amount): string
    {
        $intent = $installment->payment_reference;

        if (! is_string($intent) || $intent === '') {
            throw ValidationException::withMessages(['amount' => __('payments.creditNoteNoStripePayment')]);
        }

        $refund = (new StripeClient($this->settings->activeStripeSecretKey()))->refunds->create([
            'payment_intent' => $intent,
            'amount' => $amount,
        ], [
            'idempotency_key' => $creditNote->public_id,
        ]);

        return $refund->id;
    }
}
