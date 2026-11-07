<?php

namespace App\Payments\Actions;

use App\Crm\Data\DomainRowData;
use App\Crm\Data\RowActionData;
use App\Crm\Models\ClientCase;
use App\Models\User;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * Mockup 01's *Zahlungen* column: money the machine has already chased and
 * not got.
 *
 * One row, not the mockup's two. The other — an installment never issued —
 * has no state to detect and no action to end it with; the design's
 * "Deferred: nie fällig gestellt" records why, and it belongs to a Payments
 * story rather than to this screen.
 *
 * The gate is overdue_notified_at rather than an AutomationRun count:
 * DetectOverdueInstallments already stamps the moment the studio's own scan
 * first noticed, and the Stripe webhook stamps the same field. That is the
 * dunning trail the mockup's row actually describes.
 */
class ReadPaymentColumn
{
    private const DUNNING_EXHAUSTED_DAYS = 3;

    /**
     * @return Collection<int, DomainRowData>
     */
    public function __invoke(?User $viewer = null): Collection
    {
        $viewer ??= auth('staff')->user();

        return PaymentInstallment::query()
            ->where('status', PaymentInstallmentStatus::Scheduled)
            ->whereNotNull('overdue_notified_at')
            ->where('overdue_notified_at', '<=', now()->subDays(self::DUNNING_EXHAUSTED_DAYS))
            ->when($viewer !== null, fn ($query) => $query->whereHas(
                'plan',
                fn ($query) => $query->whereIn(
                    'case_id',
                    ClientCase::query()->visibleTo($viewer)->select('cases.id'),
                ),
            ))
            ->with('plan.case')
            ->orderBy('overdue_notified_at')
            ->get()
            ->map(function (PaymentInstallment $installment) {
                $project = $installment->plan->case;
                $days = (int) $installment->due_on->copy()->startOfDay()->diffInDays(today());

                return new DomainRowData(
                    projectId: $project->public_id,
                    who: $project->name,
                    what: 'Rate überfällig',
                    emphasis: "seit {$days} Tagen",
                    whatTail: sprintf(
                        '%s',
                        Number::currency($installment->amount / 100, 'EUR', 'de', 0),
                    ),
                    action: RowActionData::tel('Anrufen', $project->primaryContact()?->phone),
                    hot: true,
                );
            })
            ->values();
    }
}
