<?php

namespace App\Payments\Http\Controllers;

use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Payments\Actions\CreatePaymentPlan;
use App\Payments\Actions\SettlePaymentPlan;
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
            // Absent for a row the form has just added; names the row to
            // update in place for every row that already exists (#407).
            'installments.*.public_id' => ['nullable', 'string'],
            'installments.*.due_on' => ['required', 'date'],
            'installments.*.amount' => ['required', 'integer', 'min:0'],
            // An issued row keeps the status it has, so the form has none to
            // send for it; 'invoiced' is never a submission, only an issue.
            'installments.*.status' => ['nullable', 'in:scheduled,paid'],
        ]);

        // A friendly 422 rather than the database's own QueryException — the
        // constraint trigger (2026_08_09_002500) is the backstop this leans
        // on, not the only line of defence, the same two-layer shape
        // DiscountCalculator's capping backs up quotes_net_check. Issued rows
        // count with their stored amount: that is the figure the update keeps.
        $validator->after(function ($validator) use ($request, $paymentPlan) {
            $total = (int) $request->input('total');
            // A cancelled row stays in the payload, or UpdatePaymentPlan would try to delete it, but
            // counts zero — the sum trigger excludes it since 2026_09_14_000200.
            $issued = $paymentPlan->installments()->whereNotNull('invoiced_at')->get()
                ->mapWithKeys(fn ($row) => [$row->public_id => $row->isCancelled() ? 0 : $row->amount]);
            $sum = collect($request->input('installments', []))
                ->sum(fn ($item) => (int) ($issued[$item['public_id'] ?? ''] ?? $item['amount'] ?? 0));

            if ($sum !== $total) {
                $validator->errors()->add('installments', "Installments sum to {$sum}, not the plan total of {$total}.");
            }
        });

        $validated = $validator->validate();

        $update($paymentPlan, $validated['total'], $validated['installments']);

        return back();
    }

    /**
     * #418: the whole plan closed in one call, with one decision per rate
     * that already has money on it. See SettlePaymentPlan for what each
     * state becomes.
     */
    public function settle(Request $request, PaymentPlan $paymentPlan, SettlePaymentPlan $settle): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $paymentPlan->job->project);

        $validated = $request->validate([
            'decisions' => ['nullable', 'array'],
            'decisions.*.action' => ['required', 'in:keep,credit'],
            'decisions.*.amount' => ['nullable', 'integer', 'min:1'],
        ]);

        $settle($paymentPlan, $validated['decisions'] ?? [], auth('staff')->user());

        return redirect("/auftraege/{$paymentPlan->job->project->public_id}");
    }

    /**
     * T-25: reminders for this one client, off. Its own route rather than a
     * field on update() above, which submits the whole plan — a phone call
     * must not need every installment's amount and date to hand.
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
