<?php

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSignature;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\FormAnswer;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Models\AuditLog;
use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
    $this->project = Project::factory()->create();
});

// props() lives in tests/Pest.php — see the note there.

it('renders the project overview with a derived header', function () {
    // Booked, because the shoots only speak for a project in production —
    // before that the header reads the funnel.
    markBooked($this->project);
    Shoot::factory()->for($this->project)->primary()->atStatus(ShootStatus::Prep)->create();

    $header = props("/auftraege/{$this->project->public_id}")['header'];

    expect($header['position']['label'])->toBe('Vorbereitung')
        ->and($header['position']['source'])->toBe('shoot')
        ->and($header['id'])->toBe($this->project->public_id);
});

it('shows only the tabs whose feature exists', function () {
    // Mockup 04 shows seven. A tab appears when the feature behind it lands,
    // never as an empty shell. Vertrag ist optional, Kommunikation is E-06,
    // Fragebögen is E-02.
    $tabs = props("/auftraege/{$this->project->public_id}")['header']['tabs'];

    expect(array_column($tabs, 'key'))->toBe(['overview', 'shoots', 'messages', 'galleries', 'documents']);
});

it('names its tabs with keys the catalogue answers', function () {
    // The labels used to be German strings in PHP, which made this the one
    // navigation in the product no locale could reach.
    $catalogue = json_decode(file_get_contents(resource_path('js/locales/de.json')), true);

    $tabs = props("/auftraege/{$this->project->public_id}")['header']['tabs'];

    foreach (array_column($tabs, 'label') as $key) {
        expect(data_get($catalogue, $key))->toBeString("{$key} is missing from de.json");
    }
});

it('carries the quote block the inquiry overview carries', function () {
    expect(props("/auftraege/{$this->project->public_id}")['quote'])->toBeNull();

    Quote::factory()->for($this->project)->create(['total' => 590000, 'sent_at' => now()->subDay()]);

    $props = props("/auftraege/{$this->project->public_id}");

    expect($props['quote']['total'])->toBe(590000)
        ->and($props['packages'])->toBe([]);
});

it('counts the threads waiting for an answer', function () {
    // The rule the inbox already uses: whoever wrote the last line owes the
    // next one. A thread the studio answered last is not waiting.
    $waiting = Thread::factory()->for($this->project)->create();
    Message::factory()->for($waiting)->create();

    $answered = Thread::factory()->for($this->project)->create();
    Message::factory()->for($answered)->create();
    Message::factory()->for($answered)->fromStaff($this->staff)->create();

    $tabs = props("/auftraege/{$this->project->public_id}")['header']['tabs'];

    expect(collect($tabs)->firstWhere('key', 'messages')['count'])->toBe(1);
});

it('counts the questionnaires the client has not answered', function () {
    // Shared and untouched is the one that waits. A questionnaire the studio
    // keeps to itself is nobody's homework, and one with an answer on it has
    // been started.
    FormInstance::factory()->for($this->project)->create()->forceFill(['shared_at' => now()])->save();

    $started = FormInstance::factory()->for($this->project)->create();
    $started->forceFill(['shared_at' => now()])->save();
    FormAnswer::factory()->for(FormInstanceField::factory()->for($started))->create();

    FormInstance::factory()->for($this->project)->create();

    $tabs = props("/auftraege/{$this->project->public_id}")['header']['tabs'];

    $documents = collect($tabs)->firstWhere('key', 'documents');

    expect(collect($documents['children'])->firstWhere('key', 'forms')['count'])->toBe(1)
        ->and($documents['count'])->toBe(1);
});

it('counts the quotes the client has not answered', function () {
    // Sent and still open. A draft has not left the studio, and an accepted
    // or superseded one is settled either way.
    Quote::factory()->for($this->project)->create(['status' => QuoteStatus::Sent, 'sent_at' => now()]);
    Quote::factory()->for($this->project)->create();
    Quote::factory()->for($this->project)->create(['status' => QuoteStatus::Accepted, 'sent_at' => now(), 'accepted_at' => now()]);

    $tabs = props("/auftraege/{$this->project->public_id}")['header']['tabs'];

    expect(collect(collect($tabs)->firstWhere('key', 'documents')['children'])->firstWhere('key', 'quotes')['count'])->toBe(1);
});

