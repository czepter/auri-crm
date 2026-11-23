<?php

namespace App\Finance\Actions;

use App\Finance\Models\FiscalYear;
use App\Finance\Support\FiscalYearLock;
use App\Models\User;
use App\Support\AuditLogger;

class ChangeFiscalYearVatMode
{
    public function __invoke(int $year, string $mode, ?User $actor = null): FiscalYear
    {
        FiscalYearLock::assertOpen('vat_mode', $year);

        $fiscal = FiscalYear::forYear($year);
        $from = $fiscal->vat_mode;
        $fiscal->update(['vat_mode' => $mode]);

        if ($from !== $mode) {
            AuditLogger::record('finance.year.vat_mode_changed', $actor, ['year' => $year, 'from' => $from, 'to' => $mode], $fiscal->public_id);
        }

        return $fiscal;
    }
}
