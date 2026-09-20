<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Payments\Actions\CreatePaymentPlan;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Actions\UpdatePaymentPlan;
use App\Payments\Data\PaymentInstallmentData;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The api surface's mirror of App\Payments\Http\Controllers\
 * PaymentPlanController and PaymentInstallmentController — same Actions,
 * `scope:` in place of the staff Policy those reuse (see QuoteController's
 * own doc comment on why no $this->authorize() call appears here).
 */
class PaymentController extends Controller
{
    public function store(Request $request, Project $project, CreatePaymentPlan $create): JsonResponse
    {
        $job = $project->job ?? abort(404);

        $validated = $request->validate([
            'total' => ['required', 'integer', 'min:0'],
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.due_on' => ['required', 'date'],
        ]);

        $plan = $create($job, $validated['total'], collect($validated['installments'])->pluck('due_on')->all());

        return response()->json(['data' => PaymentPlanData::fromModel($plan->fresh('installments'))], 201);
    }

    public function update(Request $request, PaymentPlan $paymentPlan, UpdatePaymentPlan $update): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'total' => ['required', 'integer', 'min:0'],
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.due_on' => ['required', 'date'],
            'installments.*.amount' => ['required', 'integer', 'min:0'],
            'installments.*.status' => ['required', 'in:scheduled,paid'],
        ]);

        // Same friendly 422 as PaymentPlanController::update() — the
        // constraint trigger (2026_08_09_002500) is the backstop, not the
        // only check.
        $validator->after(function ($validator) use ($request) {
            $total = (int) $request->input('total');
            $sum = collect($request->input('installments', []))->sum(fn ($item) => (int) ($item['amount'] ?? 0));

            if ($sum !== $total) {
                $validator->errors()->add('installments', "Installments sum to {$sum}, not the plan total of {$total}.");
            }
        });

        $validated = $validator->validate();

        $plan = $update($paymentPlan, $validated['total'], $validated['installments']);

        return response()->json(['data' => PaymentPlanData::fromModel($plan)]);
    }

    public function markPaid(Request $request, PaymentInstallment $installment, MarkInstallmentPaid $markPaid): JsonResponse
    {
        $validated = $request->validate([
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(SettledVia::class)],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $markPaid(
            $installment,
            SettledVia::from($validated['method']),
            $validated['paid_on'],
            $validated['reference'] ?? null,
            $this->actor($request),
        );

        return response()->json(['data' => $this->installmentData($installment->fresh())]);
    }

    public function issueInvoice(Request $request, PaymentInstallment $installment, IssueInvoice $issue): JsonResponse
    {
        $issue($installment, $this->actor($request));

        return response()->json(['data' => $this->installmentData($installment->fresh())]);
    }

    private function installmentData(PaymentInstallment $installment): PaymentInstallmentData
    {
        return PaymentInstallmentData::fromModel($installment, PaymentPlanData::labelForInstallment($installment));
    }

    /** N-04: the consenting staff member behind an OAuth token, null for a machine one. */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
