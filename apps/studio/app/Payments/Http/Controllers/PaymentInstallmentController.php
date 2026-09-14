<?php

namespace App\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Payments\Actions\CancelInvoice;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\CreditNote;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T-07's staff action: mark one installment paid, with the date, method and
 * reference staff actually have in hand — never a status flipped on its own.
 * Reuses managePaymentPlan (App\Policies\ProjectPolicy) rather than a new
 * capability: marking a payment settled is exactly as sensitive as creating
 * or editing the plan it belongs to.
 */
class PaymentInstallmentController extends Controller
{
    public function markPaid(Request $request, PaymentInstallment $installment, MarkInstallmentPaid $markPaid): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        $validated = $request->validate([
            'paid_on' => ['required', 'date'],
            'method' => ['required', Rule::enum(SettledVia::class)],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $markPaid(
            $installment,
            SettledVia::from($validated['method']),
            $validated['paid_on'],
            $validated['reference'] ?? null,
            auth('staff')->user(),
        );

        return back();
    }

    /**
     * M-06: issue the invoice for one installment. Same authority as marking
     * it paid — both are statements the studio makes about money owed on a
     * job it already booked, and splitting them across two capabilities would
     * invent a role that can settle a payment but not document it.
     */
    public function issueInvoice(PaymentInstallment $installment, IssueInvoice $issue): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        $issue($installment, auth('staff')->user());

        return back();
    }

    /**
     * #408. Same authority again: retiring an invoice is a statement about
     * money owed, exactly as issuing it was.
     */
    public function cancelInvoice(Request $request, PaymentInstallment $installment, CancelInvoice $cancel): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(CancellationKind::class)],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $cancel(
            $installment,
            CancellationKind::from($validated['kind']),
            $validated['reason'],
            auth('staff')->user(),
        );

        return back();
    }

    /**
     * #409. The euro amount arrives as the client typed it and is converted
     * here, the same boundary the payment-plan form already crosses.
     */
    public function issueCreditNote(Request $request, PaymentInstallment $installment, IssueCreditNote $issue): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $issue($installment, $validated['amount'], $validated['reason'], auth('staff')->user());

        return back();
    }

    public function downloadCreditNote(PaymentInstallment $installment, CreditNote $creditNote): StreamedResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        abort_if($creditNote->payment_installment_id !== $installment->id || $creditNote->pdf_path === null, 404);

        return Storage::disk('local')->response($creditNote->pdf_path, "gutschrift-{$creditNote->number}.pdf");
    }

    /**
     * Inline rather than an attachment, for the reason ContractController::
     * download() already gives: the browser's own viewer is what a studio
     * wants when checking its own paperwork, and the client-side `download`
     * attribute still saves it.
     */
    public function downloadInvoice(PaymentInstallment $installment): StreamedResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        abort_if($installment->invoice_path === null, 404);

        return Storage::disk('local')->response(
            $installment->invoice_path,
            "rechnung-{$installment->invoice_number}.pdf",
        );
    }
}
