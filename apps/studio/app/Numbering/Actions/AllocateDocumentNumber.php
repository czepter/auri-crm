<?php

namespace App\Numbering\Actions;

use App\Numbering\Enums\DocumentType;
use App\Numbering\Enums\NumberRangeResetRule;
use App\Numbering\Models\NumberCounter;
use App\Settings\Models\NumberRange;
use Illuminate\Support\Facades\DB;

/**
 * Hands out the next number for one tenant's range of one document type —
 * Q-05's AllocateQuoteNumber, generalised by T-04 to read a range's prefix,
 * reset rule and starting counter from configuration (App\Settings\Models\
 * NumberRange) instead of a hardcoded 'AN' string and an assumed-yearly
 * reset. $documentType defaults to Quote, so every call Q-05 itself wrote —
 * and the one Q-06 is expected to write, positional ($tenantId, $year) and
 * all — keeps working unchanged.
 *
 * Standalone on purpose: nothing in this codebase calls it yet for a real
 * document. Q-06's "send quote" action is the one intended caller, and only
 * at send — never at draft — so an abandoned draft burns no number. Its
 * future caller is expected to invoke this inside its own DB transaction, so
 * a failed send rolls the number back with everything else it touched.
 *
 * The transaction here only wraps the read-then-insert; it does not retry.
 * Two calls racing for the same (tenant_id, document_type, period) both
 * compute the same next counter, and the table's unique index lets exactly
 * one insert land — the other throws a query exception. That's deliberate,
 * unchanged from Q-05: silently retrying would hide a real contention
 * problem behind extra queries, and the one caller this has is expected to
 * decide for itself whether "try again" or "tell the user" is the right
 * response to a losing race.
 */
class AllocateDocumentNumber
{
    /**
     * @return array{number: string, year: int, counter: int}
     */
    public function __invoke(
        ?string $tenantId = null,
        ?int $year = null,
        DocumentType $documentType = DocumentType::Quote,
    ): array {
        $tenantId ??= config('tenancy.tenant_id');
        $year ??= now('UTC')->year;

        return DB::transaction(function () use ($documentType, $tenantId, $year) {
            // withoutGlobalScope('tenant'): NumberRange carries BelongsToTenant
            // for the settings screen, which only ever reaches its own
            // request's tenant. This allocator counts against whichever
            // tenant its caller names — today always config('tenancy.tenant_id')
            // but not necessarily so the day a second tenant exists — and the
            // trait's scope would silently mis-filter it out the moment the
            // two disagree, the same reasoning NumberCounter documents for
            // skipping the trait outright.
            $range = NumberRange::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('document_type', $documentType->value)
                ->firstOrFail();

            $period = $this->period($range, $year);

            $counter = $this->nextCounter($range, $tenantId, $period);

            NumberCounter::create([
                'tenant_id' => $tenantId,
                'document_type' => $documentType->value,
                'year' => $period,
                'counter' => $counter,
            ]);

            return [
                'number' => $this->format($range, $year, $counter),
                'year' => $year,
                'counter' => $counter,
            ];
        });
    }

    /**
     * The number a range's next allocation would carry, without allocating
     * it — T-04's settings-screen preview. Reads outside any transaction:
     * a preview is advisory, not a reservation, and two staff looking at the
     * same settings screen seeing the same preview is expected, not a race.
     */
    public function preview(NumberRange $range): string
    {
        $year = now('UTC')->year;
        $period = $this->period($range, $year);
        $counter = $this->nextCounter($range, $range->tenant_id, $period);

        return $this->format($range, $year, $counter);
    }

    private function period(NumberRange $range, int $year): int
    {
        return $range->reset_rule === NumberRangeResetRule::Continuous
            ? NumberCounter::CONTINUOUS_PERIOD
            : $year;
    }

    /**
     * 1 + the highest counter this range has issued for the period, or the
     * range's own starting_counter the first time anything is issued for
     * it — T-04's whole point: X-01's historical import sets that starting
     * point, and the first number this system ever issues for the range
     * picks up from there instead of restarting at 1 under a number a client
     * already holds.
     */
    private function nextCounter(NumberRange $range, string $tenantId, int $period): int
    {
        $issued = (int) NumberCounter::query()
            ->where('tenant_id', $tenantId)
            ->where('document_type', $range->document_type->value)
            ->where('year', $period)
            ->max('counter');

        return $issued > 0 ? $issued + 1 : max(1, $range->starting_counter);
    }

    private function format(NumberRange $range, int $year, int $counter): string
    {
        return match ($range->reset_rule) {
            NumberRangeResetRule::Yearly => "{$range->prefix}-{$year}-{$counter}",
            NumberRangeResetRule::Continuous => "{$range->prefix}-{$counter}",
        };
    }
}
