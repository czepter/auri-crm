<?php

use App\Crm\Enums\LeadStage;
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

// N-02: GET /api/projects/{project:public_id} — the single-resource read
// the index alone can't answer.

it('gets a single project by its public_id', function () {
    $project = Project::factory()->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllProjects))
        ->getJson("/api/projects/{$project->public_id}")
        ->assertOk()->json();

    expect($body['data']['id'])->toBe($project->public_id);
    expect(integerIdPaths($body))->toBe([]);
});

// A project with nothing set encodes `fields` from an empty PHP array, and
// `[]` is not the map every caller is told to expect — the MCP surface
// refuses the whole response over it. json() reads `{}` and `[]` alike, so
// the shape has to be asserted on the raw body.
it('encodes project fields as an object even when the project has none', function () {
    $project = Project::factory()->create(['field_values' => []]);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllProjects))
        ->getJson("/api/projects/{$project->public_id}")
        ->assertOk()->getContent();

    expect($body)->toContain('"fields":{}');
});

it('refuses a single project to a token that does not hold ViewAllProjects', function () {
    $project = Project::factory()->create();

    $this->withHeader('Authorization', bearerFor(Capability::ManageStaff))
        ->getJson("/api/projects/{$project->public_id}")
        ->assertForbidden();
});

it('404s a single project lookup for a public_id that does not exist', function () {
    $this->withHeader('Authorization', bearerFor(Capability::ViewAllProjects))
        ->getJson('/api/projects/not-a-real-id')
        ->assertNotFound();
});

// N-02: GET /api/shoots?from=&to= — I-02's `shoots` index narrowed to a
// date range, on top of its plain `since` filter.

it('filters shoots to a date range', function () {
    $inRange = Shoot::factory()->create(['date' => '2026-03-15']);
    Shoot::factory()->create(['date' => '2026-02-15']);
    Shoot::factory()->create(['date' => '2026-04-15']);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllProjects))
        ->getJson('/api/shoots?from=2026-03-01&to=2026-03-31')
        ->assertOk()->json();

    $ids = collect($body['data'])->pluck('id');

    expect($ids->all())->toBe([$inRange->public_id]);
});

it('filters shoots by cancelled, and keeps both without it', function () {
    $open = Shoot::factory()->create(['date' => '2026-03-15']);
    $cancelled = Shoot::factory()->cancelled('Termin abgesagt')->create(['date' => '2026-03-16']);

    $ids = function (string $query) {
        return collect(
            $this->withHeader('Authorization', bearerFor(Capability::ViewAllProjects))
                ->getJson("/api/shoots{$query}")
                ->assertOk()->json('data')
        )->pluck('id')->all();
    };

    expect($ids('?cancelled=false'))->toBe([$open->public_id])
        ->and($ids('?cancelled=true'))->toBe([$cancelled->public_id])
        ->and($ids(''))->toBe([$open->public_id, $cancelled->public_id]);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllProjects))
        ->getJson('/api/shoots?cancelled=true')
        ->assertOk()->json('data');

    expect($body[0]['cancelledAt'])->not->toBeNull();
});

it('rejects a shoots date range where to precedes from', function () {
    $this->withHeader('Authorization', bearerFor(Capability::ViewAllProjects))
        ->getJson('/api/shoots?from=2026-03-31&to=2026-03-01')
        ->assertStatus(422);
});

// N-02: GET /api/pipeline-snapshot — a count per LeadStage.

it('summarises the lead pipeline as a count per stage, in funnel order', function () {
    Lead::factory()->atStage(LeadStage::New)->count(2)->create();
    Lead::factory()->atStage(LeadStage::Contacted)->create();
    Lead::factory()->atStage(LeadStage::New)->lost()->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageLeads))
        ->getJson('/api/pipeline-snapshot')
        ->assertOk()->json();

    expect($body['data'])->toBe([
        ['stage' => 'new', 'label' => 'Neu', 'count' => 2],
        ['stage' => 'contacted', 'label' => 'Kontaktiert', 'count' => 1],
        ['stage' => 'quote_sent', 'label' => 'Angebot', 'count' => 0],
        ['stage' => 'quote_accepted', 'label' => 'Angebot angenommen', 'count' => 0],
        ['stage' => 'contract_sent', 'label' => 'Vertrag', 'count' => 0],
        ['stage' => 'booked', 'label' => 'Gebucht', 'count' => 0],
    ]);
});

it('refuses the pipeline snapshot to a token that does not hold ManageLeads', function () {
    $this->withHeader('Authorization', bearerFor(Capability::ManageStaff))
        ->getJson('/api/pipeline-snapshot')
        ->assertForbidden();
});
