<?php

namespace App\Finance\Support;

use App\Finance\Data\EuerChecklistData;
use App\Finance\Data\EuerReportData;
use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use App\Finance\Models\FiscalYear;
use App\Finance\Models\FixedAsset;
use App\Models\StudioSettings;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\CreditNote;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Support\InvoiceFigures;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The yearly EÜR as a query over what Auri already knows: payments received,
 * credit notes, booked expenses and the depreciation of booked assets. Nothing
 * is stored until a year is locked; then the snapshot is served instead.
 *
 * Every amount a business share touches is rounded half up on its own line,
 * so the lists behind a figure add up to it.
 */
final class EuerReport
{
    /** @var array<int, InvoiceFigures> */
    private array $invoiceFigures = [];

    public function forYear(int $year): EuerReportData
    {
        $fiscal = FiscalYear::query()->where('year', $year)->first();

        if ($fiscal?->isLocked() && $fiscal->snapshot !== null) {
            return EuerReportData::from([...$fiscal->snapshot['report'], 'lockedAt' => $fiscal->locked_at->format('Y-m-d')]);
        }

        return $this->compute($year);
    }

    public function compute(int $year): EuerReportData
    {
        $fiscal = FiscalYear::query()->where('year', $year)->first();
        $mode = $fiscal?->vat_mode ?? StudioSettings::current()->vat_mode;
        $small = $mode === 'small_business';

        [$operating, $collectedVat, $credits] = $this->income($year, $small);

        $categories = [];
        $inputVat = 0;

        foreach ($this->bookedExpenses($year)->with('category')->get() as $expense) {
            [$net, $vat] = self::eur($expense);
            $share = $expense->business_share;

            if ($expense->asset_id === null) {
                $categories[$expense->category_id] ??= ['category' => $expense->category, 'amount' => 0, 'count' => 0];
                $categories[$expense->category_id]['amount'] += Depreciation::share($small ? $net + $vat : $net, $share);
                $categories[$expense->category_id]['count']++;
            }

            $inputVat += $small ? 0 : Depreciation::share($vat, $share);
        }

        $depreciation = $residual = $sales = 0;

        foreach ($this->countedAssets($year) as $asset) {
            $schedule = Depreciation::of($asset);
            $depreciation += Depreciation::share($schedule->yearly($year), $asset->business_share);

            if ($asset->disposed_at?->year === $year) {
                $residual += Depreciation::share($schedule->residualAtDisposal(), $asset->business_share);
                $sales += Depreciation::share((int) $asset->disposal_proceeds, $asset->business_share);
            }
        }

        $rows = collect($categories)
            ->sortBy(fn (array $row) => $row['category']->sort_order)
            ->map(fn (array $row) => [
                'key' => $row['category']->key,
                'label' => $row['category']->label,
                'euerLine' => $row['category']->euer_line,
                'amount' => $row['amount'],
                'count' => $row['count'],
            ])->values()->all();

        $income = $operating + $collectedVat + $sales;
        $expenses = array_sum(array_column($rows, 'amount')) + $depreciation + $residual + $inputVat;

        return new EuerReportData(
            year: $year,
            vatMode: $mode,
            lockedAt: $fiscal?->locked_at?->format('Y-m-d'),
            operatingIncome: $operating,
            creditNotes: $credits,
            collectedVat: $collectedVat,
            assetSales: $sales,
            income: $income,
            categories: $rows,
            depreciation: $depreciation,
            residualBookValue: $residual,
            paidInputVat: $inputVat,
            expenses: $expenses,
            profit: $income - $expenses,
            checklist: $this->checklist($year),
        );
    }

