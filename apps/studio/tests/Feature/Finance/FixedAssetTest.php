<?php

use App\Finance\Models\Expense;
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

function assetPayload(array $overrides = []): array
{
    return [
        'name' => 'Mobiler Rechner',
        'acquired_at' => '2026-04-10',
        'cost_net' => 145000,
        'cost_gross' => 172550,
        'method' => 'linear',
        'useful_life_months' => 12,
        'afa_category' => 'computer_software',
        ...$overrides,
    ];
}

it('makes an asset with the next inventory number of its acquisition year', function () {
    $this->actingAs(staffMember(), 'staff');
    $this->travelTo('2026-11-22');

    $this->post('/finanzen/ausgaben', expensePayload(['asset' => assetPayload()]))->assertRedirect();
    $this->post('/finanzen/ausgaben', expensePayload(['asset' => assetPayload(['name' => 'Objektiv', 'cost_net' => 64000, 'cost_gross' => 76160, 'method' => 'gwg', 'useful_life_months' => null, 'afa_category' => null])]))->assertRedirect();
    $this->post('/finanzen/ausgaben', expensePayload(['asset' => assetPayload(['acquired_at' => '2025-12-30'])]))->assertRedirect();

    expect(FixedAsset::query()->orderBy('id')->pluck('inventory_no')->all())->toBe(['INV-26-001', 'INV-26-002', 'INV-25-001'])
        ->and(Expense::query()->whereNotNull('asset_id')->count())->toBe(3);
});

it('refuses a GWG above the limit and a linear asset without a life', function () {
    $this->actingAs(staffMember(), 'staff');

    $this->post('/finanzen/ausgaben', expensePayload(['asset' => assetPayload(['method' => 'gwg', 'cost_net' => 80001, 'cost_gross' => 95001])]))
        ->assertSessionHasErrors(['asset.method' => __('finance.gwgLimit')]);
    $this->post('/finanzen/ausgaben', expensePayload(['asset' => assetPayload(['useful_life_months' => null, 'afa_category' => null])]))
        ->assertSessionHasErrors(['asset.useful_life_months' => 'Das Feld Nutzungsdauer (Monate) muss ausgefüllt werden.']);
    $this->post('/finanzen/ausgaben', expensePayload(['asset' => assetPayload(['method' => 'gwg', 'cost_net' => 80000, 'cost_gross' => 95200])]))
        ->assertSessionHasNoErrors();

    expect(FixedAsset::query()->count())->toBe(1);
});

it('applies the gross limit in a small business year', function () {
    FiscalYear::factory()->create(['year' => 2026, 'vat_mode' => 'small_business']);
    $this->actingAs(staffMember(), 'staff');

    $this->post('/finanzen/ausgaben', expensePayload(['asset' => assetPayload(['method' => 'gwg', 'cost_net' => 70000, 'cost_gross' => 83300])]))
        ->assertSessionHasErrors('asset.method');
});

it('takes the life from the AfA category and follows the expense’s business share', function () {
    $this->actingAs(staffMember(), 'staff');
    $this->post('/finanzen/ausgaben', expensePayload(['asset' => assetPayload(['useful_life_months' => null])]));
    $expense = Expense::query()->sole();

    expect($expense->asset->useful_life_months)->toBe(12);

    $this->put("/finanzen/ausgaben/{$expense->public_id}", expensePayload(['business_share' => 40]))->assertRedirect();

    expect($expense->asset()->first()->business_share)->toBe(40);
});

