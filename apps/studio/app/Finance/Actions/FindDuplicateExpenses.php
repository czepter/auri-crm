<?php

namespace App\Finance\Actions;

use App\Finance\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Expenses that already hold the same receipt, invoice number or vendor, amount and date. */
class FindDuplicateExpenses
{
    /** @return Collection<int, Expense> */
    public function __invoke(?string $sha256, ?string $vendor, ?string $invoiceNo, ?int $gross, ?string $invoiceDate, ?Expense $except = null): Collection
    {
        $vendor = $vendor === null ? null : mb_strtolower(trim($vendor));
        $invoiceNo = $invoiceNo === null ? null : mb_strtolower(trim($invoiceNo));

        return Expense::query()
            ->when($except, fn (Builder $query) => $query->whereKeyNot($except->id))
            ->where(function (Builder $query) use ($sha256, $vendor, $invoiceNo, $gross, $invoiceDate) {
                $query->whereRaw('false')
                    ->when($sha256, fn (Builder $q) => $q->orWhereHas('documents', fn (Builder $d) => $d->where('sha256', $sha256)))
                    ->when($vendor && $invoiceNo, fn (Builder $q) => $q->orWhere(fn (Builder $m) => $m
                        ->whereRaw('lower(vendor) = ?', [$vendor])
                        ->whereRaw('lower(invoice_no) = ?', [$invoiceNo])))
                    ->when($vendor && $gross !== null && $invoiceDate, fn (Builder $q) => $q->orWhere(fn (Builder $m) => $m
                        ->whereRaw('lower(vendor) = ?', [$vendor])
                        ->where('gross', $gross)
                        ->whereDate('invoice_date', $invoiceDate)));
            })
            ->with(['category', 'documents'])
            ->orderByDesc('id')
            ->get();
    }
}