it('counts the contracts still waiting for a signature', function () {
    // A contract in front of the client and unsigned is the one that waits.
    // Signed is done, and one that never rendered is the studio's own problem.
    Contract::factory()->for($this->project)->create(['status' => ContractStatus::Rendered]);

    $signed = Contract::factory()->for($this->project)->create(['status' => ContractStatus::Rendered]);
    ContractSignature::factory()->for($signed)->create();

    $tabs = props("/auftraege/{$this->project->public_id}")['header']['tabs'];

    expect(collect(collect($tabs)->firstWhere('key', 'documents')['children'])->firstWhere('key', 'contract')['count'])->toBe(1);
});

it('agrees with the board about an unbooked project', function () {
    // Reported from the screen: the header said "Angefragt" while the board
    // said "Angebot angenommen" for the same project. Intake creates the
    // primary shoot at `requested` and it stays there until booking, so
    // reading the shoots made every unbooked project say the same word.
    Lead::factory()->for($this->project)->atStage(LeadStage::QuoteAccepted)->create();
    Shoot::factory()->for($this->project)->primary()->create();

    $position = props("/auftraege/{$this->project->public_id}/shootings")['header']['position'];

    expect($position['label'])->toBe('Angebot angenommen')
        ->and($position['source'])->toBe('lead');
});

it('reads the lead stage in the header when no uncancelled shoot exists', function () {
    Lead::factory()->for($this->project)->atStage(LeadStage::QuoteSent)->create();

    expect(props("/auftraege/{$this->project->public_id}/shootings")['header']['position']['label'])
        ->toBe('Angebot');
});

it('adds a shoot to a project', function () {
    $this->post("/auftraege/{$this->project->public_id}/shootings", [
        'kind' => 'Verlobungsshooting',
        'label' => 'Verlobungsshooting',
        'date' => '2026-03-22',
        'call_time' => '14:00',
        'end_time' => '16:00',
        'venue_name' => 'Jenischpark',
    ])->assertRedirect();

    $shoot = $this->project->shoots()->sole();

    expect($shoot->kind)->toBe('Verlobungsshooting')
        ->and($shoot->status->key)->toBe(ShootStatus::Requested->value)
        ->and($shoot->is_primary)->toBeFalse();
});

it('takes a second and a third shoot', function () {
    Shoot::factory()->for($this->project)->primary()->create();
    Shoot::factory()->for($this->project)->count(2)->create();

    expect($this->project->shoots()->count())->toBe(3);
});

it('advances a shoot from the screen', function () {
    $shoot = Shoot::factory()->for($this->project)->atStatus(ShootStatus::Booked)->create();

    $this->post("/shootings/{$shoot->public_id}/status", ['status' => 'shot'])
        ->assertRedirect();

    expect($shoot->fresh()->status->key)->toBe(ShootStatus::Shot->value);
});

it('renders a refused transition beside the control rather than failing', function () {
    $shoot = Shoot::factory()->for($this->project)->atStatus(ShootStatus::Delivered)->create();

    // The no-op, since a correction backwards is a move the screen now makes.
    $this->from("/auftraege/{$this->project->public_id}/shootings")
        ->post("/shootings/{$shoot->public_id}/status", ['status' => ShootStatus::Delivered->value])
        ->assertSessionHasErrors('status');

    expect($shoot->fresh()->status->key)->toBe(ShootStatus::Delivered->value);
});

it('takes a mistaken status move back from the project screen', function () {
    $shoot = Shoot::factory()->for($this->project)->atStatus(ShootStatus::Delivered)->create();

    $this->post("/shootings/{$shoot->public_id}/status", ['status' => ShootStatus::Booked->value])
        ->assertRedirect();

    expect($shoot->fresh()->status->key)->toBe(ShootStatus::Booked->value);
});

