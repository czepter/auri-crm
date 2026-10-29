<?php

use App\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    // StudioSettingsResolver caches the settings row (T-01) — these tests
    // write straight to the row via the model, bypassing the resolver's own
    // cache-busting set(), so the cache needs clearing itself.
    Cache::store('redis')->flush();
});

it('renders the impressum from the studio\'s own T-02 particulars', function () {
    StudioSettings::query()->update([
        'legal_name' => 'Atelier Nordlicht Fotografie GmbH',
        'legal_form' => 'GmbH',
        'street' => 'Elbchaussee 12',
        'postcode' => '22765',
        'city' => 'Hamburg',
        'country' => 'Deutschland',
        'tax_number' => '22/123/45678',
        'vat_id' => 'DE123456789',
        'register_number' => 'HRB 123456',
        'register_court' => 'Amtsgericht Hamburg',
        'representative' => 'Christian Bergmann',
        'website' => 'https://atelier-nordlicht.de',
    ]);

    $props = $this->get('/impressum')->assertOk()->viewData('page')['props'];

    expect($props['name'])->toBe('Atelier Nordlicht Fotografie GmbH')
        ->and($props['street'])->toBe('Elbchaussee 12')
        ->and($props['postcode'])->toBe('22765')
        ->and($props['city'])->toBe('Hamburg')
        ->and($props['taxNumber'])->toBe('22/123/45678')
        ->and($props['vatId'])->toBe('DE123456789')
        ->and($props['registerNumber'])->toBe('HRB 123456')
        ->and($props['registerCourt'])->toBe('Amtsgericht Hamburg')
        ->and($props['representative'])->toBe('Christian Bergmann');
});

it('falls back to the display name when no legal name is set, matching studio.name\'s own merge-field fallback', function () {
    StudioSettings::query()->update(['legal_name' => null, 'display_name' => 'Atelier Nordlicht']);

    $props = $this->get('/impressum')->assertOk()->viewData('page')['props'];

    expect($props['name'])->toBe('Atelier Nordlicht');
});

it('is reachable without a portal session', function () {
    $this->get('/impressum')->assertOk();
});

it('never puts an integer identifier into the impressum response', function () {
    $props = $this->get('/impressum')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});
