<?php

use App\Engagement\Actions\PostMessage;
use App\Engagement\Actions\SendStaffReply;
use App\Engagement\Jobs\SendScheduledMessage;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Engagement\Notifications\StaffReplyPosted;
use App\Enums\StaffRole;
use App\Notifications\Jobs\SendMailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function scheduledThread(): Thread
{
    $project = contractProject();
    $project->contacts()->sole()->update(['email' => 'lena@example.test']);

    $thread = Thread::factory()->for($project)->create();
    $thread->participants()->create(['contact_id' => $project->contacts()->sole()->id]);

    return $thread;
}

it('holds a scheduled message in the thread instead of sending it', function () {
    Notification::fake();
    Bus::fake();

    $thread = scheduledThread();
    $when = now()->addDays(3);

    $message = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Bis Montag.', $when);

    Notification::assertNothingSent();
    Bus::assertDispatched(SendScheduledMessage::class);

    expect($message->fresh()->scheduled_for->toIso8601String())->toBe($when->toIso8601String())
        ->and($message->fresh()->delivery_state)->toBeNull();
});

it('sends immediately when no time is given', function () {
    Notification::fake();

    $thread = scheduledThread();
    $message = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Sofort.');

    expect($message->fresh()->delivery_state)->toBe('sent')
        ->and($message->fresh()->scheduled_for)->toBeNull();
});

it('sends through the same path when the job fires', function () {
    Notification::fake();
    Bus::fake();

    $thread = scheduledThread();
    $when = now()->addDay();
    $message = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Später.', $when);

    (new SendScheduledMessage($message, $when->toIso8601String()))->handle(app(SendStaffReply::class));

    expect($message->fresh()->delivery_state)->toBe('sent')
        ->and($message->fresh()->email_message_id)->not->toBeNull();
});

it('sends nothing when the message was cancelled before its job fired', function () {
    Notification::fake();
    // phpunit.xml pins QUEUE_CONNECTION=sync, and the sync driver runs a
    // delayed job immediately — so every test that needs to observe the
    // pending state has to hold the job itself.
    Bus::fake();

    $thread = scheduledThread();
    $when = now()->addDay();
    $message = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Doch nicht.', $when);
    $id = $message->id;

    $message->delete();

    (new SendScheduledMessage($message, $when->toIso8601String()))->handle(app(SendStaffReply::class));

    Notification::assertNothingSent();
    expect(Message::find($id))->toBeNull();
});

it('lets a stale job expire silently when the message was rescheduled', function () {
    Notification::fake();
    Bus::fake();

    $thread = scheduledThread();
    $original = now()->addDay();
    $message = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Verschoben.', $original);

    $message->update(['scheduled_for' => now()->addWeek()]);

    // The job dispatched for the original time finds a row that no longer
    // agrees with it, and does nothing rather than sending early.
    (new SendScheduledMessage($message, $original->toIso8601String()))->handle(app(SendStaffReply::class));

    Notification::assertNothingSent();
    expect($message->fresh()->delivery_state)->toBeNull();
});

it('never sends one message twice, however often its job runs', function () {
    Notification::fake();
    Bus::fake();

    $thread = scheduledThread();
    $when = now()->addDay();
    $message = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Einmal.', $when);
    $send = app(SendStaffReply::class);

    (new SendScheduledMessage($message, $when->toIso8601String()))->handle($send);
    (new SendScheduledMessage($message, $when->toIso8601String()))->handle($send);

    // Counted on the queue, not at the mailer: MailTransport dispatches the
    // send rather than performing it, and Bus::fake() holds it here.
    Bus::assertDispatchedTimes(SendMailNotification::class, 1);
    Bus::assertDispatched(
        SendMailNotification::class,
        fn (SendMailNotification $job) => $job->notification instanceof StaffReplyPosted,
    );
});

// --- the screen ---------------------------------------------------------

it('refuses a send time already in the past', function () {
    $thread = scheduledThread();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/auftraege/{$thread->project->public_id}/nachrichten", [
            'body' => 'Rückwirkend.',
            'scheduled_for' => now()->subHour()->format('Y-m-d\TH:i'),
        ])
        ->assertInvalid(['scheduled_for']);
});

it('cancels a pending message from the thread screen, and only a pending one', function () {
    Notification::fake();
    Bus::fake();

    $thread = scheduledThread();
    $pending = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Wartet.', now()->addDay());
    $sent = app(PostMessage::class)($thread, staffMember(StaffRole::Owner), 'Ist raus.');

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->delete("/auftraege/{$thread->project->public_id}/nachrichten/{$pending->public_id}")
        ->assertRedirect();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->delete("/auftraege/{$thread->project->public_id}/nachrichten/{$sent->public_id}")
        ->assertStatus(422);

    expect(Message::find($pending->id))->toBeNull()
        ->and(Message::find($sent->id))->not->toBeNull();
});
