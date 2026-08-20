<?php

use App\Gallery\Notifications\AssetCommented;
use App\Gallery\Notifications\AssetFavorited;
use App\Models\UserEmailChange;
use App\Notifications\Models\NotificationPreference;
use App\Notifications\StaffEmailChangeConfirmation;
use App\Notifications\Support\NotificationPreferences;
use App\Notifications\Support\StudioEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('lets anybody with a session reach their own profile', function () {
    $this->actingAs(staffMember(), 'staff')
        ->get('/profil')
        ->assertOk();
});

it('renames without asking for the password', function () {
    $user = staffMember();

    $this->actingAs($user, 'staff')
        ->patch('/profil', ['name' => 'Nina Kowalski', 'email' => $user->email])
        ->assertRedirect();

    expect($user->fresh()->name)->toBe('Nina Kowalski');
});

// The address is the login, and a studio account has nobody above it to fix a
// typo — /team invites, it never edits an address.
it('never writes a new e-mail before the new address confirms it', function () {
    Notification::fake();

    $user = staffMember();
    $user->forceFill(['email' => 'alt@studio.test', 'password' => Hash::make('correct-horse')])->save();

    $this->actingAs($user, 'staff')
        ->patch('/profil', [
            'name' => $user->name,
            'email' => 'neu@studio.test',
            'current_password' => 'correct-horse',
        ])
        ->assertRedirect();

    expect($user->fresh()->email)->toBe('alt@studio.test')
        ->and(UserEmailChange::query()->live()->where('user_id', $user->id)->count())->toBe(1);

    Notification::assertSentOnDemand(
        StaffEmailChangeConfirmation::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'neu@studio.test',
    );
});

it('refuses the e-mail change without the current password', function () {
    $user = staffMember();
    $user->forceFill(['email' => 'alt@studio.test', 'password' => Hash::make('correct-horse')])->save();

    $this->actingAs($user, 'staff')
        ->patch('/profil', [
            'name' => $user->name,
            'email' => 'neu@studio.test',
            'current_password' => 'falsch',
        ])
        ->assertSessionHasErrors('current_password');

    expect(UserEmailChange::query()->count())->toBe(0);
});

it('moves the login once the link from the new inbox is followed', function () {
    $user = staffMember();
    $user->forceFill(['email' => 'alt@studio.test'])->save();

    $token = bin2hex(random_bytes(8));

    UserEmailChange::create([
        'user_id' => $user->id,
        'new_email' => 'neu@studio.test',
        'token_hash' => UserEmailChange::hash($token),
        'expires_at' => now()->addHour(),
    ]);

    $this->get("/profil/e-mail-bestaetigen/{$token}")->assertRedirect('/profil');

    expect($user->fresh()->email)->toBe('neu@studio.test');

    // Spent: following it twice does not reopen the change.
    $this->get("/profil/e-mail-bestaetigen/{$token}")->assertRedirect('/login');
});

it('changes the password only against the current one', function () {
    $user = staffMember();
    $user->forceFill(['password' => Hash::make('correct-horse')])->save();

    $this->actingAs($user, 'staff')
        ->put('/profil/passwort', [
            'current_password' => 'falsch',
            'password' => 'battery-staple-42',
            'password_confirmation' => 'battery-staple-42',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('correct-horse', $user->fresh()->password))->toBeTrue();

    $this->actingAs($user, 'staff')
        ->put('/profil/passwort', [
            'current_password' => 'correct-horse',
            'password' => 'battery-staple-42',
            'password_confirmation' => 'battery-staple-42',
        ])
        ->assertRedirect();

    expect(Hash::check('battery-staple-42', $user->fresh()->password))->toBeTrue();
});

it('replaces every recovery code at once', function () {
    $user = staffMember();
    $before = $user->mfa_recovery_codes;

    $this->actingAs($user, 'staff')
        ->post('/profil/wiederherstellungscodes')
        ->assertRedirect('/auth/mfa/recovery-codes')
        ->assertSessionHas('recovery_codes');

    expect($user->fresh()->mfa_recovery_codes)->toHaveCount(8)
        ->and($user->fresh()->mfa_recovery_codes)->not->toBe($before);
});

it('suppresses a gallery notification the staff member turned off', function () {
    $user = staffMember();

    $modes = collect(StudioEvents::ALL)->map(fn () => 'immediate')->all();

    $this->actingAs($user, 'staff')
        ->patch('/profil/benachrichtigungen', [
            'digest_frequency' => null,
            'events' => [...$modes, 'assetCommented' => 'off'],
        ])
        ->assertRedirect();

    // Two rows per event: one per channel — see ProfileController::MODES.
    expect(NotificationPreference::query()->count())->toBe(count(StudioEvents::ALL) * 2);

    $preferences = app(NotificationPreferences::class);

    expect($preferences->enabled($user, AssetCommented::class, 'in_app'))->toBeFalse()
        ->and($preferences->enabled($user, AssetFavorited::class, 'in_app'))->toBeTrue();
});
