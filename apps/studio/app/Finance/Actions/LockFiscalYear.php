<?php

namespace App\Finance\Actions;

use App\Finance\Enums\ExpenseStatus;
use App\Finance\Jobs\RenderEuerPdfJob;
use App\Finance\Models\Expense;
use App\Finance\Models\FiscalYear;
use App\Finance\Support\EuerReport;
use App\Finance\Support\FiscalYearLock;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes a past year for good: the report as it reads now is stored as the
 * year's snapshot, and every later write dated in the year is refused. There
 * is no way back.
 */
class LockFiscalYear
{
    public function __construct(private readonly EuerReport $report) {}

    public function __invoke(int $year, ?User $actor = null): FiscalYear
    {
        FiscalYearLock::assertOpen('year', $year);

        if ($year >= now()->year) {
            throw ValidationException::withMessages(['year' => __('finance.lockYearOpen')]);
        }

        $drafts = Expense::query()->inYear($year)->where('status', ExpenseStatus::Draft)->count();

        if ($drafts > 0) {
            throw ValidationException::withMessages(['year' => __('finance.lockOpenDrafts', ['count' => $drafts])]);
        }

        $fiscal = DB::transaction(function () use ($year, $actor) {
            $fiscal = FiscalYear::forYear($year);
            FiscalYear::query()->whereKey($fiscal->id)->lockForUpdate()->first();

            $fiscal->update([
                'snapshot' => [
                    'report' => $this->report->compute($year)->toArray(),
                    ...$this->report->appendix($year),
                ],
                'locked_at' => now(),
                'locked_by' => $actor?->id,
            ]);

            return $fiscal;
        });

        AuditLogger::record('finance.year.locked', $actor, ['year' => $year], $fiscal->public_id);

        // The lock stands whether or not Gotenberg answers; the PDF is made on the first download otherwise.
        rescue(fn () => Bus::dispatch((new RenderEuerPdfJob($fiscal))->onQueue('render')));

        return $fiscal;
    }
}
