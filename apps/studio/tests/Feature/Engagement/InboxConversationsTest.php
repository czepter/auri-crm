<?php

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

function threadWhereLastSpoke(string $who, string $subject = 'Frage'): Thread
{
    $thread = Thread::factory()->for(Project::factory())->create(['subject' => $subject]);

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
    $thread = Thread::factory()->create(['project_id' => null, 'subject' => 'Allgemein']);
    Message::factory()->for($thread)->create([
        'contact_id' => Contact::factory()->create()->id,
        'author_id' => null,
    ]);

    $this->post("/posteingang/unterhaltung/{$thread->public_id}/antwort", ['body' => 'Sehr gern.'])
        ->assertRedirect();

    expect($thread->fresh()->latestMessage->body)->toBe('Sehr gern.')
        ->and($thread->fresh()->workState())->toBe('wartet');
});
