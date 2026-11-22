<?php

use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Finance\Models\Expense;
use App\Finance\Models\ExpenseCategory;
use App\Finance\Models\FinanceDocument;
use App\Finance\Models\FiscalYear;
use App\Finance\Models\FixedAsset;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('ships the ten categories without SKR04 numbers', function () {
    expect(ExpenseCategory::query()->orderBy('sort_order')->pluck('key')->all())->toBe([
        'software_abos', 'hosting_domains', 'werbung', 'weiterbildung', 'fotozubehoer',
        'buero', 'reise', 'versicherung', 'gebuehren', 'sonstiges',
    ])
        ->and(ExpenseCategory::query()->whereNotNull('skr04_account')->count())->toBe(0)
        ->and(ExpenseCategory::query()->where('key', 'reise')->value('euer_line'))->toBe('reisekosten');
});

it('refuses rows the constraints forbid', function (string $table, array $row) {
    $defaults = match ($table) {
        'expenses' => ['public_id' => 'a'.substr(md5(serialize($row)), 0, 23), 'net' => 100, 'vat' => 19, 'gross' => 119, 'business_share' => 100, 'status' => 'draft'],
        'fixed_assets' => ['public_id' => 'b'.substr(md5(serialize($row)), 0, 23), 'inventory_no' => 'INV-26-001', 'name' => 'x', 'acquired_at' => '2026-01-01', 'cost_net' => 1, 'cost_gross' => 1, 'method' => 'gwg', 'business_share' => 100],
        'fiscal_years' => ['public_id' => 'c'.substr(md5(serialize($row)), 0, 23), 'year' => 2026, 'vat_mode' => 'standard'],
    };

    expect(fn () => DB::table($table)->insert([...$defaults, ...$row, 'tenant_id' => config('tenancy.tenant_id')]))
        ->toThrow(QueryException::class);
})->with([
    'gross differs from net plus vat' => ['expenses', ['gross' => 120]],
    'share above 100' => ['expenses', ['business_share' => 101]],
    'unknown expense status' => ['expenses', ['status' => 'paid']],
    'unknown method' => ['fixed_assets', ['method' => 'degressive']],
    'linear without months' => ['fixed_assets', ['method' => 'linear', 'useful_life_months' => 0]],
    'negative proceeds' => ['fixed_assets', ['disposal_proceeds' => -1]],
    'unknown vat mode' => ['fiscal_years', ['vat_mode' => 'regular']],
]);

it('keeps one document per hash', function () {
    FinanceDocument::factory()->count(2)->create(['sha256' => null]);
    FinanceDocument::factory()->create(['sha256' => str_repeat('a', 64)]);

    expect(fn () => FinanceDocument::factory()->create(['sha256' => str_repeat('a', 64)]))->toThrow(QueryException::class);
});

it('keeps one inventory number per tenant', function () {
    FixedAsset::factory()->create(['inventory_no' => 'INV-25-001']);

    expect(fn () => FixedAsset::factory()->create(['inventory_no' => 'INV-25-001']))->toThrow(QueryException::class);
});

it('hides another tenant’s rows', function () {
    Expense::factory()->create();
    DB::table('expenses')->insert([
        'tenant_id' => '00000000-0000-4000-8000-00000000dead', 'public_id' => 'dddddddddddddddddddddddd',
        'net' => 0, 'vat' => 0, 'gross' => 0,
    ]);

    expect(Expense::query()->count())->toBe(1);
});

it('refuses writes dated in a locked year at model level', function () {
    $open = Expense::factory()->create(['paid_at' => '2026-02-01']);
    $old = Expense::factory()->create(['paid_at' => '2025-06-01']);
    FiscalYear::factory()->locked()->create(['year' => 2025]);

    expect(fn () => Expense::factory()->create(['paid_at' => '2025-06-01']))->toThrow(ValidationException::class)
        ->and(fn () => $open->update(['paid_at' => '2025-12-31']))->toThrow(ValidationException::class)
        ->and(fn () => $old->update(['note' => 'x']))->toThrow(ValidationException::class)
        ->and(fn () => $old->delete())->toThrow(ValidationException::class)
        ->and(fn () => FixedAsset::factory()->create(['acquired_at' => '2025-03-10']))->toThrow(ValidationException::class);
});

it('lets an asset of a locked year be disposed in an open year only', function () {
    $asset = FixedAsset::factory()->create(['acquired_at' => '2025-03-10']);
    FiscalYear::factory()->locked()->create(['year' => 2025]);

    $asset->update(['disposed_at' => '2026-02-01', 'disposal_proceeds' => 100]);

    expect(fn () => $asset->update(['name' => 'Umbenannt']))->toThrow(ValidationException::class);

    $asset->refresh()->update(['disposed_at' => null, 'disposal_proceeds' => null]);
    expect(fn () => $asset->update(['disposed_at' => '2025-12-01']))->toThrow(ValidationException::class);
});

it('gives only the admin role the finance capability', function (StaffRole $role, bool $allowed) {
    expect(staffMember($role)->can(Capability::ManageFinances->value))->toBe($allowed)
        ->and($role->allows(Capability::ManageFinances))->toBe($allowed);
})->with([
    [StaffRole::Admin, true],
    [StaffRole::Manager, false],
    [StaffRole::Contributor, false],
    [StaffRole::Reader, false],
]);
