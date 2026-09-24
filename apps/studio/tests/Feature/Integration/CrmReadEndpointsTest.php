<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
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
    'projects' => ['/api/projects', Capability::ViewAllCases],
    'leads' => ['/api/leads', Capability::ManageLeads],
    'shoots' => ['/api/shoots', Capability::ViewAllCases],
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
    'projects' => ['/api/projects', Capability::ViewAllCases, fn () => ClientCase::factory()->booked()->count(3)->create()],
    'leads' => ['/api/leads', Capability::ManageLeads, fn () => Lead::factory()->count(3)->create()],
    'shoots' => ['/api/shoots', Capability::ViewAllCases, fn () => Shoot::factory()->count(3)->create()],
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

it('caps the page size at per_page, up to the max of 100', function () {
    Contact::factory()->count(10)->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageContacts))
        ->getJson('/api/contacts?per_page=3')
        ->assertOk()->json();

    expect($body['data'])->toHaveCount(3);
    expect($body['meta']['per_page'])->toBe(3);
});

it('rejects a per_page above the api surface\'s max', function () {
    $this->withHeader('Authorization', bearerFor(Capability::ManageContacts))
        ->getJson('/api/contacts?per_page=101')
        ->assertStatus(422);
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

// #353: the contact search runs on the server, over every row rather than
// the page a caller happens to be holding.

it('searches contacts by name or email, case-insensitively', function () {
    $byName = Contact::factory()->create(['name' => 'Steffens', 'email' => 'post@example.test']);
    $byEmail = Contact::factory()->create(['name' => 'Kowalski', 'email' => 'STEFF@example.test']);
    Contact::factory()->create(['name' => 'Meier', 'email' => 'meier@example.test']);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageContacts))
        ->getJson('/api/contacts?query=steff')
        ->assertOk()->json();

    expect(collect($body['data'])->pluck('id')->sort()->values()->all())
        ->toBe(collect([$byName->public_id, $byEmail->public_id])->sort()->values()->all());
});

// The search the page-local filter could never do: the match is on the last
// page, so a filter over the first page returns nothing at all.
it('finds a contact that the first page does not contain', function () {
    // Faker emails come from surnames, so a random one can contain "steffens".
    Contact::factory()->count(30)->sequence(fn ($s) => ['email' => "meier{$s->index}@example.test"])->create(['name' => 'Meier']);
    $needle = Contact::factory()->create(['name' => 'Steffens']);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageContacts))
        ->getJson('/api/contacts?query=steffens')
        ->assertOk()->json();

    expect(collect($body['data'])->pluck('id')->all())->toBe([$needle->public_id]);
});

it('carries a contact\'s address', function () {
    $contact = Contact::factory()->create(['street' => 'Marktstraße 1', 'postcode' => '07743', 'city' => 'Jena']);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageContacts))
        ->getJson('/api/contacts')->assertOk()->json();

    expect($body['data'][0])->toMatchArray([
        'street' => 'Marktstraße 1',
        'postcode' => '07743',
        'city' => 'Jena',
    ]);
});

it('rejects a since value that is not a valid date', function () {
    $this->withHeader('Authorization', bearerFor(Capability::ManageContacts))
        ->getJson('/api/contacts?since=not-a-date')
        ->assertStatus(422);
});

it('lists only booked projects, never a lead\'s', function () {
    $booked = ClientCase::factory()->booked()->create();
    Lead::factory()->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson('/api/projects')->assertOk()->json();

    expect(collect($body['data'])->pluck('id')->all())->toBe([$booked->public_id]);
});

it('carries a lead\'s project as the project\'s own public_id', function () {
    $project = ClientCase::factory()->create();
    $lead = Lead::factory()->for($project, 'case')->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageLeads))
        ->getJson('/api/leads')->assertOk()->json();

    expect($body['data'][0]['id'])->toBe($lead->case->public_id);
    expect($body['data'][0]['projectId'])->toBe($project->public_id);
});

// #349: GET /api/leads?stage=&closed=&captured_from=&captured_to=

