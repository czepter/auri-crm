<?php

use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use App\Quotes\Models\Quote;
use App\Settings\Models\StudioClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

function leadOn(
    ?string $date,
    LeadStage $stage = LeadStage::New,
    int $daysAgo = 0,
    string $name = 'Meier & Kowalski',
): Lead {
    $project = Project::factory()->wedding()->create(['name' => $name]);
    Shoot::factory()->for($project)->primary()->on($date)->create();

    return Lead::factory()->for($project)->atStage($stage, $daysAgo)->create();
}

function boardProps(): array
{
    return test()->actingAs(test()->staff, 'staff')
        ->get('/anfragen')->assertOk()->viewData('page')['props'];
}

it('groups projects into the six funnel columns in order', function () {
    leadOn('2026-06-14', LeadStage::New);
    leadOn('2026-07-11', LeadStage::Contacted, name: 'Petersen');
    leadOn('2026-08-22', LeadStage::Booked, name: 'Vehse & Rieck');

    $columns = boardProps()['columns'];

    expect(array_column($columns, 'stage'))
        ->toBe(['new', 'contacted', 'quote_sent', 'quote_accepted', 'contract_sent', 'booked'])
        ->and($columns[0]['label'])->toBe('Neu')
        ->and($columns[0]['cards'])->toHaveCount(1)
        ->and($columns[1]['cards'])->toHaveCount(1)
        ->and($columns[2]['cards'])->toHaveCount(0)
        ->and($columns[5]['cards'])->toHaveCount(1);
});

it('keeps lost leads off the board entirely', function () {
    leadOn('2026-06-14')->forceFill(['lost_at' => now(), 'lost_reason' => 'Budget', 'outcome' => LeadOutcome::Lost])->save();

    $counts = array_map(fn ($c) => count($c['cards']), boardProps()['columns']);

    expect(array_sum($counts))->toBe(0);
});

it('renders the contact names, the primary date and the shoot count', function () {
    $lead = leadOn('2026-06-14', name: 'Steffens & Falk');
    $project = $lead->project;
    $project->attachContact(
        Contact::factory()->create(['name' => 'Annika Steffens']),
        ProjectContactRole::Primary,
    );
    $project->attachContact(
        Contact::factory()->create(['name' => 'Ruben Falk']),
        ProjectContactRole::Partner,
    );
    Shoot::factory()->for($project)->on('2026-03-01')->create();

    $card = boardProps()['columns'][0]['cards'][0];

    expect($card['names'])->toBe('Annika Steffens & Ruben Falk')
        ->and($card['date'])->toBe('2026-06-14')
        ->and($card['shootCount'])->toBe(2)
        ->and($card['id'])->toBe($project->public_id);
});

it('falls back to the project name when no contact is attached', function () {
    leadOn('2026-06-14', name: 'Meier & Kowalski');

    expect(boardProps()['columns'][0]['cards'][0]['names'])->toBe('Meier & Kowalski');
});

it('counts days in the current stage, not since creation', function () {
    leadOn('2026-06-14', LeadStage::Contacted, daysAgo: 8);

    expect(boardProps()['columns'][1]['cards'][0]['daysInStage'])->toBe(8);
});

it('renders a card with no date at all', function () {
    leadOn(null, name: 'Mira Sundermann');

    expect(boardProps()['columns'][0]['cards'][0]['date'])->toBeNull();
});

it('flags a card whose primary date collides with another open project', function () {
    leadOn('2026-06-14', name: 'Meier & Kowalski');
    leadOn('2026-06-14', name: 'Kaltenbach & Adler');

    $cards = boardProps()['columns'][0]['cards'];

    expect($cards[0]['conflicts'])->toHaveCount(1)
        ->and($cards[1]['conflicts'])->toHaveCount(1)
        // Each card names the OTHER project, never itself.
        ->and($cards[0]['conflicts'][0]['projectName'])
        ->not->toBe($cards[1]['conflicts'][0]['projectName']);
});

it('clears the flag when the colliding project moves, without writing to the lead', function () {
    $first = leadOn('2026-06-14', name: 'Meier & Kowalski');
    $second = leadOn('2026-06-14', name: 'Kaltenbach & Adler');

    $before = $first->fresh()->updated_at;

    $second->project->primaryShoot->update(['date' => '2026-06-21']);

    $cards = boardProps()['columns'][0]['cards'];

    // The flag is a query, not a column: it heals itself when the other
    // project moves, and nothing was ever written to this lead to undo.
    expect($cards[0]['conflicts'])->toBe([])
        ->and($cards[1]['conflicts'])->toBe([])
        ->and($first->fresh()->updated_at->eq($before))->toBeTrue();
});

it('resolves every conflict on the board in a bounded number of queries', function () {
    foreach (range(1, 12) as $i) {
        leadOn(sprintf('2026-06-%02d', $i), name: "Projekt {$i}");
    }

    DB::enableQueryLog();
    boardProps();
    $count = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    // A per-card conflict lookup would put this well above 20.
    expect($count)->toBeLessThan(20);
});

