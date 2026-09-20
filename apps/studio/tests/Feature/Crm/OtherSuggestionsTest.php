<?php

use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Models\Lead;
use App\Crm\Support\OtherSuggestions;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('suggests each Sonstige text once, the most used first, without blanks', function () {
    Lead::factory()->create(['source' => LeadSource::Other, 'source_other' => 'Zeitungsartikel']);
    Lead::factory()->count(2)->create(['source' => LeadSource::Other, 'source_other' => 'Firmenevent']);
    Lead::factory()->create(['source' => LeadSource::Other, 'source_other' => '']);
    Lead::factory()->create(['source' => LeadSource::Website, 'captured_via' => CaptureChannel::Other, 'captured_via_other' => 'Messe']);

    expect(OtherSuggestions::for('source'))->toBe(['Firmenevent', 'Zeitungsartikel'])
        ->and(OtherSuggestions::for('channel'))->toBe(['Messe']);

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/anfragen/neu')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['presets']['source'])->toBe(['Firmenevent', 'Zeitungsartikel']);
});