it('filters leads by stage', function () {
    $wanted = Lead::factory()->atStage(LeadStage::QuoteSent)->create();
    Lead::factory()->atStage(LeadStage::New)->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageLeads))
        ->getJson('/api/leads?stage=quote_sent')
        ->assertOk()->json();

    expect(collect($body['data'])->pluck('id')->all())->toBe([$wanted->case->public_id]);
});

it('filters leads by whether they are closed, keeping the stage they died in', function () {
    $open = Lead::factory()->atStage(LeadStage::QuoteSent)->create();
    $closed = Lead::factory()->atStage(LeadStage::QuoteSent)->lost()->create();

    $header = bearerFor(Capability::ManageLeads);

    $stillIn = $this->withHeader('Authorization', $header)->getJson('/api/leads?closed=false')->assertOk()->json();
    $done = $this->withHeader('Authorization', $header)->getJson('/api/leads?stage=quote_sent&closed=true')->assertOk()->json();

    expect(collect($stillIn['data'])->pluck('id')->all())->toBe([$open->case->public_id]);
    expect(collect($done['data'])->pluck('id')->all())->toBe([$closed->case->public_id]);
});

it('filters leads to the window they were captured in', function () {
    $inWindow = Lead::factory()->create(['captured_at' => '2026-03-15 09:00:00']);
    Lead::factory()->create(['captured_at' => '2026-02-28 09:00:00']);
    Lead::factory()->create(['captured_at' => '2026-04-01 09:00:00']);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageLeads))
        ->getJson('/api/leads?captured_from=2026-03-01&captured_to=2026-03-31')
        ->assertOk()->json();

    expect(collect($body['data'])->pluck('id')->all())->toBe([$inWindow->case->public_id]);
});

it('rejects a lead capture window where captured_to precedes captured_from', function () {
    $this->withHeader('Authorization', bearerFor(Capability::ManageLeads))
        ->getJson('/api/leads?captured_from=2026-03-31&captured_to=2026-03-01')
        ->assertStatus(422);
});

it('rejects a lead stage filter that is not a stage', function () {
    $this->withHeader('Authorization', bearerFor(Capability::ManageLeads))
        ->getJson('/api/leads?stage=verschickt')
        ->assertStatus(422);
});

it('carries a lead\'s source detail, capture moment and closed state', function () {
    $lead = Lead::factory()->lost()->create([
        'source' => LeadSource::Other,
        'source_other' => 'Hochzeitsportal',
        'captured_at' => '2026-03-15 09:00:00',
    ]);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageLeads))
        ->getJson('/api/leads')->assertOk()->json();

    expect($body['data'][0]['sourceOther'])->toBe('Hochzeitsportal');
    expect($body['data'][0]['capturedAt'])->toBe($lead->captured_at->toIso8601String());
    expect($body['data'][0]['isLost'])->toBeTrue();
});

// #399: isLost alone cannot tell a lost lead from a declined one, and the
// project a lead hangs off had no name attached at all. #521: the close sits on the case.
it('carries a case\'s close outcome, reason and moment, and a lead\'s stage entry and project name', function () {
    $project = ClientCase::factory()->create(['name' => 'Meier Hochzeit']);
    $lead = Lead::factory()->for($project, 'case')->atStage(LeadStage::QuoteSent)
        ->lost('Zu teuer', LeadOutcome::Declined)->create();

    $header = bearerFor(Capability::ManageLeads, Capability::ViewAllCases);
    $leads = $this->withHeader('Authorization', $header)->getJson('/api/leads')->assertOk()->json();
    $case = $this->withHeader('Authorization', $header)->getJson("/api/cases/{$project->public_id}")->assertOk()->json('data');

    expect($leads['data'][0]['projectName'])->toBe('Meier Hochzeit');
    expect($leads['data'][0]['stageEnteredAt'])->toBe($lead->stage_entered_at?->toIso8601String());
    expect($case['closeOutcome'])->toBe('declined');
    expect($case['closeReason'])->toBe('Zu teuer');
    expect($case['closedAt'])->toBe($project->fresh()->closed_at->toIso8601String());
});

// N-02: GET /api/cases/{project:public_id} — the single-resource read
// the index alone can't answer.

