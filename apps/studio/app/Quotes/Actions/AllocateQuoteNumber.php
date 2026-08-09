<?php

namespace App\Quotes\Actions;

use App\Quotes\Models\QuoteNumberCounter;
use Illuminate\Support\Facades\DB;

/**
 * Hands out AN-{year}-{counter}, one sequence per tenant per year.
 *
 * Standalone on purpose: nothing in this codebase calls it yet. Q-06's
 * "send quote" action is the one intended caller, and only at send — never
 * at draft — so an abandoned draft burns no number. Its future caller is
 * expected to invoke this inside its own DB transaction, so a failed send
 * rolls the number back with everything else it touched.
 *
 * The transaction here only wraps the read-then-insert; it does not retry.
 * Two calls racing for the same (tenant_id, year) both compute the same
 * next counter, and the table's unique index lets exactly one insert land —
 * the other throws a query exception. That's deliberate: silently retrying
 * would hide a real contention problem behind extra queries, and the one
 * caller this has is expected to decide for itself whether "try again" or
 * "tell the user" is the right response to a losing race.
 */
class AllocateQuoteNumber
{
    /**
     * @return array{number: string, year: int, counter: int}
     */
    public function __invoke(?string $tenantId = null, ?int $year = null): array
    {
        $tenantId ??= config('tenancy.tenant_id');
        $year ??= now('UTC')->year;

        return DB::transaction(function () use ($tenantId, $year) {
            $counter = 1 + (int) QuoteNumberCounter::query()
                ->where('tenant_id', $tenantId)
                ->where('year', $year)
                ->max('counter');

            QuoteNumberCounter::create([
                'tenant_id' => $tenantId,
                'year' => $year,
                'counter' => $counter,
            ]);

            return [
                'number' => "AN-{$year}-{$counter}",
                'year' => $year,
                'counter' => $counter,
            ];
        });
    }
}
