<?php

namespace App\Payments\Actions;

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Models\StudioSettings;
use App\Models\User;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Jobs\RenderInvoiceJob;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Support\InvoiceFigures;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * M-06's "Fällig stellen", since #480 for any number of rates of one plan at
 * once, and for an invoice without a project at all.
 *
 * Drawing the number and writing the invoice share one transaction. The
 * render is deliberately outside it: a Gotenberg round trip is not something
 * a number allocation should wait on, and a failed render leaves an issued
 * invoice whose PDF can be re-run, not a number that was silently never used.
 *
 * Idempotent where it can be: rates that already sit together on one invoice
 * return that invoice rather than drawing a second number, which is what makes
 * the button safe to double-click. Two concurrent first calls are settled by
 * the unique index on (tenant_id, number) and the one on the counter table.
 *
 * A paid rate can still be invoiced: transfers arrive before paperwork more
 * often than the other way round.
 *
 * What it does refuse is a studio with neither Steuernummer nor USt-IdNr.:
 * § 14 UStG requires one of them. Neither is asked for during first-run
 * setup — this is where they fall due.
 */
class IssueInvoice
{
    public function __construct(
        private readonly AllocateDocumentNumber $allocate,
    ) {}

    /**
     * @param  iterable<PaymentInstallment>|PaymentInstallment  $installments
     * @param  array{intro?: ?string, outro?: ?string}  $texts
     */
    public function __invoke(iterable|PaymentInstallment $installments, ?User $actor = null, array $texts = []): Invoice
    {
        $installments = Collection::wrap($installments)->values();

        if ($installments->isEmpty() || $installments->pluck('payment_plan_id')->unique()->count() > 1 || $installments->contains('payment_plan_id', null)) {
            throw ValidationException::withMessages(['installments' => __('payments.invoiceOnePlan')]);
        }

        if ($installments->contains(fn (PaymentInstallment $row) => $row->isCancelled())) {
            throw ValidationException::withMessages(['installments' => __('payments.cancelledNotIssuable')]);
        }

        $issued = $installments->pluck('invoice_id')->filter()->unique();

        if ($issued->count() === 1 && ! $installments->contains('invoice_id', null)) {
            return Invoice::findOrFail($issued->first());
        }

        if ($issued->isNotEmpty()) {
            throw ValidationException::withMessages(['installments' => __('payments.alreadyInvoiced')]);
        }

        $project = $installments->first()->plan->case;

        return $this->positions(
            $project->primaryContact(),
            $project,
            $installments->map(fn (PaymentInstallment $row) => ['title' => PaymentPlanData::labelForInstallment($row), 'installment' => $row])->all(),
            null,
            $texts,
            $actor,
        );
    }

