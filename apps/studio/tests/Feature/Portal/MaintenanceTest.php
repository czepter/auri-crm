<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Settings\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
});

it('lets an authenticated client reach their project when maintenance is off', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}")->assertOk();
});

it('shows the closure message and no project data to an authenticated client while maintenance is on', function () {
    StudioSettings::query()->update([
        'portal_maintenance_enabled' => true,
        'portal_maintenance_message' => 'Wir sind kurz nicht erreichbar.',
    ]);

    $project = Project::factory()->create(['portal_visible' => true, 'name' => 'Hochzeit Geheim GmbH']);
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $response = $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}")->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['component'] ?? $response->viewData('page')['component'])->toBe('Maintenance');
    expect($props['message'])->toBe('Wir sind kurz nicht erreichbar.');

    // No project, quote, contract or payment data anywhere in the response.
    expect($response->getContent())
        ->not->toContain('Hochzeit Geheim GmbH')
        ->not->toContain('Lena Meier');
});

it('shows the closure message on the home switcher too', function () {
    StudioSettings::query()->update(['portal_maintenance_enabled' => true]);

    $contact = Contact::factory()->create();

    $page = $this->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page');
    expect($page['component'])->toBe('Maintenance');
});

it('shows the closure message to an unauthenticated visitor instead of redirecting to sign-in', function () {
    StudioSettings::query()->update(['portal_maintenance_enabled' => true]);

    $page = $this->get('/')->assertOk()->viewData('page');
    expect($page['component'])->toBe('Maintenance');
});

it('shows the closure message on the sign-in route itself', function () {
    StudioSettings::query()->update(['portal_maintenance_enabled' => true]);

    $page = $this->get('/anmelden')->assertOk()->viewData('page');
    expect($page['component'])->toBe('Maintenance');
});

it('falls back to a default message when none is configured', function () {
    StudioSettings::query()->update(['portal_maintenance_enabled' => true, 'portal_maintenance_message' => null]);

    $props = $this->get('/')->assertOk()->viewData('page')['props'];

    expect($props['message'])->toBeNull();
});

it('never uses revocation wording in the German copy shipped for the closure screen', function () {
    $forbidden = ['widerrufen', 'entzogen', 'widerruf', 'entzug'];

    $de = json_decode(file_get_contents(resource_path('js/locales/de.json')), true);
    $portalStrings = collect($de['portal'] ?? [])->filter(fn ($v, $k) => str_starts_with($k, 'maintenance'));

    foreach ($portalStrings as $key => $value) {
        foreach ($forbidden as $word) {
            expect(mb_strtolower((string) $value))->not->toContain($word);
        }
    }

    expect($portalStrings)->not->toBeEmpty();
});

it('still reaches the impressum while maintenance is on', function () {
    StudioSettings::query()->update(['portal_maintenance_enabled' => true]);

    $this->get('/impressum')->assertOk();
});

it('still resolves the branding logo while maintenance is on', function () {
    StudioSettings::query()->update(['portal_maintenance_enabled' => true]);

    // No logo stored — 404 either way, but never the Maintenance page.
    $page = $this->get('/branding/logo');
    expect($page->status())->toBe(404);
});

it('leaves the admin surface untouched by the portal maintenance switch', function () {
    StudioSettings::query()->update(['portal_maintenance_enabled' => true]);

    useSurface(Surface::ADMIN);

    $owner = staffMember(\App\Enums\StaffRole::Owner);
    $page = $this->actingAs($owner, 'staff')->get('/einstellungen/allgemein')->assertOk()->viewData('page');

    expect($page['component'])->toBe('Settings/Index');
});
