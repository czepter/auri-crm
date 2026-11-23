<?php

namespace App\Finance\Support;

use App\Finance\Models\FiscalYear;
use Illuminate\Validation\ValidationException;

final class FiscalYearLock
{
    public static function assertOpen(string $field, ?int ...$years): void
    {
        $locked = FiscalYear::query()
            ->whereIn('year', array_unique(array_filter($years, fn (?int $year) => $year !== null)))
            ->whereNotNull('locked_at')
            ->orderBy('year')
            ->first();

        if ($locked !== null) {
            throw ValidationException::withMessages([$field => __('finance.yearLocked', ['year' => $locked->year])]);
        }
    }
}
