<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// integerIdPaths() lives in tests/Pest.php — see the note there.

it('never puts an integer identifier into an authenticated inertia page prop', function () {
    $user = User::factory()->withMfa()->create();

    $props = $this->actingAs($user, 'staff')->get('/')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into the login page', function () {
    $props = $this->get('/login')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('exposes the signed-in user as a public id', function () {
    $user = User::factory()->withMfa()->create();

    $props = $this->actingAs($user, 'staff')->get('/')->assertOk()->viewData('page')['props'];

    expect($props['auth']['user']['id'])->toBe($user->public_id)
        ->and($props['auth']['user'])->not->toHaveKey('mfa_secret');
});

it('never puts an integer identifier into any crm page', function (string $path) {
    $user = User::factory()->withMfa()->create();

    $props = $this->actingAs($user, 'staff')->get($path)->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
})->with(['/anfragen', '/anfragen/neu', '/kontakte']);

it('never puts an integer identifier into a project page', function (string $suffix) {
    // Populated on purpose: an empty project proves nothing, since the keys
    // that escape belong to the shoots, the crew, the notes and the job.
    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->primary()->create();
    CrewAssignment::factory()->for($shoot)->create();
    InternalNote::factory()->for($project)->create();
    Job::factory()->for($project)->create();

    $props = $this->actingAs($user, 'staff')
        ->get("/auftraege/{$project->public_id}{$suffix}")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
})->with(['', '/shootings']);

it('detects an integer id when one is present', function () {
    expect(integerIdPaths(['data' => ['id' => 7, 'name' => 'x']]))->toBe(['$.data.id']);
    expect(integerIdPaths(['data' => ['id' => 'abc', 'name' => 'x']]))->toBe([]);
});

it('never puts an integer identifier into the portal switcher', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create();
    $first = Project::factory()->create(['portal_visible' => true]);
    $second = Project::factory()->create(['portal_visible' => true]);
    $first->attachContact($contact, ProjectContactRole::Primary);
    $second->attachContact($contact, ProjectContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into a portal project screen', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true]);
    $project->attachContact($contact, ProjectContactRole::Primary);
    Shoot::factory()->for($project)->primary()->create();

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});
