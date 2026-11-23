<?php

use App\Finance\Models\Expense;
use App\Finance\Models\FiscalYear;
use App\Finance\Models\FixedAsset;
use App\Finance\Support\EuerReport;
use Database\Seeders\DemoCrmSeeder;
use Database\Seeders\DemoFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-22 10:00:00');
    Bus::fake();
    Storage::fake('s3');
    Storage::fake('local');
    $this->seed(DemoCrmSeeder::class);
    $this->seed(DemoFinanceSeeder::class);
});

it('seeds the current year to figures worked out by hand', function () {
    $report = app(EuerReport::class)->forYear(2026);

    expect($report->vatMode)->toBe('standard')
        ->and($report->operatingIncome)->toBe(380000)
        ->and($report->collectedVat)->toBe(72200)
        ->and($report->assetSales)->toBe(30000)
        ->and($report->income)->toBe(482200)
        ->and(array_column($report->categories, 'amount', 'key'))->toEqual([
            'software_abos' => 6512, 'hosting_domains' => 10000, 'fotozubehoer' => 11000, 'sonstiges' => 2500,
            'reise' => 8318, 'versicherung' => 31200, 'weiterbildung' => 45000,
        ])
        ->and($report->depreciation)->toBe(285800)
        ->and($report->residualBookValue)->toBe(59500)
        ->and($report->paidInputVat)->toBe(43617)
        ->and($report->profit)->toBe(482200 - (114530 + 285800 + 59500 + 43617))
        ->and($report->checklist->toArray())->toBe([
            'withoutReceipt' => 1, 'drafts' => 3, 'openInvoices' => 1, 'openInvoicesRemaining' => 142800, 'foreignWithoutEur' => 1,
        ]);
});

it('locks last year with a snapshot and leaves the year before open with one draft', function () {
    $locked = FiscalYear::query()->where('year', 2025)->sole();

    expect($locked->vat_mode)->toBe('small_business')
        ->and($locked->isLocked())->toBeTrue()
        ->and($locked->snapshot['report']['income'])->toBeGreaterThan(0)
        ->and(FiscalYear::query()->where('year', 2024)->whereNotNull('locked_at')->exists())->toBeFalse()
        ->and(Expense::query()->inYear(2024)->where('status', 'draft')->count())->toBe(1);
});

it('numbers the assets and disposes last year’s flash set this year', function () {
    expect(FixedAsset::query()->orderBy('id')->pluck('inventory_no')->all())->toContain('INV-26-001', 'INV-26-002', 'INV-25-001', 'INV-25-002')
        ->and(FixedAsset::query()->where('name', 'Blitzanlage')->sole()->disposed_at->toDateString())->toBe('2026-06-30');
});

it('does nothing the second time', function () {
    $count = Expense::query()->count();

    $this->seed(DemoFinanceSeeder::class);

    expect(Expense::query()->count())->toBe($count);
});
