<?php

use App\Finance\Models\FiscalYear;
use App\Finance\Models\FixedAsset;
use App\Finance\Support\Depreciation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('takes the gross as cost basis in a small business year and the net otherwise', function () {
    FiscalYear::factory()->create(['year' => 2025, 'vat_mode' => 'small_business']);
    FiscalYear::factory()->create(['year' => 2026, 'vat_mode' => 'standard']);

    $small = FixedAsset::factory()->create(['acquired_at' => '2025-03-10', 'cost_net' => 100000, 'cost_gross' => 119000]);
    $standard = FixedAsset::factory()->create(['acquired_at' => '2026-03-10', 'cost_net' => 100000, 'cost_gross' => 119000]);

    expect($small->basis())->toBe(119000)
        ->and($standard->basis())->toBe(100000)
        ->and(Depreciation::of($small)->yearly(2025))->toBe(33056);
});
