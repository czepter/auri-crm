<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Engagement\Actions\PostMessage;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Engagement\Notifications\StaffReplyPosted;
use App\Engagement\Support\MessageIdentifier;
use App\Enums\StaffRole;
use App\Models\User;
use App\Notifications\Studio\ClientMessagePosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function threadWithClient(): Thread
{
    $project = bookedContactProject();
    $contact = $project->contacts()->sole();
    $contact->update(['email' => 'lena@example.test']);

    $thread = Thread::factory()->for($project)->create();
    $thread->participants()->create(['contact_id' => $contact->id]);

    return $thread;
}

it('mails a staff reply to the client and keeps the row as the record', function () {
    Notification::fake();

    $thread = threadWithClient();
    $message = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Die Bilder sind fertig.');

    // participants() now holds two rows — PostMessage records the staff
    // member as one the moment they reply — so the recipient is picked by
    // being the contact, not by being the only one.
    Notification::assertSentTo(
        $thread->participants()->whereNotNull('contact_id')->sole()->contact,
        StaffReplyPosted::class,
    );

    expect($message->fresh()->body)->toBe('Die Bilder sind fertig.')
        ->and($message->fresh()->delivery_state)->toBe('sent');
});

// T-11 moved the second half of this: a client's message is never mailed
// back to the client, but it is what tells the studio to open the thread.
it('never mails the client a message the client wrote', function () {
    Notification::fake();

    $thread = threadWithClient();
    $contact = $thread->participants()->sole()->contact;

    app(PostMessage::class)($thread, $contact, 'Wann kommen die Bilder?');

    Notification::assertNothingSentTo($contact);
    Notification::assertSentTo(User::query()->get(), ClientMessagePosted::class);
    expect(Message::sole()->delivery_state)->toBeNull();
});

it('knows the Message-ID before the send, so a reply has something to thread against', function () {
    Notification::fake();

    $thread = threadWithClient();
    $message = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Kurz zurück.');

    expect($message->fresh()->email_message_id)
        ->toBe(MessageIdentifier::for($message))
        ->toStartWith('<msg-')
        ->toEndWith('>');
});

it('writes one message id per message and never two the same', function () {
    Notification::fake();

    $thread = threadWithClient();
    $first = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Eins.');
    $second = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Zwei.');

    expect($first->fresh()->email_message_id)->not->toBe($second->fresh()->email_message_id);
});

it('sends nothing, and claims nothing, when no participant has an address', function () {
    Notification::fake();

    $project = bookedContactProject();
    $project->contacts()->sole()->update(['email' => null]);
    $thread = Thread::factory()->for($project)->create();
    $thread->participants()->create(['contact_id' => $project->contacts()->sole()->id]);

    $message = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Hallo?');

    Notification::assertNothingSent();
    expect($message->fresh()->delivery_state)->toBeNull()
        ->and($message->fresh()->email_message_id)->toBeNull();
});

it('shows a contact every thread they are on, across projects', function () {
    Notification::fake();

    // One contact on two projects. bookedContactProject() is not reused for the
    // second: it mints its own contact at a fixed address, and C-01's
    // partial unique index on (tenant_id, email) means a second row with
    // that address cannot exist — the same rule C-08 relies on.
    $contact = Contact::factory()->create(['name' => 'Lena Meier', 'email' => 'lena@example.test']);

    foreach (['Projekt A', 'Projekt B'] as $name) {
        $project = Project::factory()->create(['name' => $name]);
        $project->attachContact($contact, ProjectContactRole::Primary);

        $thread = Thread::factory()->for($project)->create();
        $thread->participants()->create(['contact_id' => $contact->id]);
        app(PostMessage::class)($thread, staffMember(StaffRole::Owner), "Nachricht zu {$name}");
    }

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get("/kontakte/{$contact->public_id}")
        ->assertOk()
        ->assertInertia(function ($page) {
            $rows = collect($page->toArray()['props']['correspondence']);

            expect($rows)->toHaveCount(2)
                ->and($rows->pluck('projectName')->sort()->values()->all())->toBe(['Projekt A', 'Projekt B']);
        });
});
