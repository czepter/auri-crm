<?php

namespace App\Reporting\Support;

use App\Crm\Enums\ProjectType;
use App\Crm\Models\ClientCase;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Carbon\CarbonInterface;

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
     *     projects: int,
     *     quotes: int,
     *     months: list<array{month: string, due: int, received: int, open: int}>,
     * }
     */
    public static function build(?CarbonInterface $from = null, ?ProjectType $type = null): array
    {
        $start = ($from ?? now())->copy()->startOfMonth();
        $end = $start->copy()->addMonths(self::MONTHS - 1)->endOfMonth();

        return [
            'overdue' => self::overdue($start, $type),
            'projects' => self::projectsBehind($type, fn ($query) => $query
                ->where('payment_installments.status', '!=', PaymentInstallmentStatus::Paid)
                ->whereBetween('payment_installments.due_on', [$start, $end])),
            'quotes' => self::openQuotes($type)
                ->where('status', QuoteStatus::Sent)
                ->whereNotNull('valid_until')
                ->whereBetween('valid_until', [$start, $end])
                ->count(),
            'months' => self::months($start, $type),
        ];
    }

    /**
     * Wie viele Projekte hinter den Balken stehen. Ein Projekt mit sechs
     * Raten ist ein Projekt, nicht sechs — gezählt wird über die Rate zum
     * Plan und von dort zum Projekt.
     *
     * @param  callable(\Illuminate\Database\Eloquent\Builder): mixed  $scope
     */
    private static function projectsBehind(?ProjectType $type, callable $scope): int
    {
        $query = PaymentInstallment::query()
            ->join('payment_plans', 'payment_plans.id', '=', 'payment_installments.payment_plan_id');

        ProjectFilter::apply($query, $type, 'payment_plans.case_id');
        $scope($query);

        return (int) $query->distinct()->count('payment_plans.case_id');
    }

    /**
     * Unpaid, and due before the window opens. Never invoiced-vs-scheduled:
     * money is late because its date passed, not because of the paperwork
     * state it happens to be in.
     */
    private static function overdue(CarbonInterface $start, ?ProjectType $type): int
    {
        return (int) self::installments($type)
            ->where('status', '!=', PaymentInstallmentStatus::Paid)
            ->where('due_on', '<', $start)
            ->sum('amount');
    }

    /**
     * Raten des Studios, auf Wunsch auf einen Projekttyp verengt. Der Weg zum
     * Projekt geht über den Plan — eine Rate kennt das Projekt nicht.
     */
    private static function installments(?ProjectType $type)
    {
        $query = PaymentInstallment::query();

        if ($type !== null) {
            $query->whereIn(
                'payment_plan_id',
                PaymentPlan::query()
                    ->select('payment_plans.id')
                    ->whereIn('payment_plans.case_id', ClientCase::query()->select('id')->where('type', $type->value)),
            );
        }

        return $query;
    }

    /**
     * @return list<array{month: string, due: int, received: int, open: int}>
     */
    private static function months(CarbonInterface $start, ?ProjectType $type): array
    {
        $months = [];

        for ($offset = 0; $offset < self::MONTHS; $offset++) {
            $monthStart = $start->copy()->addMonths($offset);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $months[] = [
                'month' => $monthStart->format('Y-m'),

                // Expected: unpaid installments falling due in the month.
                'due' => (int) self::installments($type)
                    ->where('status', '!=', PaymentInstallmentStatus::Paid)
                    ->whereBetween('due_on', [$monthStart, $monthEnd])
                    ->sum('amount'),

                // Received keys on the settlement date, not the due date —
                // an installment due in March and paid in April is April's
                // money, and showing it in March would describe a bank
                // balance that never existed.
                'received' => (int) self::installments($type)
                    ->where('status', PaymentInstallmentStatus::Paid)
                    ->whereBetween('paid_on', [$monthStart, $monthEnd])
                    ->sum('amount'),

                // Still only a possibility: sent, unanswered, and not yet
                // expired. Deliberately unweighted, and kept in its own
                // column so it is never mistaken for the first two.
                'open' => (int) self::openQuotes($type)
                    ->where('status', QuoteStatus::Sent)
                    ->whereNotNull('valid_until')
                    ->whereBetween('valid_until', [$monthStart, $monthEnd])
                    ->sum('total'),
            ];
        }

        return $months;
    }

    /**
     * Was tatsächlich hereinkam: die zwölf Monate bis einschließlich diesem,
     * gebucht auf den Zahltag.
     *
     * Kein Gegenstück zur Prognose, sondern die andere Hälfte derselben
     * Frage. Offene Angebote und fällige Raten haben hier nichts zu suchen:
     * in einem vergangenen Monat ist beides entweder Geld geworden oder
     * nicht.
     *
     * @return array{projects: int, months: list<array{month: string, received: int}>}
     */
    public static function past(?ProjectType $type = null): array
    {
        $start = now()->copy()->startOfMonth()->subMonths(self::MONTHS - 1);
        $end = now()->copy()->endOfMonth();

        $months = [];

        for ($offset = 0; $offset < self::MONTHS; $offset++) {
            $monthStart = $start->copy()->addMonths($offset);

            $months[] = [
                'month' => $monthStart->format('Y-m'),
                'received' => (int) self::installments($type)
                    ->where('status', PaymentInstallmentStatus::Paid)
                    ->whereBetween('paid_on', [$monthStart, $monthStart->copy()->endOfMonth()])
                    ->sum('amount'),
            ];
        }

        return [
            'projects' => self::projectsBehind($type, fn ($query) => $query
                ->where('payment_installments.status', PaymentInstallmentStatus::Paid)
                ->whereBetween('payment_installments.paid_on', [$start, $end])),
            'months' => $months,
        ];
    }

    /** Offene Angebote, auf Wunsch auf einen Projekttyp verengt. */
    private static function openQuotes(?ProjectType $type)
    {
        $query = Quote::query();

        ProjectFilter::apply($query, $type, 'quotes.case_id');

        return $query;
    }
}
