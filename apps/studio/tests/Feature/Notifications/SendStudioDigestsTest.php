<?php

use App\Notifications\Models\InAppNotification;
use App\Notifications\Studio\ClientMessagePosted;
use App\Notifications\Studio\StudioDigest;
use App\Notifications\Support\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('is registered and mails a digest to a user who is due', function () {
    Notification::fake();

    $user = staffMember();
    $user->forceFill(['digest_frequency' => 'daily'])->save();
    app(NotificationPreferences::class)->set($user, ClientMessagePosted::class, 'mail', false);

    InAppNotification::create([
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'type' => ClientMessagePosted::class,
        'data' => ['title' => 'Neue Nachricht', 'body' => 'Meier'],
    ]);

    $this->artisan('notifications:send-digests')
        ->assertSuccessful()
        ->expectsOutputToContain('Sent 1 digest(s).');

    Notification::assertSentTo($user, StudioDigest::class);
    expect($user->fresh()->digest_sent_at)->not->toBeNull();
});