it('takes an absage back from the project screen', function () {
    $shoot = Shoot::factory()->for($this->project)->atStatus(ShootStatus::Prep)->create();

    $this->post("/shootings/{$shoot->public_id}/absage", ['reason' => 'Termin abgesagt']);
    $this->post("/shootings/{$shoot->public_id}/absage-zuruecknehmen")->assertRedirect();

    expect($shoot->fresh()->isCancelled())->toBeFalse()
        ->and($shoot->fresh()->status->key)->toBe(ShootStatus::Prep->value);
});

it('cancels a shoot with a reason', function () {
    $shoot = Shoot::factory()->for($this->project)->atStatus(ShootStatus::Prep)->create();

    $this->post("/shootings/{$shoot->public_id}/absage", ['reason' => 'Termin abgesagt'])
        ->assertRedirect();

    expect($shoot->fresh()->isCancelled())->toBeTrue()
        ->and($shoot->fresh()->status->key)->toBe(ShootStatus::Prep->value);
});

it('re-designates the primary shoot in one transaction', function () {
    $incumbent = Shoot::factory()->for($this->project)->primary()->create();
    $challenger = Shoot::factory()->for($this->project)->create();

    $this->post("/shootings/{$challenger->public_id}/haupttermin")->assertRedirect();

    expect($challenger->fresh()->is_primary)->toBeTrue()
        ->and($incumbent->fresh()->is_primary)->toBeFalse()
        ->and(AuditLog::where('event', 'shoot.primary_designated')->count())->toBe(1);
});

it('violates the index if the incumbent is not cleared first', function () {
    // The wrong order, written out: this is what DesignatePrimaryShoot's
    // transaction exists to prevent, and the database is the thing enforcing it.
    Shoot::factory()->for($this->project)->primary()->create();
    $challenger = Shoot::factory()->for($this->project)->create();

    $challenger->forceFill(['is_primary' => true])->save();
})->throws(QueryException::class);

it('assigns crew to a shoot by public id and renders them', function () {
    $shoot = Shoot::factory()->for($this->project)->create();
    $photographer = User::factory()->create(['name' => 'Nina Weber']);

    $this->post("/shootings/{$shoot->public_id}/crew", [
        'user' => $photographer->public_id,
    ])->assertRedirect();

    $crew = props("/auftraege/{$this->project->public_id}/shootings")['shoots'][0]['crew'];

    expect($crew)->toHaveCount(1)
        ->and($crew[0]['initials'])->toBe('NW');
});

it('does not duplicate someone already on the shoot', function () {
    $shoot = Shoot::factory()->for($this->project)->create();
    $user = User::factory()->create();
    $payload = ['user' => $user->public_id];

    $this->post("/shootings/{$shoot->public_id}/crew", $payload)->assertRedirect();
    $this->post("/shootings/{$shoot->public_id}/crew", $payload)->assertRedirect();

    expect($shoot->crew()->count())->toBe(1);
});

it('removes a crew assignment', function () {
    $assignment = CrewAssignment::factory()
        ->for(Shoot::factory()->for($this->project))
        ->create();

    $this->delete("/crew/{$assignment->public_id}")->assertRedirect();

    expect(CrewAssignment::count())->toBe(0);
});

it('books the project from the screen', function () {
    Shoot::factory()->for($this->project)->create();

    $this->post("/auftraege/{$this->project->public_id}/buchung")->assertRedirect();

    expect($this->project->fresh()->isBooked())->toBeTrue()
        ->and($this->project->shoots()->sole()->status->key)->toBe(ShootStatus::Booked->value);
});

it('deletes a project nothing agreed hangs off', function () {
    Lead::factory()->for($this->project)->create();

    $this->delete("/auftraege/{$this->project->public_id}")->assertRedirect('/projekte');

    expect(Project::count())->toBe(0)->and(Lead::count())->toBe(0);
});

it('keeps a booked project and offers no delete', function () {
    markBooked($this->project);

    expect(props("/auftraege/{$this->project->public_id}")['header']['canDelete'])->toBeFalse();

    $this->delete("/auftraege/{$this->project->public_id}")->assertSessionHasErrors('project');

    expect(Project::count())->toBe(1);
});

