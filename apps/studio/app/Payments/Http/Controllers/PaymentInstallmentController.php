<?php

namespace App\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * T-07's staff action: mark one installment paid, with the date, method and
 * reference staff actually have in hand — never a status flipped on its own.
 * Reuses managePaymentPlan (App\Policies\ClientCasePolicy) rather than a new
 * capability: marking a payment settled is exactly as sensitive as creating
 * or editing the plan it belongs to.
 */
class PaymentInstallmentController extends Controller
{
    public function markPaid(Request $request, PaymentInstallment $installment, MarkInstallmentPaid $markPaid): RedirectResponse
    {
        InvoiceController::authorizeProject($installment->case());

        $validated = $request->validate([
            'paid_on' => ['required', 'date'],
            'method' => ['required', Rule::enum(SettledVia::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'integer', 'min:1'],
        ]);

        $markPaid(
            $installment,
            SettledVia::from($validated['method']),
            $validated['paid_on'],
            $validated['reference'] ?? null,
            auth('staff')->user(),
            $validated['amount'] ?? null,
        );

        return back();
    }

    /**
     * M-06: one rate on its own invoice, straight into the compose screen.
     * Sending stays a second decision (#415).
     */
    public function issueInvoice(PaymentInstallment $installment, IssueInvoice $issue): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->case);

        $invoice = $issue($installment, auth('staff')->user());

        return redirect("/rechnungen/{$invoice->public_id}/versenden");
    }
}
