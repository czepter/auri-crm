<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Crm\Notifications\Studio\InquiryReceived;
use App\Engagement\Actions\StartThread;
use App\Engagement\Notifications\Studio\ClientMessagePosted;
use App\Models\User;
use App\Notifications\Enums\NotificationMode;
use App\Notifications\Models\InAppNotification;
use App\Notifications\Support\NotificationPreferences;
use App\Notifications\Transports\InAppTransport;
use App\Payments\Notifications\Studio\InstallmentPaid;
use App\Staff\Actions\SendStudioDigests;
use App\Staff\Notifications\StudioDigest;
use App\Staff\Support\StudioEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('tells the whole studio when the project has no crew', function () {
    Notification::fake();

    staffMember();
    staffMember();

    app(StartThread::class)(null, Contact::factory()->create(), 'Frage', 'Wann?');

    Notification::assertSentTo(User::query()->get(), ClientMessagePosted::class);
});

// The point of narrowing: a second shooter on somebody else's wedding does
// not get told about it.
it('tells only the crew of the project where there is one', function () {
    Notification::fake();

    $crewed = staffMember();
    $uninvolved = staffMember();

    $project = ClientCase::factory()->create();
    $shoot = Shoot::factory()->create(['case_id' => $project->id]);
    CrewAssignment::factory()->create(['shoot_id' => $shoot->id, 'user_id' => $crewed->id]);

    app(StartThread::class)($project, Contact::factory()->create(), 'Frage', 'Wann?');

    Notification::assertSentTo($crewed, ClientMessagePosted::class);
    Notification::assertNotSentTo($uninvolved, ClientMessagePosted::class);
});

it('writes an in-app row even when the mail is turned off', function () {
    $user = staffMember();

    app(NotificationPreferences::class)->set($user, ClientMessagePosted::class, 'mail', false);

    app(StartThread::class)(null, Contact::factory()->create(), 'Frage', 'Wann?');

    expect(InAppNotification::query()->where('type', ClientMessagePosted::class)->count())->toBe(1);
});

it('carries only the events set to the summary, and never the ones already mailed', function () {
    Notification::fake();

    $user = staffMember();
    $user->forceFill(['digest_frequency' => 'daily'])->save();

    // One digested, one immediate.
    app(NotificationPreferences::class)->setMode($user, ClientMessagePosted::class, NotificationMode::Digest);

    InAppNotification::create([
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'type' => ClientMessagePosted::class,
        'data' => ['title' => 'Neue Nachricht', 'body' => 'Meier'],
    ]);
    InAppNotification::create([
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'type' => InstallmentPaid::class,
        'data' => ['title' => 'Zahlung eingegangen', 'body' => 'Meier'],
    ]);

    expect(app(SendStudioDigests::class)())->toBe(1);

    Notification::assertSentTo($user, StudioDigest::class, function (StudioDigest $digest) use ($user) {
        $lines = collect($digest->toMail($user)->introLines);

        return $lines->contains(fn (string $line) => str_contains($line, 'Neue Nachricht'))
            && $lines->doesntContain(fn (string $line) => str_contains($line, 'Zahlung'));
    });

    expect($user->fresh()->digest_sent_at)->not->toBeNull();
});

it('holds a weekly summary until the week is up', function () {
    Notification::fake();

    $user = staffMember();
    $user->forceFill(['digest_frequency' => 'weekly', 'digest_sent_at' => now()->subDays(3)])->save();

    expect(app(SendStudioDigests::class)())->toBe(0);

    $user->forceFill(['digest_sent_at' => now()->subDays(8)])->save();
    app(NotificationPreferences::class)->setMode($user, ClientMessagePosted::class, NotificationMode::Digest);

    InAppNotification::create([
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'type' => ClientMessagePosted::class,
        'data' => ['title' => 'Neue Nachricht', 'body' => 'Meier'],
    ]);

    expect(app(SendStudioDigests::class)())->toBe(1);
});

it('lets a staff member read and clear their own inbox', function () {
    $user = staffMember();

    InAppNotification::create([
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'type' => ClientMessagePosted::class,
        'data' => ['title' => 'Neue Nachricht', 'body' => 'Meier', 'url' => '/posteingang'],
    ]);

    $this->actingAs($user, 'staff')->get('/benachrichtigungen')->assertOk();

    $this->actingAs($user, 'staff')->post('/benachrichtigungen/gelesen')->assertRedirect();

    expect(InAppNotification::sole()->read_at)->not->toBeNull();
});

it('stores one three-way choice as the two preference rows behind it', function () {
    $user = staffMember();

    $modes = collect(StudioEvents::ALL)->map(fn () => 'off')->all();

    $this->actingAs($user, 'staff')
        ->patch('/profil/benachrichtigungen', [
            'digest_frequency' => 'weekly',
            'events' => [...$modes, 'clientMessage' => 'digest'],
        ])
        ->assertRedirect();

    $preferences = app(NotificationPreferences::class);

    expect($user->fresh()->digest_frequency)->toBe('weekly')
        ->and($preferences->enabled($user, ClientMessagePosted::class, 'mail'))->toBeFalse()
        ->and($preferences->enabled($user, ClientMessagePosted::class, 'in_app'))->toBeTrue();
});

it('drops the in-app row when its project goes, so the link cannot 404', function () {
    $user = staffMember();
    $project = ClientCase::factory()->create();

    app(InAppTransport::class)
        ->send($user, new InquiryReceived(
            Lead::factory()->create(['case_id' => $project->id])
        ));

    expect(InAppNotification::count())->toBe(1);

    $project->delete();

    expect(InAppNotification::count())->toBe(0);
});
