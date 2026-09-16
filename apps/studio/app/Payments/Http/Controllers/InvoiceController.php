<?php

namespace App\Payments\Http\Controllers;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Payments\Data\InvoicePlanData;
use App\Payments\Models\PaymentPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * #479: every rate of every visible project, with the actions the project's
 * payment card has. Same gate as ProjectPolicy::managePaymentPlan, which each
 * of those actions checks again per project.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $request->user('staff');
        abort_unless($viewer->role->allows(Capability::ManageContracts), 403);

        $plans = PaymentPlan::query()
            ->whereHas('job.project', fn ($query) => $query->visibleTo($viewer))
            ->with(['installments.creditNotes', 'job.project'])
            ->latest()
            ->get();

        return Inertia::render('Invoices/Index', [
            'plans' => InvoicePlanData::collect($plans->map(InvoicePlanData::fromModel(...))),
        ]);
    }
}
