<?php

namespace App\Quotes\Actions;

use App\Crm\Models\Project;
use App\Payments\Data\PaymentPlanData;
use App\Quotes\Data\QuoteData;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;

/**
 * The quote tab of an inquiry and of a project: every quote as a row and what
 * the client pays. A quote is edited on its own page, not here.
 */
class ReadQuoteList
{
    public function __invoke(Project $project): array
    {
        $quotes = $project->quotes()->with(['items', 'predecessor', 'paymentStages'])->get();

        // Gezahlt wird nach dem angenommenen Angebot, bis dahin nach dem
        // jüngsten, das noch gilt.
        $payable = $quotes->firstWhere('status', QuoteStatus::Accepted)
            ?? $quotes->first(fn (Quote $quote) => $quote->status !== QuoteStatus::Superseded);

        $plan = $project->paymentPlan;

        return [
            'quotes' => QuoteData::collect($quotes),
            // Once booked, the plan is what gets charged; CreatePaymentPlan
            // split it from the same stages.
            'payments' => match (true) {
                $plan !== null => [
                    'number' => $payable?->number,
                    'rows' => collect(PaymentPlanData::fromModel($plan->load('installments.invoice.creditNotes'))->installments)
                        ->map(fn ($installment) => ['label' => $installment->label, 'amount' => $installment->amount])
                        ->all(),
                ],
                $payable !== null => ['number' => $payable->number, 'rows' => $payable->paymentSplit()],
                default => null,
            },
        ];
    }
}