it('flags a card whose date falls inside a studio closure, naming it', function () {
    leadOn('2026-12-26', name: 'Meier & Kowalski');
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['label' => 'Weihnachtspause']);

    $card = boardProps()['columns'][0]['cards'][0];

    expect($card['conflicts'])->toHaveCount(1)
        ->and($card['conflicts'][0]['source'])->toBe('closure')
        ->and($card['conflicts'][0]['label'])->toBe('Weihnachtspause');
});

it('issues no more queries for eleven cards spanning several closures than it did before this story', function () {
    foreach (range(1, 11) as $i) {
        leadOn(sprintf('2026-%02d-14', $i), name: "Projekt {$i}");
    }

    // Warms StudioSettingsResolver's own redis-backed cache (read once by
    // RequireStudioOnboarding on every GET) before either measurement below
    // — otherwise only the first of the two request/response cycles pays
    // that one-off cache-miss query, and the two counts this test compares
    // are no longer measuring the same thing.
    boardProps();

    DB::enableQueryLog();
    boardProps();
    $withoutClosures = count(DB::getRawQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    StudioClosure::factory()->spanning('2026-01-01', '2026-01-31')->create(['label' => 'Winterpause']);
    StudioClosure::factory()->spanning('2026-07-01', '2026-07-15')->create(['label' => 'Sommerpause']);
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['label' => 'Weihnachtspause']);

    DB::enableQueryLog();
    boardProps();
    $withClosures = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    expect($withClosures)->toBe($withoutClosures);
});

// The card carries both public ids, and the stage route only accepts the
// lead's. Posting the id the card links with is what the board did for a
// release, and it 404s — so the test posts what the card actually sends.
it('advances a lead using the identifier the card carries', function () {
    $lead = leadOn('2026-06-14');
    $card = boardProps()['columns'][0]['cards'][0];

    expect($card['leadId'])->not->toBe($card['id']);

    $this->actingAs($this->staff, 'staff')
        ->post("/anfragen/{$card['leadId']}/stufe", ['stage' => 'contacted'])
        ->assertRedirect('/anfragen');

    expect($lead->fresh()->stage->key)->toBe(LeadStage::Contacted->value);
});

it('advances a lead from the board', function () {
    $lead = leadOn('2026-06-14');

    $this->actingAs($this->staff, 'staff')
        ->post("/anfragen/{$lead->public_id}/stufe", ['stage' => 'contacted'])
        ->assertRedirect('/anfragen');

    expect($lead->fresh()->stage->key)->toBe(LeadStage::Contacted->value);
});

// Dragging left is the board's undo for a mis-drop, so the same endpoint takes
// it. See LeadStage::canTransitionTo.
it('moves a lead backward from the board', function () {
    $lead = leadOn('2026-06-14', LeadStage::QuoteSent);

    $this->actingAs($this->staff, 'staff')
        ->post("/anfragen/{$lead->public_id}/stufe", ['stage' => 'new'])
        ->assertRedirect('/anfragen');

    expect($lead->fresh()->stage->key)->toBe(LeadStage::New->value);
});

it('refuses a move to the stage the card already sits in', function () {
    $lead = leadOn('2026-06-14', LeadStage::QuoteSent);

    $this->actingAs($this->staff, 'staff')
        ->post("/anfragen/{$lead->public_id}/stufe", ['stage' => 'quote_sent'])
        ->assertSessionHasErrors('stage');

    expect($lead->fresh()->stage->key)->toBe(LeadStage::QuoteSent->value);
});

it('binds the stage route on the public id, not the primary key', function () {
    $lead = leadOn('2026-06-14');

    $this->actingAs($this->staff, 'staff')
        ->post("/anfragen/{$lead->id}/stufe", ['stage' => 'contacted'])
        ->assertNotFound();
});

it('puts no integer identifier on the board', function () {
    leadOn('2026-06-14');

    expect(integerIdPaths(boardProps()))->toBe([]);
});

// C-07 (#241): the column head carries the count and the sum — the question
// staff otherwise go to Auswertungen for.
it('sums each stage from the quotes actually sent', function () {
    $lead = leadOn('2026-06-14', LeadStage::QuoteSent);
    Quote::factory()->for($lead->project)->create(['total' => 590000, 'sent_at' => now()->subDay()]);

    $other = leadOn('2026-07-11', LeadStage::QuoteSent, name: 'Petersen');
    Quote::factory()->for($other->project)->create(['total' => 310000, 'sent_at' => now()->subDay()]);

    // Nothing sent: not a zero in the sum, and a dash in the value column.
    leadOn('2026-08-02', LeadStage::New, name: 'Sundermann');

    $columns = collect(boardProps()['columns'])->keyBy('stage');

    expect($columns['quote_sent']['total'])->toBe(900000)
        ->and($columns['new']['total'])->toBe(0)
        ->and(collect($columns['new']['cards'])->first()['value'])->toBeNull();
});

