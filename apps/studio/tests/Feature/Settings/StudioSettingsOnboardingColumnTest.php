<?php

use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts every studio unonboarded and lets the column be written', function () {
    $row = StudioSettings::query()->sole();

    expect($row->onboarding_completed_at)->toBeNull();

    $row->update(['onboarding_completed_at' => now()]);

    expect($row->fresh()->onboarding_completed_at)->not->toBeNull();
});
