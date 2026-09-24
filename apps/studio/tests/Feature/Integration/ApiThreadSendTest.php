<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Engagement\Jobs\SendScheduledMessage;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    Notification::fake();
    Mail::fake();
});

function apiThreadWithClient(): array
{
    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);
    $thread = Thread::factory()->for($project, 'case')->create();
    Message::factory()->for($thread)->for($contact)->create();

    return [$project, $thread];
}

it('sends a staff reply through the api surface, requiring a staff actor', function () {
    [$project] = apiThreadWithClient();
    $staff = staffMember();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($staff))
        ->withHeader('Idempotency-Key', 'send-1')
        ->postJson("/api/cases/{$project->public_id}/messages/send", ['body' => 'Wir sehen uns Samstag.']);

    $response->assertCreated()->assertJson(['fromStaff' => true]);

    expect(Message::where('author_id', $staff->id)->exists())->toBeTrue()
        ->and(integerIdPaths($response->json()))->toBe([]);
});

it('refuses a send from a token no staff member stands behind', function () {
    [$project] = apiThreadWithClient();
    $token = ApiToken::factory()->withScopes(Capability::ManageMessages)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'send-2')
        ->postJson("/api/cases/{$project->public_id}/messages/send", ['body' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('actor');

    expect(Message::count())->toBe(1); // only the seeded client message
});

it('refuses to reply on a project with no thread yet', function () {
    $project = ClientCase::factory()->create();
    $staff = staffMember();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($staff))
        ->withHeader('Idempotency-Key', 'send-3')
        ->postJson("/api/cases/{$project->public_id}/messages/send", ['body' => 'x'])
        ->assertNotFound();
});

it('schedules a future send instead of dispatching it immediately', function () {
    Queue::fake();
    [$project] = apiThreadWithClient();
    $staff = staffMember();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($staff))
        ->withHeader('Idempotency-Key', 'send-4')
        ->postJson("/api/cases/{$project->public_id}/messages/send", [
            'body' => 'Erinnerung',
            'scheduled_for' => now()->addDay()->toIso8601String(),
        ])
        ->assertCreated();

    $message = Message::where('author_id', $staff->id)->sole();
    expect($message->scheduled_for)->not->toBeNull();
    Queue::assertPushed(SendScheduledMessage::class);
});

it('cancels a scheduled message that has not gone out yet', function () {
    [$project, $thread] = apiThreadWithClient();
    $staff = staffMember();
    $message = Message::factory()->for($thread)->fromStaff($staff)->create(['scheduled_for' => now()->addDay()]);

    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageMessages)->create()))
        ->withHeader('Idempotency-Key', 'cancel-1')
        ->deleteJson("/api/messages/{$message->public_id}")
        ->assertNoContent();

    expect(Message::find($message->id))->toBeNull();
});

it('refuses to cancel a message that already went out', function () {
    [$project, $thread] = apiThreadWithClient();
    $message = Message::factory()->for($thread)->fromStaff()->create(['scheduled_for' => null]);

    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageMessages)->create()))
        ->withHeader('Idempotency-Key', 'cancel-2')
        ->deleteJson("/api/messages/{$message->public_id}")
        ->assertUnprocessable();

    expect(Message::find($message->id))->not->toBeNull();
});
