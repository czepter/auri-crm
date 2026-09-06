<?php

namespace App\Payments\Http\Controllers;

use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Payments\Actions\CreatePaymentPlan;
use App\Payments\Actions\UpdatePaymentPlan;
use App\Payments\Models\PaymentPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentPlanController extends Controller
{
    public function store(Request $request, Project $project, CreatePaymentPlan $create): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $project);

        // "A project without a Job has no plan" (M-03) — the same fact
        // ProjectHeaderData::isBooked already reads off $project->job.
        $job = $project->job ?? abort(404);

        $validated = $request->validate([
            'total' => ['required', 'integer', 'min:0'],
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.due_on' => ['required', 'date'],
        ]);

        $create($job, $validated['total'], collect($validated['installments'])->pluck('due_on')->all());

        return back();
    }

    public function update(Request $request, PaymentPlan $paymentPlan, UpdatePaymentPlan $update): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $paymentPlan->job->project);

        $validator = Validator::make($request->all(), [
            'total' => ['required', 'integer', 'min:0'],
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.due_on' => ['required', 'date'],
            'installments.*.amount' => ['required', 'integer', 'min:0'],
            // 'invoiced' is never a valid submission from this form — nothing
            // in M-03 reaches it, and PaymentPlan::isEditable() is the guard
            // that stops editing once something (M-04) does.
            'installments.*.status' => ['required', 'in:scheduled,paid'],
        ]);

        // A friendly 422 rather than the database's own QueryException — the
        // constraint trigger (2026_08_09_002500) is the backstop this leans
        // on, not the only line of defence, the same two-layer shape
        // DiscountCalculator's capping backs up quotes_net_check.
        $validator->after(function ($validator) use ($request) {
            $total = (int) $request->input('total');
            $sum = collect($request->input('installments', []))->sum(fn ($item) => (int) ($item['amount'] ?? 0));

            if ($sum !== $total) {
                $validator->errors()->add('installments', "Installments sum to {$sum}, not the plan total of {$total}.");
            }
        });

        $validated = $validator->validate();

        $update($paymentPlan, $validated['total'], $validated['installments']);

        return back();
    }

    /**
     * T-25: reminders for this one client, off. Its own route rather than a
     * field on update() above, which submits the whole plan and is refused
     * once anything is paid — a phone call must not need an editable plan.
     */
    public function reminders(Request $request, PaymentPlan $paymentPlan): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $paymentPlan->job->project);

        $paymentPlan->update($request->validate([
            'client_reminders_enabled' => ['required', 'boolean'],
        ]));

        return back();
    }
}
