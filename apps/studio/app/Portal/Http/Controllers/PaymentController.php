<?php

namespace App\Portal\Http\Controllers;

use App\Documents\Support\DocumentMergeDataBuilder;
use App\Http\Controllers\Controller;
use App\Payments\Actions\ConfirmInstallmentCheckoutSession;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Support\InvoiceDocument;
use App\Settings\Support\StudioSettingsResolver;
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
 * StudioSettingsResolver::stripeEnabled() alone. Nothing here ever CREATES a
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
        string $project,
        StudioSettingsResolver $settings,
        ConfirmInstallmentCheckoutSession $confirm,
    ): Response {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()
            ->where('projects.public_id', $project)
            ->with('job.paymentPlan.installments.creditNotes')
            ->firstOrFail();

        $plan = $found->job?->paymentPlan;

        $sessionId = $request->query('session_id');
        if ($plan !== null && is_string($sessionId) && $settings->stripeEnabled()) {
            $installment = $plan->installments->firstWhere('stripe_checkout_session_id', $sessionId);

            if ($installment !== null) {
                $confirm($installment, $sessionId);
                $plan = $plan->fresh();
            }
        }

        $bank = $settings->attributes();
        $status = $request->query('status');

        return Inertia::render('Payments/Show', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => ['id' => $found->public_id, 'name' => $found->name],
            'plan' => $plan === null ? null : PaymentPlanData::fromModel($plan->load('installments.creditNotes')),
            'stripeEnabled' => $settings->stripeEnabled(),
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
        string $project,
        string $installment,
        StudioSettingsResolver $settings,
        DocumentMergeDataBuilder $mergeData,
    ): Response {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()
            ->where('projects.public_id', $project)
            ->with('job.paymentPlan.installments.creditNotes')
            ->firstOrFail();

        $row = $found->job?->paymentPlan?->installments->firstWhere('public_id', $installment);

        abort_if($row === null || ! $row->isInvoiced(), 404);

        $document = InvoiceDocument::forInvoice(
            $row,
            $mergeData->fromProject($found, $settings, $row),
            $settings,
        );

        return Inertia::render('Payments/Invoice', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => ['id' => $found->public_id, 'name' => $found->name],
            'invoice' => [
                'id' => $row->public_id,
                'number' => $row->invoice_number,
                'status' => $row->status->value,
                'statusLabel' => $row->status->label(),
                'hasDocument' => $row->invoice_path !== null,
                'metaRows' => $document->metaRows,
                'description' => $document->description,
                // #411: leer auf einer Anzahlungsrechnung — dann bleibt es
                // bei der einen Zeile, wie bisher.
                'lines' => $document->lines,
                'deposits' => $document->deposits,
                'totalGross' => $document->totalGross,
                'summaryRows' => $document->summaryRows(),
                'vatNote' => $document->vatNote,
                'paymentLines' => $document->paymentLines,
                'creditNotes' => $row->creditNotes
                    ->filter(fn ($note) => $note->pdf_path !== null)
                    ->map(fn ($note) => ['id' => $note->public_id, 'number' => $note->number])
                    ->values()
                    ->all(),
            ],
            // #408: a cancelled invoice stays readable and loses the button.
            'canPay' => $settings->stripeEnabled()
                && $row->status !== PaymentInstallmentStatus::Paid
                && $row->status !== PaymentInstallmentStatus::Cancelled,
        ]);
    }

    /**
     * M-06's client-side half. Scoped through visibleProjects() and the
     * plan, never by installment alone: a public_id from another client's
     * project must 404 here exactly as the screen itself does, rather than
     * 403 and confirm the row exists.
     */
    public function invoice(string $project, string $installment): StreamedResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()
            ->where('projects.public_id', $project)
            ->with('job.paymentPlan.installments')
            ->firstOrFail();

        $row = $found->job?->paymentPlan?->installments->firstWhere('public_id', $installment);

        abort_if($row === null || $row->invoice_path === null, 404);

        return Storage::disk('local')->response($row->invoice_path, "rechnung-{$row->invoice_number}.pdf");
    }

    /** #409, scoped exactly as invoice() is — through the project, never by id alone. */
    public function creditNote(string $project, string $installment, string $creditNote): StreamedResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()
            ->where('projects.public_id', $project)
            ->with('job.paymentPlan.installments.creditNotes')
            ->firstOrFail();

        $row = $found->job?->paymentPlan?->installments->firstWhere('public_id', $installment);
        $note = $row?->creditNotes->firstWhere('public_id', $creditNote);

        abort_if($note === null || $note->pdf_path === null, 404);

        return Storage::disk('local')->response($note->pdf_path, "gutschrift-{$note->number}.pdf");
    }
}