it('links the inquiry and closes it from the project', function () {
    $lead = Lead::factory()->for($this->project)->create();
    $screen = "/auftraege/{$this->project->public_id}/shootings";

    expect(array_column(props($screen)['header']['tabs'], 'href', 'key')['overview'])->toBe("/anfragen/{$lead->public_id}");

    $this->from($screen)
        ->post("/anfragen/{$lead->public_id}/schliessen", ['outcome' => 'lost', 'reason' => 'Anderen Fotografen gebucht'])
        ->assertRedirect($screen);

    expect(props($screen)['header']['outcomeLabel'])->toBe('Verloren');
});

it('leaves an already booked project alone when the button is pressed twice', function () {
    $this->post("/auftraege/{$this->project->public_id}/buchung")->assertRedirect();
    $this->post("/auftraege/{$this->project->public_id}/buchung")->assertRedirect();

    expect(Project::booked()->count())->toBe(1)
        ->and(AuditLog::where('event', 'project.booked')->count())->toBe(1);
});

it('writes, edits and deletes an internal note', function () {
    $this->post("/auftraege/{$this->project->public_id}/notizen", ['body' => 'Gegenlicht ab 20 Uhr.'])
        ->assertRedirect();

    $note = InternalNote::sole();
    expect($note->author_id)->toBe($this->staff->id);

    $written = collect(props("/auftraege/{$this->project->public_id}")['thread'])
        ->firstWhere('body', 'Gegenlicht ab 20 Uhr.');
    expect($written['author'])->toBe($this->staff->name)
        ->and($written['system'])->toBeFalse();

    $this->patch("/notizen/{$note->public_id}", ['body' => 'Korrigiert.'])->assertRedirect();
    expect($note->fresh()->body)->toBe('Korrigiert.');

    $this->delete("/notizen/{$note->public_id}")->assertRedirect();
    expect(InternalNote::count())->toBe(0);
});

it('renders the tasks card with the audience the field carries', function () {
    $shoot = Shoot::factory()->for($this->project)->create(['label' => 'Hochzeitstag']);
    Task::factory()->for($this->project)->create([
        'shoot_id' => $shoot->id,
        'title' => 'Familienliste einholen',
        'audience' => TaskAudience::Client,
        'due_on' => now()->subDay()->format('Y-m-d'),
    ]);

    $tasks = props("/auftraege/{$this->project->public_id}")['tasks'];

    expect($tasks[0]['title'])->toBe('Familienliste einholen')
        ->and($tasks[0]['audienceLabel'])->toBe('Kunde')
        ->and($tasks[0]['shootLabel'])->toBe('Hochzeitstag')
        ->and($tasks[0]['isOverdue'])->toBeTrue();
});

// The edit state writes venue_name and venue_city separately, so the screen
// gets them separately. Splitting the joined line back apart cut a name that
// carries its own comma in the wrong place.
it('carries the venue in its two parts beside the joined line', function () {
    Shoot::factory()->for($this->project)->create([
        'venue_name' => 'Gut Basthorst, Hof 2',
        'venue_city' => 'Basthorst',
    ]);

    $shoot = props("/auftraege/{$this->project->public_id}")['shoots'][0];

    expect($shoot['venue'])->toBe('Gut Basthorst, Hof 2, Basthorst')
        ->and($shoot['venueName'])->toBe('Gut Basthorst, Hof 2')
        ->and($shoot['venueCity'])->toBe('Basthorst');
});

it('sorts open tasks above finished ones', function () {
    Task::factory()->for($this->project)->done()->create(['title' => 'Erledigt']);
    Task::factory()->for($this->project)->due(now()->addDays(30)->format('Y-m-d'))->create(['title' => 'Offen']);

    $tasks = props("/auftraege/{$this->project->public_id}")['tasks'];

    expect(collect($tasks)->pluck('title')->all())->toBe(['Offen', 'Erledigt']);
});