it('gets a single project by its public_id', function () {
    $project = ClientCase::factory()->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson("/api/cases/{$project->public_id}")
        ->assertOk()->json();

    expect($body['data']['id'])->toBe($project->public_id);
    expect(integerIdPaths($body))->toBe([]);
});

// #350: what the index deliberately does not carry — enough to verify a
// capture without three more calls.

it('carries the lead, the contacts with their roles, and the shoots', function () {
    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);
    $project->attachContact($contact, CaseContactRole::Primary);
    $lead = Lead::factory()->for($project, 'case')->atStage(LeadStage::Booked)->create([
        'source' => LeadSource::Referral,
        'inquiry_text' => 'Standesamt Jena, Juni.',
    ]);
    $shoot = Shoot::factory()->for($project, 'case')->create([
        'call_time' => '14:30',
        'end_time' => '23:00',
        'venue_name' => 'Standesamt',
        'venue_city' => 'Jena',
    ]);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson("/api/cases/{$project->public_id}")
        ->assertOk()->json('data');

    expect($body['lead'])->toBe([
        'stage' => 'booked',
        'source' => 'referral',
        'sourceOther' => null,
        'inquiryText' => 'Standesamt Jena, Juni.',
        'capturedVia' => $lead->captured_via->value,
        'capturedViaOther' => null,
        'capturedAt' => $lead->captured_at->toIso8601String(),
        'stageEnteredAt' => $lead->stage_entered_at?->toIso8601String(),
    ]);
    expect($body['closedAt'])->toBeNull();
    expect($body['shoots'][0])->toMatchArray([
        'callTime' => '14:30',
        'endTime' => '23:00',
        'venueName' => 'Standesamt',
        'venueCity' => 'Jena',
        'cancelledReason' => null,
    ]);
    expect($body['contacts'])->toHaveCount(1);
    expect($body['contacts'][0]['id'])->toBe($contact->public_id);
    expect($body['contacts'][0]['role'])->toBe('primary');
    expect(collect($body['shoots'])->pluck('id')->all())->toBe([$shoot->public_id]);
    expect(integerIdPaths($body))->toBe([]);
});

// #399: booking status, the primary-shoot flag, crew per shoot and open
// tasks were all unreachable from get_case before this.
it('carries the project\'s booking, its shoots\' crew and primary flag, and its open task count', function () {
    $project = ClientCase::factory()->create();
    markBooked($project, ['booked_via' => 'manual']);
    $shoot = Shoot::factory()->for($project, 'case')->create(['is_primary' => true]);
    CrewAssignment::factory()->for($shoot)->create();
    $project->tasks()->create(['title' => 'Vertrag verschicken', 'audience' => 'internal']);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson("/api/cases/{$project->public_id}")
        ->assertOk()->json('data');

    expect($body['bookedAt'])->not->toBeNull();
    expect($body['bookedVia'])->toBe('manual');
    expect($body['shoots'][0]['isPrimary'])->toBeTrue();
    expect($body['shoots'][0]['crew'])->toHaveCount(1);
    expect($body['openTaskCount'])->toBe(1);
    expect(integerIdPaths($body))->toBe([]);
});

it('leaves an inquiry with a null booking', function () {
    $project = ClientCase::factory()->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson("/api/cases/{$project->public_id}")
        ->assertOk()->json('data');

    expect($body['bookedAt'])->toBeNull();
    expect($body['bookedVia'])->toBeNull();
    expect($body['openTaskCount'])->toBe(0);
});

it('leaves a project without a lead with a null lead rather than an absent one', function () {
    $project = ClientCase::factory()->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson("/api/cases/{$project->public_id}")
        ->assertOk()->json('data');

    expect($body)->toHaveKey('lead');
    expect($body['lead'])->toBeNull();
});

// Notes hang off ManageNotes. A token holding ViewAllCases reaching them
// through the project read is exactly the leak this endpoint must not be.
it('never carries internal notes', function () {
    $project = ClientCase::factory()->create();
    InternalNote::factory()->for($project, 'case')->create(['body' => 'Zahlt bar, Vater ist schwierig.']);

    $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson("/api/cases/{$project->public_id}")
        ->assertOk()
        ->assertDontSee('Vater ist schwierig');
});

