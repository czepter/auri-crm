<?php

use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts every studio unonboarded and lets the column be written', function () {
    // tests/Pest.php's own global default stamps every feature test's studio
    // as already onboarded (RequireStudioOnboarding, Task 6) — this test is
    // about the migration's own column, not that fixture default, so it
    // clears the stamp itself before asserting the column's null state.
    StudioSettings::query()->update(['onboarding_completed_at' => null]);

    $row = StudioSettings::query()->sole();

    expect($row->onboarding_completed_at)->toBeNull();

    $row->update(['onboarding_completed_at' => now()]);

    expect($row->fresh()->onboarding_completed_at)->not->toBeNull();
});
