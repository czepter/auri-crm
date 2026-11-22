<?php

namespace App\Finance\Data;

use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use App\Finance\Support\Depreciation;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ExpenseTotalsData extends Data
{
    public function __construct(
        public int $sum,
        public int $business,
        public int $drafts,
    ) {}

    /** @param  Collection<int, Expense>  $expenses  one year's expenses, whatever their status */
    public static function fromYear(Collection $expenses): self
    {
        $booked = $expenses->where('status', ExpenseStatus::Booked);

        return new self(
            sum: (int) $booked->sum('gross_eur'),
            business: $booked->sum(fn (Expense $e) => Depreciation::share((int) $e->gross_eur, $e->business_share)),
            drafts: $expenses->where('status', ExpenseStatus::Draft)->count(),
        );
    }
}