it('removes the asset when it is switched off, unless it was disposed', function () {
    $this->actingAs(staffMember(), 'staff');
    $this->post('/finanzen/ausgaben', expensePayload(['book' => true, 'asset' => assetPayload()]));
    $expense = Expense::query()->sole();

    $this->post("/finanzen/anlagen/{$expense->asset->public_id}/abgang", ['disposed_at' => '2026-05-01', 'disposal_proceeds' => 0]);
    $this->put("/finanzen/ausgaben/{$expense->public_id}", expensePayload(['asset' => null]))->assertSessionHasErrors('asset');

    $this->delete("/finanzen/anlagen/{$expense->asset->public_id}/abgang");
    $this->put("/finanzen/ausgaben/{$expense->public_id}", expensePayload(['asset' => null]))->assertSessionHasNoErrors();

    expect(FixedAsset::query()->count())->toBe(0)
        ->and($expense->fresh()->asset_id)->toBeNull();
});

it('disposes an asset with a date after acquisition and not in the future, and clears it again', function () {
    $this->actingAs(staffMember(), 'staff');
    $this->travelTo('2026-11-22');
    $this->post('/finanzen/ausgaben', expensePayload(['book' => true, 'asset' => assetPayload()]));
    $asset = FixedAsset::query()->sole();

    $this->post("/finanzen/anlagen/{$asset->public_id}/abgang", ['disposed_at' => '2026-04-09', 'disposal_proceeds' => 100])->assertSessionHasErrors(['disposed_at' => __('finance.beforeAcquisition')]);
    $this->post("/finanzen/anlagen/{$asset->public_id}/abgang", ['disposed_at' => '2026-12-01', 'disposal_proceeds' => 100])->assertSessionHasErrors('disposed_at');
    $this->post("/finanzen/anlagen/{$asset->public_id}/abgang", ['disposed_at' => '2026-06-30', 'disposal_proceeds' => -1])->assertSessionHasErrors('disposal_proceeds');
    $this->post("/finanzen/anlagen/{$asset->public_id}/abgang", ['disposed_at' => '2026-06-30', 'disposal_proceeds' => 0])->assertSessionHasNoErrors();

    expect($asset->fresh()->disposal_proceeds)->toBe(0);

    $this->delete("/finanzen/anlagen/{$asset->public_id}/abgang")->assertRedirect();
    expect($asset->fresh()->disposed_at)->toBeNull();
});

it('lists booked assets with their book value at the end of the year and drops disposed ones afterwards', function () {
    $this->actingAs(staffMember(), 'staff');
    $this->travelTo('2027-03-01');
    $this->post('/finanzen/ausgaben', expensePayload(['book' => true, 'asset' => assetPayload()]));
    $this->post('/finanzen/ausgaben', expensePayload(['asset' => assetPayload(['name' => 'Entwurf'])]));
    $this->post('/finanzen/ausgaben', expensePayload(['book' => true, 'asset' => assetPayload(['name' => 'Blitz', 'acquired_at' => '2026-03-01', 'cost_net' => 90000, 'cost_gross' => 107100, 'method' => 'linear', 'useful_life_months' => 36, 'afa_category' => null])]));
    $blitz = FixedAsset::query()->where('name', 'Blitz')->sole();
    $this->post("/finanzen/anlagen/{$blitz->public_id}/abgang", ['disposed_at' => '2027-02-01', 'disposal_proceeds' => 30000]);

    $y2026 = props('/finanzen/anlagen?jahr=2026');
    $y2027 = props('/finanzen/anlagen?jahr=2027');
    $y2028 = props('/finanzen/anlagen?jahr=2028');

    expect(array_column($y2026['rows'], 'name'))->toBe(['Blitz', 'Mobiler Rechner'])
        ->and(array_column($y2026['rows'], 'bookValue'))->toBe([65000, 36250])
        ->and(array_column($y2027['rows'], 'name'))->toBe(['Blitz', 'Mobiler Rechner'])
        ->and(array_column($y2028['rows'], 'name'))->toBe(['Mobiler Rechner'])
        ->and($y2027['rows'][0]['disposedAt'])->toBe('2027-02-01')
        ->and($y2028['rows'][0]['bookValue'])->toBe(0)
        ->and(integerIdPaths($y2026))->toBe([]);
});
