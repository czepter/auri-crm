<?php

use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

function threadWhereLastSpoke(string $who, string $subject = 'Frage'): Thread
{
    $thread = Thread::factory()->for(ClientCase::factory(), 'case')->create(['subject' => $subject]);

    Message::factory()->for($thread)->create($who === 'client'
        ? ['contact_id' => Contact::factory()->create()->id, 'author_id' => null]
        : ['contact_id' => null, 'author_id' => test()->staff->id]);

    return $thread;
}

function inbox(array $query = []): array
{
    return test()->actingAs(test()->staff, 'staff')
        ->get('/posteingang'.($query === [] ? '' : '?'.http_build_query($query)))
        ->assertOk()->viewData('page')['props'];
}

// C-14 (#250): who spoke last decides the group, and it cannot go stale.
it('groups a conversation by who owes the next line', function () {
    threadWhereLastSpoke('client', 'Unbeantwortet');
    threadWhereLastSpoke('studio', 'Wartet');

    $groups = collect(inbox()['groups'])->keyBy('key');

    expect(collect($groups['unbeantwortet']['rows'])->pluck('subject')->all())->toBe(['Unbeantwortet'])
        ->and(collect($groups['wartet']['rows'])->pluck('subject')->all())->toBe(['Wartet'])
        ->and($groups['erledigt']['rows'])->toBe([]);
});

// Not derivable — one that needs no reply looks exactly like one waiting for
// it — so it is a decision, and a new message from the client undoes it.
it('files a conversation as done, and reopens it when the client writes again', function () {
    $thread = threadWhereLastSpoke('client');

    $this->post("/posteingang/unterhaltung/{$thread->public_id}/erledigt")->assertRedirect();

    expect(collect(inbox()['groups'])->firstWhere('key', 'erledigt')['rows'])->toHaveCount(1);

    Message::factory()->for($thread)->create([
        'contact_id' => Contact::factory()->create()->id,
        'author_id' => null,
    ]);

    expect(collect(inbox()['groups'])->firstWhere('key', 'unbeantwortet')['rows'])->toHaveCount(1);
});

it('opens the first unanswered conversation, or the one asked for', function () {
    threadWhereLastSpoke('studio', 'Wartet');
    $unanswered = threadWhereLastSpoke('client', 'Unbeantwortet');

    expect(inbox()['open']['subject'])->toBe('Unbeantwortet');

    $waiting = Thread::query()->where('subject', 'Wartet')->sole();

    expect(inbox(['unterhaltung' => $waiting->public_id])['open']['subject'])->toBe('Wartet')
        ->and(inbox(['unterhaltung' => $unanswered->public_id])['open']['state'])->toBe('unbeantwortet');
});

// A conversation that names no booking still has to be answerable — the
// question that comes before the booking is the one clients most need to ask.
it('answers a thread from the inbox, booking or no booking', function () {
    $thread = Thread::factory()->create(['case_id' => null, 'subject' => 'Allgemein']);
    Message::factory()->for($thread)->create([
        'contact_id' => Contact::factory()->create()->id,
        'author_id' => null,
    ]);

    $this->post("/posteingang/unterhaltung/{$thread->public_id}/antwort", ['body' => 'Sehr gern.'])
        ->assertRedirect();

    expect($thread->fresh()->latestMessage->body)->toBe('Sehr gern.')
        ->and($thread->fresh()->workState())->toBe('wartet');
});

it('hides conversations of projects the contributor is not crewed on', function () {
    $thread = threadWhereLastSpoke('client', 'Fremd');
    $contributor = staffMember(StaffRole::Contributor);

    $props = $this->actingAs($contributor, 'staff')->get('/posteingang')->assertOk()->viewData('page')['props'];

    expect(collect($props['groups'])->flatMap(fn ($group) => $group['rows'])->pluck('subject')->all())->not->toContain('Fremd')
        ->and(collect($props['projects'])->pluck('id')->all())->not->toContain($thread->case->public_id);

    $this->post("/posteingang/unterhaltung/{$thread->public_id}/antwort", ['body' => 'Hallo'])->assertForbidden();
    expect($thread->messages()->where('author_id', $contributor->id)->exists())->toBeFalse();
});

it('refuses filing a thread into a project the contributor is not crewed on', function () {
    $thread = Thread::factory()->create(['case_id' => null]);
    $project = ClientCase::factory()->create();

    $this->actingAs(staffMember(StaffRole::Contributor), 'staff')
        ->post("/posteingang/unterhaltung/{$thread->public_id}/zuordnen", ['project' => $project->public_id])
        ->assertForbidden();

    expect($thread->fresh()->case_id)->toBeNull();
});

it('still lists the Posteingang when a draft lost its anchor', function () {
    AutomationRun::create([
        'automation_rule_id' => AutomationRule::factory()->create()->id,
        'anchorable_type' => (new ClientCase)->getMorphClass(),
        'anchorable_id' => 999999,
        'idempotency_key' => 'orphan',
        'fired_at' => now(),
        'status' => AutomationRunStatus::AwaitingApproval,
        'subject' => 'Verwaist',
    ]);

    $props = $this->actingAs(staffMember(StaffRole::Admin), 'staff')->get('/posteingang')->assertOk()->viewData('page')['props'];

    expect($props['drafts'][0]['projectName'])->toBeNull();
});
