<?php

use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use App\Numbering\Models\NumberCounter;
use App\Settings\Models\NumberRange;
use App\Settings\Support\StudioSettingsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// Q-05's own six scenarios, unchanged in substance — the seeded tenant
// (config('tenancy.tenant_id')) already carries a quote range from the
// number_ranges migration (prefix AN, yearly, starting_counter 1), so every
// one of these still needs no setup of its own, exactly as it did before
// T-04.

it('formats the first number of the year as AN-{year}-1', function () {
    $result = app(AllocateDocumentNumber::class)(config('tenancy.tenant_id'), 2026);

    expect($result)->toBe(['number' => 'AN-2026-1', 'year' => 2026, 'counter' => 1]);
});

it('increments the counter on the next call for the same tenant and year', function () {
    $tenantId = config('tenancy.tenant_id');

    app(AllocateDocumentNumber::class)($tenantId, 2026);
    $second = app(AllocateDocumentNumber::class)($tenantId, 2026);
    $third = app(AllocateDocumentNumber::class)($tenantId, 2026);

    expect($second)->toBe(['number' => 'AN-2026-2', 'year' => 2026, 'counter' => 2])
        ->and($third)->toBe(['number' => 'AN-2026-3', 'year' => 2026, 'counter' => 3]);
});

it('starts a new year back at 1 without disturbing the old year', function () {
    $tenantId = config('tenancy.tenant_id');

    app(AllocateDocumentNumber::class)($tenantId, 2026);
    app(AllocateDocumentNumber::class)($tenantId, 2026);
    $firstOfNextYear = app(AllocateDocumentNumber::class)($tenantId, 2027);
    $backToOldYear = app(AllocateDocumentNumber::class)($tenantId, 2026);

    expect($firstOfNextYear)->toBe(['number' => 'AN-2027-1', 'year' => 2027, 'counter' => 1])
        ->and($backToOldYear)->toBe(['number' => 'AN-2026-3', 'year' => 2026, 'counter' => 3]);
});

it('keeps two tenants on independent sequences for the same year', function () {
    $tenantA = (string) Str::uuid();
    $tenantB = (string) Str::uuid();
    NumberRange::factory()->create(['tenant_id' => $tenantA]);
    NumberRange::factory()->create(['tenant_id' => $tenantB]);

    app(AllocateDocumentNumber::class)($tenantA, 2026);
    $secondForA = app(AllocateDocumentNumber::class)($tenantA, 2026);
    $firstForB = app(AllocateDocumentNumber::class)($tenantB, 2026);

    expect($secondForA)->toBe(['number' => 'AN-2026-2', 'year' => 2026, 'counter' => 2])
        ->and($firstForB)->toBe(['number' => 'AN-2026-1', 'year' => 2026, 'counter' => 1]);
});

it("defaults to the current tenant and the studio's year when called with no arguments", function () {
    app(StudioSettingsResolver::class)->set('timezone', 'Europe/Berlin');
    // 00:30 on 1 January in Berlin, still New Year's Eve in UTC.
    $this->travelTo(CarbonImmutable::parse('2031-12-31 23:30', 'UTC'));

    $result = app(AllocateDocumentNumber::class)();

    expect($result)->toBe(['number' => 'AN-2032-1', 'year' => 2032, 'counter' => 1]);
});

it('rejects a second row for the same tenant, document type, year and counter at the schema level', function () {
    // Not a simulated race — a direct check that the safety net the
    // allocator relies on actually exists. The real race is proven below.
    $tenantId = (string) Str::uuid();

    NumberCounter::create(['tenant_id' => $tenantId, 'document_type' => 'quote', 'year' => 2026, 'counter' => 1]);

    expect(fn () => NumberCounter::create(['tenant_id' => $tenantId, 'document_type' => 'quote', 'year' => 2026, 'counter' => 1]))
        ->toThrow(UniqueConstraintViolationException::class);
});

// T-04's own scenarios: the allocator reading configuration instead of a
// hardcoded 'AN' and an assumed-yearly reset.

it('reads the prefix and reset rule from the range instead of a hardcoded constant', function () {
    $tenantId = (string) Str::uuid();
    NumberRange::factory()->create([
        'tenant_id' => $tenantId,
        'document_type' => DocumentType::Invoice,
        'prefix' => 'RE',
        'reset_rule' => 'continuous',
        'starting_counter' => 1,
    ]);

    $first = app(AllocateDocumentNumber::class)($tenantId, 2026, DocumentType::Invoice);
    $second = app(AllocateDocumentNumber::class)($tenantId, 2027, DocumentType::Invoice);

    // Continuous: no year segment, and the second call keeps climbing across
    // the year boundary instead of resetting — the opposite of Yearly.
    expect($first)->toBe(['number' => 'RE-1', 'year' => 2026, 'counter' => 1])
        ->and($second)->toBe(['number' => 'RE-2', 'year' => 2027, 'counter' => 2]);
});

it('picks up from a raised starting counter the first time anything is issued', function () {
    $tenantId = (string) Str::uuid();
    NumberRange::factory()->create([
        'tenant_id' => $tenantId,
        'document_type' => DocumentType::Quote,
        'prefix' => 'AN',
        'reset_rule' => 'yearly',
        'starting_counter' => 1047,
    ]);

    $result = app(AllocateDocumentNumber::class)($tenantId, 2026, DocumentType::Quote);

    expect($result)->toBe(['number' => 'AN-2026-1047', 'year' => 2026, 'counter' => 1047]);
});