// The trace is one order or it is two lists sharing a heading: a note from
// June must not sit under an event from March because notes were merged last.
it('orders notes, recorded events and finished tasks into one trace', function () {
    InternalNote::factory()->for($this->project)->create([
        'body' => 'Notiz',
        'author_id' => $this->staff->id,
        'created_at' => '2026-03-02 09:00:00',
    ]);
    // Audit rows are append-only, so the clock moves rather than the row.
    $this->travelTo('2026-03-01 09:00:00');
    AuditLogger::record('quote.sent', $this->staff, [], null, $this->project->id);
    $this->travelBack();
    Task::factory()->for($this->project)->done()->due('2026-03-03')->create(['title' => 'Erledigt']);

    $thread = props("/auftraege/{$this->project->public_id}")['thread'];

    expect(collect($thread)->pluck('body')->all())
        ->toBe([__('activity.quote.sent'), 'Notiz', 'Erledigt'])
        ->and(collect($thread)->pluck('system')->all())->toBe([true, false, true]);
});

it('never calls a finished task overdue', function () {
    Task::factory()->for($this->project)->done()->due(now()->subYear()->format('Y-m-d'))->create();

    expect(props("/auftraege/{$this->project->public_id}")['tasks'][0]['isOverdue'])->toBeFalse();
});

it('adds a task by hand against one shoot', function () {
    $shoot = Shoot::factory()->for($this->project)->create();

    $this->post("/auftraege/{$this->project->public_id}/aufgaben", [
        'title' => 'Ablauf bestätigen',
        'audience' => TaskAudience::Client->value,
        'due_on' => '2026-06-01',
        'shoot' => $shoot->public_id,
    ])->assertRedirect();

    $task = Task::sole();

    expect($task->title)->toBe('Ablauf bestätigen')
        ->and($task->shoot_id)->toBe($shoot->id)
        ->and($task->task_template_id)->toBeNull();
});

it('writes a note under the day it is about, not the day it was typed', function () {
    $this->post("/auftraege/{$this->project->public_id}/notizen", [
        'body' => 'Angerufen, Termin steht.',
        'occurred_at' => '2026-04-09',
    ])->assertRedirect();

    expect(InternalNote::sole()->occurred_at->toDateString())->toBe('2026-04-09');

    $entry = collect(props("/auftraege/{$this->project->public_id}")['thread'])
        ->firstWhere('body', 'Angerufen, Termin steht.');
    expect($entry['at'])->toStartWith('2026-04-09');

    $this->post("/auftraege/{$this->project->public_id}/notizen", [
        'body' => 'Zu früh.',
        'occurred_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('occurred_at');
});

it('rejects an empty note', function () {
    $this->post("/auftraege/{$this->project->public_id}/notizen", ['body' => ''])
        ->assertSessionHasErrors('body');
});

// C-12 (#248): editing is a state of the same screen, so it posts back to the
// record. One save for the whole state — the same answer #245 gives for the
// inquiry, so the two screens behave alike.
it('saves the name, the stage and the shoot blocks from the project screen', function () {
    $project = Project::factory()->create(['name' => 'Alt']);
    $lead = Lead::factory()->for($project)->atStage(LeadStage::New)->create();
    $shoot = Shoot::factory()->for($project)->primary()->on('2026-06-14')->create();

    test()->actingAs(test()->staff, 'staff')
        ->patch("/auftraege/{$project->public_id}", [
            'name' => 'Neu',
            'stage' => LeadStage::Contacted->value,
            'shoots' => [[
                'id' => $shoot->public_id,
                'date' => '2026-06-20',
                'call_time' => '10:00',
                'end_time' => null,
                'venue_name' => 'Gut Basthorst',
                'venue_city' => 'Basthorst',
            ]],
        ])
        ->assertRedirect();

    expect($project->fresh()->name)->toBe('Neu')
        ->and($lead->fresh()->stage->key)->toBe(LeadStage::Contacted->value)
        ->and($shoot->fresh()->date->format('Y-m-d'))->toBe('2026-06-20')
        ->and($shoot->fresh()->venue_name)->toBe('Gut Basthorst');
});
