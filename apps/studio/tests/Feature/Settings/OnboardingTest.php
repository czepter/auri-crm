<?php

use App\Automation\Models\AutomationRule;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Enums\StaffRole;
use App\Settings\Models\StudioSettings;
use App\Settings\Support\StudioSettingsResolver;
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
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->from('/einstellungen')
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

it('writes the starter templates on finish, the invoice aside', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/einrichtung/studio/abschluss')
        ->assertRedirect('/');

    expect(DocumentTemplate::query()->current()->ofType(DocumentTemplateType::Quote)->pluck('name')->all())
        ->toBe(['Angebot'])
        ->and(DocumentTemplate::query()->current()->ofType(DocumentTemplateType::Invoice)->exists())
        ->toBeFalse();
});

it('seeds the default automation rules on finish, in the studio register', function () {
    app(StudioSettingsResolver::class)->set('client_register', 'informal', null);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/einrichtung/studio/abschluss')
        ->assertRedirect('/');

    expect(AutomationRule::query()->pluck('key')->all())
        ->toEqualCanonicalizing(collect(config('automation.rules'))->pluck('key')->all())
        ->and(AutomationRule::query()->where('key', 'booking-confirmation')->value('notification_subject'))
        ->toBe('Deine Buchung ist bestätigt');
});

it('writes the Kleinunternehmer invoice when the first invoice is drafted in that mode', function () {
    app(StudioSettingsResolver::class)->set('vat_mode', 'small_business', null);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/einrichtung/studio/abschluss')
        ->assertRedirect('/');

    expect(DocumentTemplate::query()->current()->ofType(DocumentTemplateType::Invoice)->exists())->toBeFalse();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/rechnungen/neu')
        ->assertOk();

    expect(DocumentTemplate::query()->current()->ofType(DocumentTemplateType::Invoice)->pluck('name')->all())
        ->toBe(['Rechnung — Kleinunternehmer']);
});

it('opens the wizard screen itself without redirecting', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einrichtung/studio')
        ->assertOk();
});
