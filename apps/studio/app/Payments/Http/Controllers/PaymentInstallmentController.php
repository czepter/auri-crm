<?php

namespace App\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
}
