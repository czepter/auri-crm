<?php

namespace App\Portal\Http\Controllers;

use App\Crm\Models\ClientCase;
use App\Documents\Actions\RecordDocumentView;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Payments\Actions\ConfirmInstallmentCheckoutSession;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Support\InvoiceDocument;
use App\Payments\Support\InvoiceMergeData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T-07's portal payment screen — no mockup was drawn for this one ("Mockup —"
 * in the issue header), so this matches the closest existing portal screen
 * instead: App\Portal\Http\Controllers\ContractController's own shape
 * (contact/showSwitcher/project header, a 404 for a project the contact
 * cannot see, never a 403).
 *
 * "Überweisen" always renders — the studio's bank details (T-02) and the
 * plan's own reference (T-04's numbering, PaymentPlanData::$reference) —
 * regardless of whether Stripe is on. "Jetzt zahlen" is gated on
 * StudioSettings' payments_stripe_enabled alone. Nothing here ever CREATES a
 * Stripe object — that is App\Payments\Actions\CreateInstallmentCheckoutSession's
 * job, called only from the client's own click on PaymentCheckoutController.
 *
 * M-04: this is also where the client lands back from Stripe's hosted
 * Checkout page. A `session_id` query param (Stripe's own {CHECKOUT_SESSION_ID}
 * substitution, see PaymentCheckoutController) is what triggers the
 * provisional confirm step — see App\Payments\Actions\
 * ConfirmInstallmentCheckoutSession for what is provisional about it and what
 * M-05's webhook is expected to own instead. An ordinary visit with no
 * `session_id` never touches Stripe at all.
 */
class PaymentController extends Controller
{
    public function __invoke(
        Request $request,
        string $case,
        ConfirmInstallmentCheckoutSession $confirm,
    ): Response|RedirectResponse {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()
            ->where('cases.public_id', $case)
            ->with('paymentPlan.installments.invoice.creditNotes', 'latestQuote')
            ->firstOrFail();

        $plan = $found->paymentPlan;

        if ($plan === null) {
            return Inertia::render('Projects/Missing', [
                'showSwitcher' => $contact->visibleProjects()->count() > 1,
                'project' => ['id' => $found->public_id, 'name' => $found->name],
                'kind' => 'payments',
            ]);
        }

        $sessionId = $request->query('session_id');
        if (is_string($sessionId) && (bool) StudioSettings::current()->payments_stripe_enabled) {
            $installment = $plan->installments->firstWhere('stripe_checkout_session_id', $sessionId);

            if ($installment !== null) {
                $confirm($installment, $sessionId);
                $plan = $plan->fresh();
            }
        }

        $bank = StudioSettings::current()->profile();
        $status = $request->query('status');

        return Inertia::render('Payments/Show', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => ['id' => $found->public_id, 'name' => $found->name],
            'plan' => PaymentPlanData::forCase($found),
            'quote' => $found->latestQuote === null ? null : ['number' => $found->latestQuote->number],
            'stripeEnabled' => (bool) StudioSettings::current()->payments_stripe_enabled,
            'paymentStatus' => in_array($status, ['success', 'cancelled'], true) ? $status : null,
            'bankDetails' => [
                'accountHolder' => $bank['bank_account_holder'],
                'iban' => $bank['bank_iban'],
                'bic' => $bank['bank_bic'],
            ],
        ]);
    }

    /**
     * #419: the invoice as a page rather than only as a PDF — built like
     * App\Portal\Http\Controllers\QuoteController's own Show, and rendering
     * the same InvoiceDocument the five PDF layouts render, so the screen and
     * the file cannot disagree about a figure.
     */
    public function show(
        Request $request,
        RecordDocumentView $record,
        string $case,
        string $invoice,
        InvoiceMergeData $mergeData,
    ): Response {
        $contact = auth('portal')->user();
        [$found, $row] = $this->find($case, $invoice);

        $record($request, $row, 'portal');

        $document = InvoiceDocument::forInvoice($row, $mergeData->forInvoice($row));
        $open = $row->installments->first(fn (PaymentInstallment $one) => in_array($one->status, PaymentInstallmentStatus::open(), true));

        return Inertia::render('Payments/Invoice', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => ['id' => $found->public_id, 'name' => $found->name],
            'invoice' => [
                'id' => $row->public_id,
                'number' => $row->number,
                'status' => $row->status(),
                'statusLabel' => __("payments.invoiceStatus.{$row->status()}"),
                'hasDocument' => $row->pdf_path !== null,
                ...$document->page(),
                'creditNotes' => $row->creditNotes
                    ->filter(fn ($note) => $note->pdf_path !== null)
                    ->map(fn ($note) => ['id' => $note->public_id, 'number' => $note->number])
                    ->values()
                    ->all(),
            ],
            // #408: a cancelled invoice stays readable and loses the button.
            // #480: the button charges the earliest rate still open on it.
            'payInstallmentId' => (bool) StudioSettings::current()->payments_stripe_enabled && ! $row->isCancelled() ? $open?->public_id : null,
        ]);
    }

    /**
     * M-06's client-side half. Scoped through visibleProjects() and the
     * project, never by invoice alone: a public_id from another client's
     * project must 404 here exactly as the screen itself does, rather than
     * 403 and confirm the row exists.
     */
    public function invoice(string $case, string $invoice): StreamedResponse
    {
        [, $row] = $this->find($case, $invoice);

        abort_if($row->pdf_path === null, 404);

        return Storage::disk('local')->response($row->pdf_path, "rechnung-{$row->number}.pdf");
    }

    /** #409, scoped exactly as invoice() is — through the project, never by id alone. */
    public function creditNote(string $case, string $invoice, string $creditNote): StreamedResponse
    {
        [, $row] = $this->find($case, $invoice);
        $note = $row->creditNotes->firstWhere('public_id', $creditNote);

        abort_if($note === null || $note->pdf_path === null, 404);

        return Storage::disk('local')->response($note->pdf_path, "gutschrift-{$note->number}.pdf");
    }

    /** @return array{0: ClientCase, 1: Invoice} */
    private function find(string $case, string $invoice): array
    {
        $found = auth('portal')->user()->visibleProjects()
            ->where('cases.public_id', $case)
            ->firstOrFail();

        $row = Invoice::query()
            ->where('case_id', $found->id)
            ->where('public_id', $invoice)
            ->with(['installments', 'creditNotes', 'contact', 'case', 'items'])
            ->firstOrFail();

        return [$found, $row];
    }
}
