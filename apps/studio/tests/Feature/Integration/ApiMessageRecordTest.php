<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
});

it('records what the client wrote elsewhere, backdated, opening the thread and sending nothing', function () {
    Notification::fake();
    Mail::fake();

    $token = ApiToken::factory()->withScopes(Capability::ManageMessages)->create();
    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'message-1')
        ->postJson("/api/projects/{$project->public_id}/messages", [
            'from' => 'client',
            'body' => 'Wie wäre es diesen Samstag?',
            'sent_at' => '2026-06-25T18:32:00+02:00',
        ]);

    $response->assertCreated()->assertJson(['author' => $contact->name, 'fromStaff' => false]);

    $message = Message::sole();
    expect($message->contact_id)->toBe($contact->id)
        ->and($message->thread->case_id)->toBe($project->id)
        ->and($message->created_at->equalTo(Carbon::parse('2026-06-25T18:32:00+02:00')))->toBeTrue()
        ->and(integerIdPaths($response->json()))->toBe([]);

    Notification::assertNothingSent();
    Mail::assertNothingSent();
});

it('keeps a thread\'s state on its newest line when an older one is written in', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageMessages)->create();
    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);
    $thread = Thread::factory()->for($project, 'case')->create();
    Message::factory()->for($thread)->fromStaff()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'message-2')
        ->postJson("/api/projects/{$project->public_id}/messages", [
            'from' => 'client',
            'body' => 'Frühere Frage',
            'sent_at' => now()->subWeek()->toIso8601String(),
        ])
        ->assertCreated();

    expect($thread->fresh()->workState())->toBe('wartet');
});

it('refuses a studio line from a token no staff member stands behind', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageMessages)->create();
    $project = ClientCase::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'message-3')
        ->postJson("/api/projects/{$project->public_id}/messages", ['from' => 'studio', 'body' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('from');

    expect(Message::count())->toBe(0);
});
