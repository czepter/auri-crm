<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Engagement\Enums\InboundMessageState;
use App\Engagement\Models\InboundMessage;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Engagement\Models\ThreadParticipant;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    Notification::fake();
    Mail::fake();
});

function apiToken(): ApiToken
{
    return ApiToken::factory()->withScopes(Capability::ManageMessages)->create();
}

it('lists inbox threads', function () {
    $project = ClientCase::factory()->create();
    $thread = Thread::factory()->for($project, 'case')->create();
    Message::factory()->for($thread)->create();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken(apiToken()))
        ->getJson('/api/inbox/threads');

    $response->assertOk()->assertJsonPath('data.0.id', $thread->public_id);
    expect(integerIdPaths($response->json()))->toBe([]);
});

it('replies on an inbox thread, requiring a staff actor', function () {
    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);
    $thread = Thread::factory()->for($project, 'case')->create();
    Message::factory()->for($thread)->for($contact)->create();
    $staff = staffMember();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($staff))
        ->withHeader('Idempotency-Key', 'inbox-reply-1')
        ->postJson("/api/inbox/threads/{$thread->public_id}/reply", ['body' => 'Danke für die Nachricht.'])
        ->assertCreated()
        ->assertJson(['fromStaff' => true]);

    Auth::forgetGuards(); // see ApiIdempotencyTest's own note on why

    $this->withHeader('Authorization', 'Bearer '.bearerToken(apiToken()))
        ->withHeader('Idempotency-Key', 'inbox-reply-2')
        ->postJson("/api/inbox/threads/{$thread->public_id}/reply", ['body' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('actor');
});

it('closes and reopens a thread by its work state', function () {
    $project = ClientCase::factory()->create();
    $thread = Thread::factory()->for($project, 'case')->create();
    Message::factory()->for($thread)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken(apiToken()))
        ->withHeader('Idempotency-Key', 'inbox-close-1')
        ->postJson("/api/inbox/threads/{$thread->public_id}/close")
        ->assertOk()
        ->assertJson(['state' => 'erledigt']);

    expect($thread->fresh()->closed_message_id)->not->toBeNull();
});

it('attaches an inbound mail to the thread it belongs on', function () {
    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create(['email' => 'client@example.com']);
    $project->attachContact($contact, CaseContactRole::Primary);
    $thread = Thread::factory()->for($project, 'case')->create();
    Message::factory()->for($thread)->for($contact)->create();
    ThreadParticipant::factory()->for($thread)->for($contact)->create();

    $inbound = InboundMessage::create([
        'from_address' => 'client@example.com',
        'to_address' => 'studio@example.com',
        'body' => 'Wie sieht es am Samstag aus?',
        'state' => InboundMessageState::Triage,
    ]);

    $this->withHeader('Authorization', 'Bearer '.bearerToken(apiToken()))
        ->withHeader('Idempotency-Key', 'inbox-attach-1')
        ->postJson("/api/inbox/messages/{$inbound->public_id}/attach", ['thread' => $thread->public_id])
        ->assertCreated();

    expect($inbound->fresh()->state)->toBe(InboundMessageState::Threaded)
        ->and($inbound->fresh()->thread_id)->toBe($thread->id);
});

it('files an unfiled thread to a project, but not a thread that already names one', function () {
    $thread = Thread::factory()->create(['case_id' => null]);
    $project = ClientCase::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken(apiToken()))
        ->withHeader('Idempotency-Key', 'inbox-file-1')
        ->postJson("/api/inbox/threads/{$thread->public_id}/file", ['project' => $project->public_id])
        ->assertOk()
        ->assertJson(['projectId' => $project->public_id]);

    $other = ClientCase::factory()->create();
    $this->withHeader('Authorization', 'Bearer '.bearerToken(apiToken()))
        ->withHeader('Idempotency-Key', 'inbox-file-2')
        ->postJson("/api/inbox/threads/{$thread->public_id}/file", ['project' => $other->public_id])
        ->assertUnprocessable();
});
