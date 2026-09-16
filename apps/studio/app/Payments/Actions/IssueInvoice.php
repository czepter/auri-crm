<?php

namespace App\Payments\Actions;

use App\Crm\Models\Contact;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Models\User;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Jobs\RenderInvoiceJob;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Support\StudioSettingsResolver;
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
        private readonly StudioSettingsResolver $settings,
    ) {}

    /** @param  iterable<PaymentInstallment>|PaymentInstallment  $installments */
    public function __invoke(iterable|PaymentInstallment $installments, ?User $actor = null): Invoice
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

        $project = $installments->first()->plan->job->project;

        return $this->issue(
            ['contact_id' => $project->primaryContact()?->id, 'project_id' => $project->id],
            function (Invoice $invoice) use ($installments) {
                foreach ($installments as $row) {
                    // An installment already settled keeps saying so —
                    // issuing the paperwork afterwards must not un-pay it.
                    $row->update([
                        'invoice_id' => $invoice->id,
                        'status' => in_array($row->status, [PaymentInstallmentStatus::Paid, PaymentInstallmentStatus::Partial], true)
                            ? $row->status
                            : PaymentInstallmentStatus::Invoiced,
                    ]);
                }
            },
            $actor,
        );
    }

    /**
     * #480: an invoice without a project. The items are the document; the one
     * installment beside them, outside any plan, is what payments book against.
     *
     * @param  list<array{description: string, quantity: int, unit_price: int, vat_rate: int}>  $items
     */
    public function free(Contact $contact, array $items, string $dueOn, ?User $actor = null): Invoice
    {
        return $this->issue(
            ['contact_id' => $contact->id, 'project_id' => null],
            function (Invoice $invoice) use ($items, $dueOn) {
                foreach (array_values($items) as $index => $item) {
                    $invoice->items()->create([...$item, 'sort_order' => $index]);
                }

                PaymentInstallment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->items()->get()->sum(fn ($item) => $item->total()),
                    'due_on' => $dueOn,
                    'status' => PaymentInstallmentStatus::Invoiced,
                ]);
            },
            $actor,
        );
    }

    /** @param  array{contact_id: ?int, project_id: ?int}  $attributes */
    private function issue(array $attributes, \Closure $attach, ?User $actor): Invoice
    {
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
                projectId: $invoice->project_id,
            );

            return $invoice;
        });

        if ($template !== null) {
            RenderInvoiceJob::dispatch($invoice);
        }

        return $invoice;
    }
}
