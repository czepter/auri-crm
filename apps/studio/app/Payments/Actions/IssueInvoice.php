<?php

namespace App\Payments\Actions;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Models\User;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Jobs\RenderInvoiceJob;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * M-06's "Fällig stellen": one installment becomes one invoice.
 *
 * Issuing does three things that must happen together or not at all — draw a
 * number from the tenant's invoice range, stamp it on the installment, and
 * move the status to Invoiced — so they share one transaction. The render is
 * deliberately outside it: a Gotenberg round trip is not something a number
 * allocation should wait on, and a failed render leaves an issued invoice
 * whose PDF can be re-run, not a number that was silently never used.
 *
 * Idempotent on the installment. A second call returns the same row untouched
 * rather than allocating a second number, which is what makes the button safe
 * to double-click and the action safe to retry. Two *concurrent* first calls
 * are settled by the unique index on (tenant_id, invoice_number) and the one
 * on the counter table — one lands, the other throws, and neither leaves a
 * gap in the sequence.
 *
 * A paid installment can still be invoiced: transfers arrive before paperwork
 * more often than the other way round, and refusing would leave the studio
 * with a settled payment it cannot document.
 *
 * What it does refuse is a studio with neither Steuernummer nor USt-IdNr.:
 * § 14 UStG requires one of them, and both starter invoices render the pair.
 * Neither is asked for during first-run setup — this is where they fall due.
 */
class IssueInvoice
{
    public function __construct(
        private readonly AllocateDocumentNumber $allocate,
        private readonly StudioSettingsResolver $settings,
    ) {}

    public function __invoke(PaymentInstallment $installment, ?User $actor = null): PaymentInstallment
    {
        if ($installment->isInvoiced()) {
            return $installment;
        }

        // Ahead of the transaction: a number drawn for a document that cannot
        // be issued would leave a gap in the tenant's invoice sequence.
        $studio = $this->settings->attributes();

        if (blank($studio['tax_number']) && blank($studio['vat_id'])) {
            throw ValidationException::withMessages(['invoice' => __('payments.taxIdentifierMissing')]);
        }

        $template = DocumentTemplate::query()
            ->current()
            ->ofType(DocumentTemplateType::Invoice)
            ->orderBy('id')
            ->first();

        $installment = DB::transaction(function () use ($installment, $template, $actor) {
            $number = ($this->allocate)(documentType: DocumentType::Invoice)['number'];

            $installment->update([
                'invoice_number' => $number,
                'invoiced_at' => now(),
                'invoice_template_id' => $template?->id,
                // An installment already settled keeps saying so — issuing
                // the paperwork afterwards must not un-pay it.
                'status' => $installment->status === PaymentInstallmentStatus::Paid
                    ? PaymentInstallmentStatus::Paid
                    : PaymentInstallmentStatus::Invoiced,
            ]);

            AuditLogger::record(
                event: 'payments.invoice.issued',
                actor: $actor,
                context: ['number' => $number, 'amount' => $installment->amount],
                subject: $installment->public_id,
                projectId: $installment->plan?->job?->project_id,
            );

            return $installment;
        });

        if ($template !== null) {
            RenderInvoiceJob::dispatch($installment);
        }

        return $installment;
    }
}
