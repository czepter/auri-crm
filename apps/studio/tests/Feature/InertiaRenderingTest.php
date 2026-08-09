<?php

use App\Crm\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

// The web middleware group starts a database-backed session on every request.
uses(RefreshDatabase::class);

it('renders the login page as an inertia component', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('names a page component that exists on disk', function (string $path, string $component) {
    // main.ts resolves the component from a glob, so a typo in the name a
    // controller passes fails in the browser and nowhere else. This is the
    // cheapest place to catch the whole class of it.
    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();

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
    ['/auftraege/{project}', 'Projects/Show'],
    ['/auftraege/{project}/shootings', 'Projects/Shoots'],
]);

it('renders through the admin root view', function () {
    $response = $this->get('/login')->assertOk();

    expect($response->original->name())->toBe('app-admin');
});
