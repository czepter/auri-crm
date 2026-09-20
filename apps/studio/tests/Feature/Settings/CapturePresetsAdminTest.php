<?php

use App\Enums\StaffRole;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::store('redis')->flush());

it('stores the Sonstige suggestions trimmed, without blanks and without repeats', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/erfassung', [
            'lead_type_presets' => ['Tierfotografie', '  Firmenevent  ', '', 'Tierfotografie'],
            'lead_source_presets' => ['Zeitungsartikel'],
            'lead_channel_presets' => [],
        ])
        ->assertRedirect('/einstellungen/erfassung');

    $row = StudioSettings::query()->sole();

    expect($row->lead_type_presets)->toBe(['Tierfotografie', 'Firmenevent'])
        ->and($row->lead_source_presets)->toBe(['Zeitungsartikel'])
        ->and($row->lead_channel_presets)->toBe([]);
});

it('offers the stored suggestions on the capture screen', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/erfassung', ['lead_type_presets' => ['Tierfotografie']]);

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/anfragen/neu')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['presets']['type'])->toBe(['Tierfotografie']);
});
