<?php

namespace App\Numbering\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per number ever handed out — never a single row incremented in
 * place. That shape is what lets the unique index on (tenant_id,
 * document_type, year, counter) do the concurrency-safety work instead of a
 * row lock: two inserts racing for the same tuple can't both land. Q-05
 * shipped this as QuoteNumberCounter, quote-only, keyed on (tenant_id, year,
 * counter); T-04 added document_type so Contract and Invoice (M-01, M-04)
 * can share the same mechanism instead of each growing their own copy.
 *
 * Table name stays quote_number_counters — T-04's issue is explicit that the
 * existing table is preserved, not rewritten, and every row it already held
 * was and remains a quote row. Renaming a live table for a naming nicety
 * is a bigger, riskier diff than living with a name that undersells what it
 * now holds.
 *
 * `year` doubles as "period": a real calendar year for a Yearly-reset range,
 * or the constant CONTINUOUS_PERIOD sentinel for a Continuous one, so every
 * range's counter — whatever its reset rule — keys off the same NOT NULL,
 * unique-indexed column rather than a nullable one NULL-vs-NULL would let
 * two continuous rows past.
 *
 * Deliberately skips BelongsToTenant, for the same reason Q-05 gave: the
 * allocator counts against whichever tenant its caller names, and the trait's
 * global scope is fixed to config('tenancy.tenant_id') for the request.
 * tenant_id is still required on every row (via the tenantId() migration
 * macro); it's just not enforced by a global scope here.
 *
 * No HasPublicId either: this table is purely internal bookkeeping, never
 * looked up by route, API or any other model's relation.
 */
class NumberCounter extends Model
{
    /** The `year` every Continuous-reset range's rows share — never a real calendar year. */
    public const CONTINUOUS_PERIOD = 0;

    protected $table = 'quote_number_counters';

    protected $fillable = ['tenant_id', 'document_type', 'year', 'counter'];
}