    /**
     * #480: positions written with the quote's builder. A position carrying an
     * `installment` bills that rate of the project's plan at its own gross;
     * every other position is charged on top, as one more receivable due on
     * $dueOn. An Endrechnung prints the quote's whole Leistung instead of its
     * positions (#411), so it takes no free positions beside it.
     *
     * @param  list<array{title: string, description?: ?string, quantity?: int, unit_price?: ?int, discount_type?: ?string, discount_value?: ?int, vat_rate?: ?int, installment?: ?PaymentInstallment}>  $positions
     * @param  array{intro?: ?string, outro?: ?string, discount_type?: ?string, discount_value?: ?int}  $attributes
     */
    public function positions(?Contact $contact, ?ClientCase $project, array $positions, ?string $dueOn, array $attributes = [], ?User $actor = null): Invoice
    {
        $positions = collect($positions)->values();
        [$picked, $free] = $positions->partition(fn (array $position) => ($position['installment'] ?? null) !== null);

        foreach ($picked as $position) {
            $row = $position['installment'];

            if ($row->plan?->case_id !== $project?->id) {
                throw ValidationException::withMessages(['items' => __('payments.invoiceOnePlan')]);
            }

            if ($row->isCancelled()) {
                throw ValidationException::withMessages(['items' => __('payments.cancelledNotIssuable')]);
            }

            if ($row->isInvoiced()) {
                throw ValidationException::withMessages(['items' => __('payments.alreadyInvoiced')]);
            }
        }

        if ($free->isNotEmpty() && $picked->contains(fn (array $position) => $position['installment']->isFinal())) {
            throw ValidationException::withMessages(['items' => __('payments.finalTakesNoPositions')]);
        }

        if ($free->isNotEmpty() && blank($dueOn)) {
            throw ValidationException::withMessages(['due_on' => __('validation.required', ['attribute' => 'Fällig am'])]);
        }

        return $this->issue(
            ['contact_id' => $contact?->id, 'case_id' => $project?->id, ...$attributes],
            function (Invoice $invoice) use ($positions, $dueOn) {
                $defaultRate = StudioSettings::current()->vat_mode === 'small_business' ? 0 : StudioSettings::current()->effectiveVatRate();

                foreach ($positions as $index => $position) {
                    $row = $position['installment'] ?? null;

                    $invoice->items()->create([
                        'title' => $position['title'],
                        'description' => $position['description'] ?? null,
                        'quantity' => $row === null ? ($position['quantity'] ?? 1) : 1,
                        'unit_price' => $row === null
                            ? ($position['unit_price'] ?? 0)
                            : InvoiceFigures::fromGross((int) $row->amount, (int) ($position['vat_rate'] ?? $defaultRate))->net,
                        'discount_type' => $row === null ? ($position['discount_type'] ?? null) : null,
                        'discount_value' => $row === null ? ($position['discount_value'] ?? null) : null,
                        'vat_rate' => $position['vat_rate'] ?? null,
                        'payment_installment_id' => $row?->id,
                        'sort_order' => $index,
                    ]);

                    // An installment already settled keeps saying so —
                    // issuing the paperwork afterwards must not un-pay it.
                    $row?->update([
                        'invoice_id' => $invoice->id,
                        'status' => in_array($row->status, [PaymentInstallmentStatus::Paid, PaymentInstallmentStatus::Partial], true)
                            ? $row->status
                            : PaymentInstallmentStatus::Invoiced,
                    ]);
                }

                $free = $invoice->items()->whereNull('payment_installment_id')->get();

                if ($free->isNotEmpty()) {
                    PaymentInstallment::create([
                        'invoice_id' => $invoice->id,
                        'amount' => InvoiceFigures::forPositions($free, $invoice->discount_type, $invoice->discount_value)->gross,
                        'due_on' => $dueOn,
                        'status' => PaymentInstallmentStatus::Invoiced,
                    ]);
                }
            },
            $actor,
        );
    }

    /** @param  array<string, mixed>  $attributes */
    private function issue(array $attributes, \Closure $attach, ?User $actor): Invoice
    {
        // Ahead of the transaction: a number drawn for a document that cannot
        // be issued would leave a gap in the tenant's invoice sequence.
        $studio = StudioSettings::current()->profile();

        if (blank($studio['tax_number']) && blank($studio['vat_id'])) {
            throw ValidationException::withMessages(['invoice' => __('payments.taxIdentifierMissing')]);
        }

        $template = DocumentTemplate::query()
            ->current()
            ->ofType(DocumentTemplateType::Invoice)
            ->orderBy('id')
            ->first();

        $invoice = DB::transaction(function () use ($attributes, $attach, $template, $actor) {
            $invoice = Invoice::create([
                ...$attributes,
                'number' => ($this->allocate)(documentType: DocumentType::Invoice)['number'],
                'issued_at' => now(),
                'template_id' => $template?->id,
            ]);

            $attach($invoice);

            AuditLogger::record(
                event: 'payments.invoice.issued',
                actor: $actor,
                context: ['number' => $invoice->number, 'amount' => $invoice->load('installments')->amount()],
                subject: $invoice->public_id,
                projectId: $invoice->case_id,
            );

            return $invoice;
        });

        if ($template !== null) {
            RenderInvoiceJob::dispatch($invoice);
        }

        return $invoice;
    }
}
