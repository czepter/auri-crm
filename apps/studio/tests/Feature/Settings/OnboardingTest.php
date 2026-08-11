<?php

use App\Enums\StaffRole;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// Runs after tests/Pest.php's own global beforeEach (which stamps every
// studio as already onboarded) — this file exercises the unfinished state
// on purpose, so it resets the flag back. The cache flush is required for
// the same reason the global hook now carries one: a raw query()->update()
// never busts StudioSettingsResolver's redis-backed cache.
beforeEach(function () {
    StudioSettings::query()->update(['onboarding_completed_at' => null]);
    Cache::store('redis')->flush();
});

it('redirects a GET to the wizard while onboarding is unfinished', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/')
        ->assertRedirect('/einrichtung/studio');
});

it('does not redirect a PATCH to a settings endpoint — the wizard must be able to save itself', function () {
    // tax_number/vat_id and the three bank fields are required on every save
    // regardless of onboarding (BankDetailsRequirement::isRequired()) — a
    // payload short of those fails validation for reasons unrelated to this
    // middleware and back()s to '/' (no referer header in a test request),
    // which would make this assertion indistinguishable from the redirect
    // this test exists to rule out.
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen', [
            'display_name' => 'Studio Zepter',
            'tax_number' => '22/123/45678',
            'bank_account_holder' => 'Studio Zepter',
            'bank_iban' => 'DE89 3704 0044 0532 0130 00',
            'bank_bic' => 'COBADEFFXXX',
        ])
        ->assertRedirect('/einstellungen');
});

it('does not redirect a role without ManageSettings', function () {
    // Photographer holds no Capability::ManageSettings (StaffRole::capabilities()),
    // so AuthorizesStaffSettings would 404 them on every settings screen anyway —
    // RequireStudioOnboarding must not catch them in its redirect either.
    $this->actingAs(staffMember(StaffRole::Photographer), 'staff')
        ->get('/')
        ->assertOk();
});

it('stamps completion and releases the dashboard', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/einrichtung/studio/abschluss')
        ->assertRedirect('/');

    expect(StudioSettings::query()->sole()->onboarding_completed_at)->not->toBeNull();

    $this->get('/')->assertOk();
});

it('opens the wizard screen itself without redirecting', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einrichtung/studio')
        ->assertOk();
});
