<?php

use App\Quotes\Actions\AllocateQuoteNumber;
use App\Quotes\Models\QuoteNumberCounter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('formats the first number of the year as AN-{year}-1', function () {
    $result = app(AllocateQuoteNumber::class)(config('tenancy.tenant_id'), 2026);

    expect($result)->toBe(['number' => 'AN-2026-1', 'year' => 2026, 'counter' => 1]);
});

it('increments the counter on the next call for the same tenant and year', function () {
    $tenantId = config('tenancy.tenant_id');

    app(AllocateQuoteNumber::class)($tenantId, 2026);
    $second = app(AllocateQuoteNumber::class)($tenantId, 2026);
    $third = app(AllocateQuoteNumber::class)($tenantId, 2026);

    expect($second)->toBe(['number' => 'AN-2026-2', 'year' => 2026, 'counter' => 2])
        ->and($third)->toBe(['number' => 'AN-2026-3', 'year' => 2026, 'counter' => 3]);
});

it('starts a new year back at 1 without disturbing the old year', function () {
    $tenantId = config('tenancy.tenant_id');

    app(AllocateQuoteNumber::class)($tenantId, 2026);
    app(AllocateQuoteNumber::class)($tenantId, 2026);
    $firstOfNextYear = app(AllocateQuoteNumber::class)($tenantId, 2027);
    $backToOldYear = app(AllocateQuoteNumber::class)($tenantId, 2026);

    expect($firstOfNextYear)->toBe(['number' => 'AN-2027-1', 'year' => 2027, 'counter' => 1])
        ->and($backToOldYear)->toBe(['number' => 'AN-2026-3', 'year' => 2026, 'counter' => 3]);
});

it('keeps two tenants on independent sequences for the same year', function () {
    $tenantA = (string) Str::uuid();
    $tenantB = (string) Str::uuid();

    app(AllocateQuoteNumber::class)($tenantA, 2026);
    $secondForA = app(AllocateQuoteNumber::class)($tenantA, 2026);
    $firstForB = app(AllocateQuoteNumber::class)($tenantB, 2026);

    expect($secondForA)->toBe(['number' => 'AN-2026-2', 'year' => 2026, 'counter' => 2])
        ->and($firstForB)->toBe(['number' => 'AN-2026-1', 'year' => 2026, 'counter' => 1]);
});

it('defaults to the current tenant and the current UTC year when called with no arguments', function () {
    config(['tenancy.tenant_id' => (string) Str::uuid()]);
    $this->travelTo(now()->utc()->setDate(2031, 12, 31));

    $result = app(AllocateQuoteNumber::class)();

    expect($result)->toBe(['number' => 'AN-2031-1', 'year' => 2031, 'counter' => 1]);
});

it('rejects a second row for the same tenant, year and counter at the schema level', function () {
    // Not a simulated race — a direct check that the safety net the
    // allocator relies on actually exists. The real race is proven below.
    $tenantId = (string) Str::uuid();

    QuoteNumberCounter::create(['tenant_id' => $tenantId, 'year' => 2026, 'counter' => 1]);

    expect(fn () => QuoteNumberCounter::create(['tenant_id' => $tenantId, 'year' => 2026, 'counter' => 1]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('never hands out the same number twice under real concurrent access', function () {
    // A single PHP process can't have two allocations genuinely in flight
    // at once, so this fires real, separate OS processes at the same
    // tenant/year — each with its own DB connection — and lets the actual
    // race happen at the database rather than simulating one.
    $tenantId = (string) Str::uuid();
    $year = 2026;
    $attempts = 20;

    $pool = Process::pool(function ($pool) use ($tenantId, $year, $attempts) {
        for ($i = 0; $i < $attempts; $i++) {
            $pool->as($i)->path(base_path())->timeout(30)->command([
                'php', 'tests/Support/allocate_quote_number_cli.php', $tenantId, (string) $year,
            ]);
        }
    })->start();

    $results = $pool->wait()->collect();

    $succeeded = $results->filter->successful();
    $failed = $results->reject->successful();

    $numbers = $succeeded->map(fn ($result) => json_decode($result->output(), true)['number']);

    expect($succeeded->count())->toBeGreaterThan(0)
        ->and($numbers->count())->toBe($numbers->unique()->count());

    // Anything that lost the race failed loudly via the constraint, not
    // silently — never a duplicate slipping past as a "success" with data
    // that doesn't match what actually landed.
    $failed->each(
        fn ($result) => expect($result->errorOutput())->toContain('quote_number_counters_tenant_id_year_counter_unique')
    );

    // The database agrees with what the processes reported: exactly one row
    // per successful allocation, and every counter value handed back is
    // actually there. Real subprocesses commit for real on their own
    // connections — RefreshDatabase's rollback of the main test connection
    // never touches them — so this also cleans up after itself.
    expect(QuoteNumberCounter::where('tenant_id', $tenantId)->where('year', $year)->count())
        ->toBe($succeeded->count());

    QuoteNumberCounter::where('tenant_id', $tenantId)->where('year', $year)->delete();
});