    /**
     * The expenses and assets behind the figures, for the PDF appendix and the snapshot.
     *
     * @return array{expenses: list<array<string, mixed>>, assets: list<array<string, mixed>>}
     */
    public function appendix(int $year): array
    {
        $end = CarbonImmutable::create($year, 12, 31);

        return [
            'expenses' => $this->bookedExpenses($year)->with('category')->orderBy('paid_at')->orderBy('id')->get()->map(fn (Expense $expense) => [
                'vendor' => $expense->vendor,
                'invoiceNo' => $expense->invoice_no,
                'paidAt' => $expense->paid_at->format('Y-m-d'),
                'category' => $expense->category?->label,
                'grossEur' => (int) $expense->gross_eur,
                'businessShare' => $expense->business_share,
                'asset' => $expense->asset_id !== null,
            ])->all(),
            'assets' => $this->countedAssets($year)->map(function (FixedAsset $asset) use ($year, $end) {
                $schedule = Depreciation::of($asset);

                return [
                    'inventoryNo' => $asset->inventory_no,
                    'name' => $asset->name,
                    'acquiredAt' => $asset->acquired_at->format('Y-m-d'),
                    'method' => $asset->method->label(),
                    'cost' => $asset->basis(),
                    'depreciation' => $schedule->yearly($year),
                    'bookValue' => $schedule->bookValue($end),
                    'businessShare' => $asset->business_share,
                    'disposedAt' => $asset->disposed_at?->format('Y-m-d'),
                ];
            })->all(),
        ];
    }

    /** @return array{0: int, 1: int, 2: int} operating income after Gutschriften, collected VAT, and what the Gutschriften took off */
    private function income(int $year, bool $small): array
    {
        $operating = $vat = $credits = 0;

        $installments = PaymentInstallment::query()
            ->whereYear('paid_on', $year)
            ->where('paid_amount', '>', 0)
            ->whereNull('cancelled_at')
            ->with(['invoice.items.installment', 'invoice.case', 'plan.case'])
            ->get();

        foreach ($installments as $installment) {
            $figures = InvoiceFigures::forInstallment($installment, $installment->invoice ? $this->figuresFor($installment->invoice) : null);
            $operating += $small ? $figures->gross : $figures->net;
            $vat += $small ? 0 : $figures->vat;
        }

        $notes = CreditNote::query()->whereYear('issued_at', $year)->with(['invoice.items.installment', 'invoice.case'])->get();

        foreach ($notes as $note) {
            $figures = $this->figuresFor($note->invoice)->share((int) $note->amount);
            $operating -= $small ? $figures->gross : $figures->net;
            $credits += $small ? $figures->gross : $figures->net;
            $vat -= $small ? 0 : $figures->vat;
        }

        return [$operating, $vat, $credits];
    }

    private function figuresFor(Invoice $invoice): InvoiceFigures
    {
        return $this->invoiceFigures[$invoice->id] ??= InvoiceFigures::forInvoice($invoice);
    }

    private function checklist(int $year): EuerChecklistData
    {
        $open = PaymentInstallment::query()
            ->whereNotNull('invoice_id')
            ->whereNull('cancelled_at')
            ->whereIn('status', [PaymentInstallmentStatus::Invoiced, PaymentInstallmentStatus::Partial])
            ->whereHas('invoice', fn (Builder $invoice) => $invoice->whereYear('issued_at', $year)->whereNull('cancelled_at'))
            ->get();

        return new EuerChecklistData(
            withoutReceipt: $this->bookedExpenses($year)->whereDoesntHave('documents')->count(),
            drafts: Expense::query()->inYear($year)->where('status', ExpenseStatus::Draft)->count(),
            openInvoices: $open->count(),
            openInvoicesRemaining: $open->sum(fn (PaymentInstallment $installment) => $installment->remaining()),
            foreignWithoutEur: $this->bookedExpenses($year)->where('currency', '!=', 'EUR')->whereNull('gross_eur')->count(),
        );
    }

    /** @return Builder<Expense> */
    private function bookedExpenses(int $year): Builder
    {
        return Expense::query()->where('status', ExpenseStatus::Booked)->whereYear('paid_at', $year);
    }

    /** @return Collection<int, FixedAsset> */
    private function countedAssets(int $year): Collection
    {
        return FixedAsset::query()
            ->whereHas('expense', fn (Builder $expense) => $expense->where('status', ExpenseStatus::Booked))
            ->whereYear('acquired_at', '<=', $year)
            ->orderBy('acquired_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * An expense in euro: the VAT share of what was debited, and the rest.
     *
     * @return array{0: int, 1: int} net and VAT
     */
    private static function eur(Expense $expense): array
    {
        $gross = (int) $expense->gross_eur;
        $vat = $expense->gross > 0 ? intdiv($expense->vat * $gross * 2 + $expense->gross, $expense->gross * 2) : 0;

        return [$gross - $vat, $vat];
    }
}
