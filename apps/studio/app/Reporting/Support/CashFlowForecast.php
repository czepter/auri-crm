<?php

namespace App\Reporting\Support;

use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Support\Carbon;

/**
 * M-07's Liquiditätsplanung: what is coming in, by month, out of rows that
 * already exist.
 *
 * **No forecasting model and no probability weighting.** Every figure here is
 * a sum over known due dates. A weighted pipeline number would be a guess
 * wearing a euro sign, and a studio planning a quarter around one would be
 * planning around this class's opinion rather than its own order book.
 *
 * Overdue is its own bucket at the head rather than folded into the current
 * month: an installment three months late is not this month's expectation,
 * and adding it to one makes the month it lands in look healthier than it is.
 */
class CashFlowForecast
{
    public const MONTHS = 12;

    /**
     * @return array{
     *     overdue: int,
     *     months: list<array{month: string, due: int, received: int, open: int}>,
     * }
     */
    public static function build(?Carbon $from = null): array
    {
        $start = ($from ?? now())->copy()->startOfMonth();

        return [
            'overdue' => self::overdue($start),
            'months' => self::months($start),
        ];
    }

    /**
     * Unpaid, and due before the window opens. Never invoiced-vs-scheduled:
     * money is late because its date passed, not because of the paperwork
     * state it happens to be in.
     */
    private static function overdue(Carbon $start): int
    {
        return (int) PaymentInstallment::query()
            ->where('status', '!=', PaymentInstallmentStatus::Paid)
            ->where('due_on', '<', $start)
            ->sum('amount');
    }

    /**
     * @return list<array{month: string, due: int, received: int, open: int}>
     */
    private static function months(Carbon $start): array
    {
        $months = [];

        for ($offset = 0; $offset < self::MONTHS; $offset++) {
            $monthStart = $start->copy()->addMonths($offset);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $months[] = [
                'month' => $monthStart->format('Y-m'),

                // Expected: unpaid installments falling due in the month.
                'due' => (int) PaymentInstallment::query()
                    ->where('status', '!=', PaymentInstallmentStatus::Paid)
                    ->whereBetween('due_on', [$monthStart, $monthEnd])
                    ->sum('amount'),

                // Received keys on the settlement date, not the due date —
                // an installment due in March and paid in April is April's
                // money, and showing it in March would describe a bank
                // balance that never existed.
                'received' => (int) PaymentInstallment::query()
                    ->where('status', PaymentInstallmentStatus::Paid)
                    ->whereBetween('paid_on', [$monthStart, $monthEnd])
                    ->sum('amount'),

                // Still only a possibility: sent, unanswered, and not yet
                // expired. Deliberately unweighted, and kept in its own
                // column so it is never mistaken for the first two.
                'open' => (int) Quote::query()
                    ->where('status', QuoteStatus::Sent)
                    ->whereNotNull('valid_until')
                    ->whereBetween('valid_until', [$monthStart, $monthEnd])
                    ->sum('total'),
            ];
        }

        return $months;
    }
}