// A board shows where something stands; a table shows what is next, so it
// groups by working state rather than by the six stages — the same three
// groups the queue beside it uses, or the two would disagree on one screen.
it('groups the table and the queue the same way', function () {
    leadOn('2026-06-14', LeadStage::New, name: 'Neu');
    leadOn('2026-07-11', LeadStage::QuoteSent, name: 'Wartet');
    leadOn('2026-08-02', LeadStage::Booked, name: 'Gebucht');

    $props = boardProps();
    $groups = collect($props['groups'])->keyBy('key');
    $queue = collect($props['queue'])->keyBy('key');

    expect(collect($groups['unbeantwortet']['rows'])->pluck('names')->all())->toBe(['Neu'])
        ->and(collect($groups['angebot']['rows'])->pluck('names')->all())->toBe(['Wartet'])
        ->and(collect($groups['angenommen']['rows'])->pluck('names')->all())->toBe(['Gebucht'])
        ->and(collect($queue['unbeantwortet']['rows'])->pluck('name')->all())->toBe(['Neu'])
        ->and(collect($queue['angebot']['rows'])->pluck('name')->all())->toBe(['Wartet'])
        ->and(collect($queue['angenommen']['rows'])->pluck('name')->all())->toBe(['Gebucht'])
        ->and(collect($queue['unbeantwortet']['rows'])->pluck('open')->all())->toBe([false]);
});

// An inquiry without a date is not the most urgent one. Table and queue stand
// on one screen, so a row that sits at the top of one and the bottom of the
// other is the same lead in two places.
it('sorts an undated inquiry last in the table and in the queue', function () {
    leadOn(null, name: 'Ohne Termin');
    leadOn('2026-06-14', name: 'Mit Termin');

    $props = boardProps();
    $group = collect($props['groups'])->firstWhere('key', 'unbeantwortet');
    $pane = collect($props['queue'])->firstWhere('key', 'unbeantwortet');

    expect(collect($group['rows'])->pluck('names')->all())->toBe(['Mit Termin', 'Ohne Termin'])
        ->and(collect($pane['rows'])->pluck('name')->all())->toBe(['Mit Termin', 'Ohne Termin']);
});

// The pane links to /anfragen/{lead}, which binds the lead's public id — not
// the project's, which the card next to it carries.
it('links a queue row to the case it stands for', function () {
    $lead = leadOn('2026-06-14', name: 'Neu');

    $row = collect(collect(boardProps()['queue'])->firstWhere('key', 'unbeantwortet')['rows'])->first();

    expect($row['id'])->toBe($lead->public_id);

    test()->actingAs(test()->staff, 'staff')->get("/anfragen/{$row['id']}")->assertOk();
});

it('names the primary contact first in the queue as well as on the card', function () {
    $project = leadOn('2026-06-14', name: 'Steffens & Falk')->project;
    $project->attachContact(
        Contact::factory()->create(['name' => 'Ruben Falk']),
        ProjectContactRole::Partner,
    );
    $project->attachContact(
        Contact::factory()->create(['name' => 'Annika Steffens']),
        ProjectContactRole::Primary,
    );

    $props = boardProps();

    expect(collect(collect($props['groups'])->firstWhere('key', 'unbeantwortet')['rows'])->pluck('names')->all())
        ->toBe(['Annika Steffens & Ruben Falk'])
        ->and(collect(collect($props['queue'])->firstWhere('key', 'unbeantwortet')['rows'])->pluck('name')->all())
        ->toBe(['Annika Steffens & Ruben Falk']);
});

it('lets the url outrank what the user last chose, and remembers a choice', function () {
    test()->staff->update(['lead_view' => 'liste']);

    expect(boardProps()['view'])->toBe('liste');

    $props = test()->actingAs(test()->staff, 'staff')
        ->get('/anfragen?ansicht=board')->assertOk()->viewData('page')['props'];

    expect($props['view'])->toBe('board');

    test()->actingAs(test()->staff, 'staff')->post('/anfragen/ansicht', ['ansicht' => 'board']);

    expect(test()->staff->fresh()->lead_view)->toBe('board');
});

it('falls back to the board when the user has chosen nothing', function () {
    expect(boardProps()['view'])->toBe('board');
});

// A booked record is a project and stands at /projekte. Leaving it here too
// shows the same case twice, under two vocabularies.
it('leaves booked cases off the board', function () {
    $lead = leadOn('2026-06-14', LeadStage::ContractSent);
    Job::factory()->for($lead->project)->create();

    leadOn('2026-07-11', LeadStage::New, name: 'Petersen');

    expect(collect(boardProps()['columns'])->pluck('cards')->flatten(1)->pluck('stage')->all())
        ->toBe([LeadStage::New->value]);
});
