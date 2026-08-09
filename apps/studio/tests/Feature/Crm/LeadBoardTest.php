<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
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
    leadOn('2026-06-14')->forceFill(['lost_at' => now(), 'lost_reason' => 'Budget'])->save();

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

it('advances a lead from the board', function () {
    $lead = leadOn('2026-06-14');

    $this->actingAs($this->staff, 'staff')
        ->post("/anfragen/{$lead->public_id}/stufe", ['stage' => 'contacted'])
        ->assertRedirect('/anfragen');

    expect($lead->fresh()->stage)->toBe(LeadStage::Contacted);
});

it('refuses a backward move from the board', function () {
    $lead = leadOn('2026-06-14', LeadStage::QuoteSent);

    $this->actingAs($this->staff, 'staff')
        ->post("/anfragen/{$lead->public_id}/stufe", ['stage' => 'new'])
        ->assertSessionHasErrors('stage');

    expect($lead->fresh()->stage)->toBe(LeadStage::QuoteSent);
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
