<?php

namespace App\Quotes\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per number ever handed out — never a single row incremented in
 * place. That shape is what lets the unique index on (tenant_id, year,
 * counter) do the concurrency-safety work instead of a row lock: two
 * inserts racing for the same triple can't both land.
 *
 * Deliberately skips BelongsToTenant. That trait's global scope is fixed to
 * config('tenancy.tenant_id') for the current request, but
 * AllocateQuoteNumber counts against whichever tenant its caller names —
 * today always the config tenant, but the scope would silently mis-filter
 * the day a second tenant exists. tenant_id is still required on every row
 * (via the tenantId() migration macro); it's just not enforced by a global
 * scope here.
 *
 * No HasPublicId either: this table is purely internal bookkeeping, never
 * looked up by route, API or any other model's relation.
 */
class QuoteNumberCounter extends Model
{
    protected $fillable = ['tenant_id', 'year', 'counter'];
}