// The lean index is the other half of #350's decision: growing the payload
// there is the contact join on every row this split exists to avoid.
it('keeps the project index lean', function () {
    $project = ClientCase::factory()->booked()->create();
    $project->attachContact(Contact::factory()->create(), CaseContactRole::Primary);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson('/api/projects')->assertOk()->json();

    expect(array_keys($body['data'][0]))->toBe(['id', 'name', 'type', 'portalVisible', 'fields', 'updatedAt']);
});

// A project with nothing set encodes `fields` from an empty PHP array, and
// `[]` is not the map every caller is told to expect — the MCP surface
// refuses the whole response over it. json() reads `{}` and `[]` alike, so
// the shape has to be asserted on the raw body.
it('encodes project fields as an object even when the project has none', function () {
    $project = ClientCase::factory()->create(['field_values' => []]);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson("/api/cases/{$project->public_id}")
        ->assertOk()->getContent();

    expect($body)->toContain('"fields":{}');
});

it('refuses a single project to a token that does not hold ViewAllCases', function () {
    $project = ClientCase::factory()->create();

    $this->withHeader('Authorization', bearerFor(Capability::ManageStaff))
        ->getJson("/api/cases/{$project->public_id}")
        ->assertForbidden();
});

it('404s a single project lookup for a public_id that does not exist', function () {
    $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson('/api/cases/not-a-real-id')
        ->assertNotFound();
});

// N-02: GET /api/shoots?from=&to= — I-02's `shoots` index narrowed to a
// date range, on top of its plain `since` filter.

it('filters shoots to a date range', function () {
    $inRange = Shoot::factory()->create(['date' => '2026-03-15']);
    Shoot::factory()->create(['date' => '2026-02-15']);
    Shoot::factory()->create(['date' => '2026-04-15']);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
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
            $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
                ->getJson("/api/shoots{$query}")
                ->assertOk()->json('data')
        )->pluck('id')->all();
    };

    expect($ids('?cancelled=false'))->toBe([$open->public_id])
        ->and($ids('?cancelled=true'))->toBe([$cancelled->public_id])
        ->and($ids(''))->toBe([$open->public_id, $cancelled->public_id]);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson('/api/shoots?cancelled=true')
        ->assertOk()->json('data');

    expect($body[0]['cancelledAt'])->not->toBeNull();
});

it('carries a shoot\'s crew and primary flag', function () {
    $shoot = Shoot::factory()->create(['date' => '2026-03-15', 'is_primary' => true]);
    CrewAssignment::factory()->for($shoot)->create();

    $body = $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
        ->getJson('/api/shoots?from=2026-03-01&to=2026-03-31')
        ->assertOk()->json('data');

    expect($body[0]['isPrimary'])->toBeTrue();
    expect($body[0]['crew'])->toHaveCount(1);
    expect(integerIdPaths($body))->toBe([]);
});

it('rejects a shoots date range where to precedes from', function () {
    $this->withHeader('Authorization', bearerFor(Capability::ViewAllCases))
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
        ['stage' => 'booked', 'label' => 'Gebucht', 'count' => 0],
    ]);
});

// #399: LeadBoardController already drops an inactive stage from the board;
// the snapshot showed it anyway, at a count of 0.
it('leaves an inactive stage out of the pipeline snapshot', function () {
    Lead::factory()->atStage(LeadStage::New)->create();
    stage(LeadStage::Contacted)->update(['is_active' => false]);

    $body = $this->withHeader('Authorization', bearerFor(Capability::ManageLeads))
        ->getJson('/api/pipeline-snapshot')
        ->assertOk()->json();

    expect(collect($body['data'])->pluck('stage')->all())->not->toContain('contacted');
});

it('refuses the pipeline snapshot to a token that does not hold ManageLeads', function () {
    $this->withHeader('Authorization', bearerFor(Capability::ManageStaff))
        ->getJson('/api/pipeline-snapshot')
        ->assertForbidden();
});
