<?php

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Engagement\Actions\PostMessage;
use App\Engagement\Actions\StartThread;
use App\Engagement\Models\Thread;
use App\Engagement\Models\ThreadParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the thread, its first message and its first participant, all from the client', function () {
    $project = Project::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);

    $thread = app(StartThread::class)($project, $contact, 'Frage zum Ablauf', 'Dürfen wir nah an den Altar?');

    expect($thread->project->is($project))->toBeTrue()
        ->and($thread->subject)->toBe('Frage zum Ablauf')
        ->and($thread->messages)->toHaveCount(1)
        ->and($thread->messages->first()->body)->toBe('Dürfen wir nah an den Altar?')
        ->and($thread->messages->first()->contact->is($contact))->toBeTrue()
        ->and($thread->messages->first()->author_id)->toBeNull();

    expect(ThreadParticipant::where('thread_id', $thread->id)->where('contact_id', $contact->id)->exists())
        ->toBeTrue();
});

it('lets staff reply on a thread the client started, without becoming its author', function () {
    $project = Project::factory()->create();
    $contact = Contact::factory()->create();
    $thread = app(StartThread::class)($project, $contact, 'Thema', 'Erste Nachricht.');
    $staff = User::factory()->create();

    $reply = app(PostMessage::class)($thread, $staff, 'Antwort vom Studio.');

    expect($reply->contact_id)->toBeNull()
        ->and($reply->author->is($staff))->toBeTrue()
        ->and($thread->fresh()->messages)->toHaveCount(2);
});

it('never creates a second thread for the same project through the action alone', function () {
    // StartThread has no such guard by itself — that rule belongs to whoever
    // calls it, see the portal controller test. This just proves each call
    // makes its own row, so the calling code has something real to guard.
    $project = Project::factory()->create();
    $contact = Contact::factory()->create();

    app(StartThread::class)($project, $contact, 'Erstes Thema', 'Nachricht eins.');
    app(StartThread::class)($project, $contact, 'Zweites Thema', 'Nachricht zwei.');

    expect(Thread::where('project_id', $project->id)->count())->toBe(2);
});

// P-04 (#223): before the first booking there is nothing to hang a thread on,
// and that is exactly when a client most needs to ask something.
it('starts a thread with no booking at all', function () {
    $contact = Contact::factory()->create();

    $thread = app(StartThread::class)(null, $contact, 'Im Herbst noch etwas frei?', 'Wir würden gern im Oktober.');

    expect($thread->project_id)->toBeNull()
        ->and($thread->messages)->toHaveCount(1)
        ->and($thread->messages->first()->contact->is($contact))->toBeTrue();
});

it('files a booking-less thread onto a project, once', function () {
    $contact = Contact::factory()->create();
    $thread = app(StartThread::class)(null, $contact, 'Thema', 'Nachricht.');
    $project = Project::factory()->create();

    app(App\Engagement\Actions\AttachThreadToProject::class)($thread, $project);

    expect($thread->fresh()->project->is($project))->toBeTrue();

    app(App\Engagement\Actions\AttachThreadToProject::class)($thread->fresh(), Project::factory()->create());
})->throws(LogicException::class);
