<?php

use App\Crm\Models\ClientCase;
use App\Models\User;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

// The web middleware group starts a database-backed session on every request.
uses(RefreshDatabase::class);

it('renders the login page as an inertia component', function () {
    // A staff account has to exist for /login to render at all — an empty
    // table sends every visitor to /einrichtung instead. See SetupTest.
    User::factory()->create();

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('names a page component that exists on disk', function (string $path, string $component) {
    // main.ts resolves the component from a glob, so a typo in the name a
    // controller passes fails in the browser and nowhere else. This is the
    // cheapest place to catch the whole class of it.
    $user = User::factory()->withMfa()->create();
    $project = ClientCase::factory()->create();

    $this->actingAs($user, 'staff')
        ->get(str_replace('{project}', $project->public_id, $path))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));

    expect(resource_path("js/admin/pages/{$component}.vue"))->toBeFile();
})->with([
    ['/', 'Uebersicht'],
    ['/anfragen', 'Leads/Board'],
    ['/kontakte', 'Contacts/Index'],
    ['/kalender', 'Kalender'],
    ['/team', 'Staff/Index'],
    ['/auftraege/{project}', 'Projects/Show'],
    ['/auftraege/{project}/shootings', 'Projects/Shoots'],
]);

it('renders through the admin root view', function () {
    User::factory()->create();

    $response = $this->get('/login')->assertOk();

    expect($response->original->name())->toBe('app-admin');
});

it('shares the studio name rather than a hardcoded one', function () {
    // The admin wordmark and every sign-in screen read this prop, all of them
    // with nobody signed in yet.
    $user = User::factory()->create();
    (new StudioSettingsResolver)->set('display_name', 'Atelier Weitwinkel', $user);

    $this->get('/login')
        ->assertOk()
        ->assertSee('<title inertia>Atelier Weitwinkel</title>', false)
        ->assertInertia(fn (Assert $page) => $page->where('brand', 'Atelier Weitwinkel'));
});
