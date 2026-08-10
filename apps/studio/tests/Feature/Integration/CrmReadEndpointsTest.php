<?php

use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

/** A bearer header for a freshly minted ApiToken scoped to exactly these capabilities. */
function bearerFor(Capability ...$scopes): string
{
    $token = ApiToken::factory()->withScopes(...$scopes)->create();

    return 'Bearer '.$token->createToken('t')->plainTextToken;
}

it('lists each CRM resource behind its matching scope', function (string $path, Capability $scope) {
    $this->withHeader('Authorization', bearerFor($scope))->getJson($path)->assertOk();
})->with([
    'contacts' => ['/api/contacts', Capability::ManageContacts],
    'projects' => ['/api/projects', Capability::ViewAllProjects],
    'leads' => ['/api/leads', Capability::ManageLeads],
    'shoots' => ['/api/shoots', Capability::ViewAllProjects],
    'jobs' => ['/api/jobs', Capability::ViewAllProjects],
]);

it('refuses each CRM resource to a token that does not hold the matching scope', function (string $path) {
    // Holds a capability, just never the one the route requires.
    $this->withHeader('Authorization', bearerFor(Capability::ManageStaff))
        ->getJson($path)
        ->assertForbidden();
})->with([
    'contacts' => ['/api/contacts'],
    'projects' => ['/api/projects'],
    'leads' => ['/api/leads'],
    'shoots' => ['/api/shoots'],
    'jobs' => ['/api/jobs'],
]);

it('refuses an unauthenticated caller', function () {
    $this->getJson('/api/contacts')->assertUnauthorized();
});

it('never returns an internal integer id for any CRM resource', function (string $path, Capability $scope, Closure $seed) {
    $seed();

    $body = $this->withHeader('Authorization', bearerFor($scope))->getJson($path)->assertOk()->json();

    expect(integerIdPaths($body))->toBe([]);
})->with([
    'contacts' => ['/api/contacts', Capability::ManageContacts, fn () => Contact::factory()->count(3)->create()],
    'projects' => ['/api/projects', Capability::ViewAllProjects, fn () => Project::factory()->count(3)->create()],
    'leads' => ['/api/leads', Capability::ManageLeads, fn () => Lead::factory()->count(3)->create()],
    'shoots' => ['/api/shoots', Capability::ViewAllProjects, fn () => Shoot::factory()->count(3)->create()],
    'jobs' => ['/api/jobs', Capability::ViewAllProjects, fn () => Job::factory()->count(3)->create()],
]);

it('cursors rather than repeats: the second page never holds the first page\'s rows', function () {
    Contact::factory()->count(30)->create();
    $header = bearerFor(Capability::ManageContacts);

    $first = $this->withHeader('Authorization', $header)->getJson('/api/contacts')->assertOk()->json();

    expect($first['data'])->toHaveCount(25);
    expect($first['meta']['next_cursor'])->not->toBeNull();

    $second = $this->withHeader('Authorization', $header)
        ->getJson('/api/contacts?cursor='.$first['meta']['next_cursor'])
        ->assertOk()->json();

    expect($second['data'])->toHaveCount(5);

    $firstIds = collect($first['data'])->pluck('id');
    $secondIds = collect($second['data'])->pluck('id');

    expect($firstIds->intersect($secondIds))->toBeEmpty();
});

it('filters out rows updated before the since timestamp', function () {
    $old = $this->travelTo(now()->subDays(5), fn () => Contact::factory()->create());
    $recent = Contact::factory()->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageContacts))
        ->getJson('/api/contacts?since='.urlencode(now()->subDay()->toIso8601String()))
        ->assertOk()->json();

    $ids = collect($body['data'])->pluck('id');

    expect($ids)->toContain($recent->public_id);
    expect($ids)->not->toContain($old->public_id);
});

it('rejects a since value that is not a valid date', function () {
    $this->withHeader('Authorization', bearerFor(Capability::ManageContacts))
        ->getJson('/api/contacts?since=not-a-date')
        ->assertStatus(422);
});

it('carries a lead\'s project as the project\'s own public_id', function () {
    $project = Project::factory()->create();
    $lead = Lead::factory()->for($project)->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageLeads))
        ->getJson('/api/leads')->assertOk()->json();

    expect($body['data'][0]['id'])->toBe($lead->public_id);
    expect($body['data'][0]['projectId'])->toBe($project->public_id);
});