it('ignores the starting counter once something has already been issued for the period', function () {
    $tenantId = (string) Str::uuid();
    $range = NumberRange::factory()->create([
        'tenant_id' => $tenantId,
        'document_type' => DocumentType::Quote,
        'starting_counter' => 1,
    ]);

    app(AllocateDocumentNumber::class)($tenantId, 2026, DocumentType::Quote);
    // Raising starting_counter directly (bypassing UpdateNumberRange's own
    // refusal, which is proven separately) to show the allocator itself
    // only ever consults it when nothing has landed yet for the period.
    $range->update(['starting_counter' => 5000]);

    $second = app(AllocateDocumentNumber::class)($tenantId, 2026, DocumentType::Quote);

    expect($second)->toBe(['number' => 'AN-2026-2', 'year' => 2026, 'counter' => 2]);
});

it('previews the next number without allocating it', function () {
    $tenantId = (string) Str::uuid();
    $range = NumberRange::factory()->create([
        'tenant_id' => $tenantId,
        'document_type' => DocumentType::Quote,
        'starting_counter' => 1,
    ]);

    $before = app(AllocateDocumentNumber::class)->preview($range);
    $beforeAgain = app(AllocateDocumentNumber::class)->preview($range);

    app(AllocateDocumentNumber::class)($tenantId, now('UTC')->year, DocumentType::Quote);
    $after = app(AllocateDocumentNumber::class)->preview($range->fresh());

    expect($before)->toBe($beforeAgain)
        ->and(NumberCounter::where('tenant_id', $tenantId)->count())->toBe(1)
        ->and($after)->not->toBe($before);
});

it('never hands out the same quote number twice under real concurrent access', function () {
    // A single PHP process can't have two allocations genuinely in flight
    // at once, so this fires real, separate OS processes at the same
    // tenant/year — each with its own DB connection — and lets the actual
    // race happen at the database rather than simulating one. Runs against
    // the seeded tenant rather than a fresh one, because a fresh tenant's
    // NumberRange row would only exist inside this test's own uncommitted
    // transaction — invisible to a genuinely separate process — while the
    // seeded tenant's ranges were committed for real by the migration.
    $tenantId = config('tenancy.tenant_id');
    $year = 2222;
    $attempts = 20;
    $database = config('database.connections.pgsql.database');

    $pool = Process::pool(function ($pool) use ($tenantId, $year, $attempts, $database) {
        for ($i = 0; $i < $attempts; $i++) {
            $pool->as($i)->path(base_path())->timeout(30)->command([
                'php', 'tests/Support/allocate_document_number_cli.php', $tenantId, (string) $year, 'quote',
                $database,
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
        fn ($result) => expect($result->errorOutput())->toContain('quote_number_counters_tenant_id_document_type_year_counter')
    );

    // The database agrees with what the processes reported: exactly one row
    // per successful allocation, and every counter value handed back is
    // actually there. Real subprocesses commit for real on their own
    // connections — RefreshDatabase's rollback of the main test connection
    // never touches them — so this also cleans up after itself.
    expect(NumberCounter::where('tenant_id', $tenantId)->where('document_type', 'quote')->where('year', $year)->count())
        ->toBe($succeeded->count());

    NumberCounter::where('tenant_id', $tenantId)->where('document_type', 'quote')->where('year', $year)->delete();
});

it('never hands out the same number twice for a second document type under real concurrent access', function () {
    // Q-05 proved this for quote specifically. T-04's refactor is only worth
    // shipping if the same guarantee holds for a range it didn't originally
    // know about — so this repeats the exact proof above for contract, the
    // seeded tenant's other range, and nothing else changes.
    $tenantId = config('tenancy.tenant_id');
    $year = 2223;
    $attempts = 20;
    $database = config('database.connections.pgsql.database');

    $pool = Process::pool(function ($pool) use ($tenantId, $year, $attempts, $database) {
        for ($i = 0; $i < $attempts; $i++) {
            $pool->as($i)->path(base_path())->timeout(30)->command([
                'php', 'tests/Support/allocate_document_number_cli.php', $tenantId, (string) $year, 'contract',
                $database,
            ]);
        }
    })->start();

    $results = $pool->wait()->collect();

    $succeeded = $results->filter->successful();
    $failed = $results->reject->successful();

    $numbers = $succeeded->map(fn ($result) => json_decode($result->output(), true)['number']);

    expect($succeeded->count())->toBeGreaterThan(0)
        ->and($numbers->count())->toBe($numbers->unique()->count());

    $failed->each(
        fn ($result) => expect($result->errorOutput())->toContain('quote_number_counters_tenant_id_document_type_year_counter')
    );

    expect(NumberCounter::where('tenant_id', $tenantId)->where('document_type', 'contract')->where('year', $year)->count())
        ->toBe($succeeded->count());

    NumberCounter::where('tenant_id', $tenantId)->where('document_type', 'contract')->where('year', $year)->delete();
});
