<?php

// apps/studio/tests/Feature/Staff/EmailVerificationTest.php

use App\Models\User;
use App\Models\UserEmailVerification;
use App\Staff\Notifications\StudioEmailVerification;
use App\Staff\Notifications\StudioWelcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createOwner(): User
{
    test()->post('/einrichtung', [
        'name' => 'Christian Zepter',
        'email' => 'christian@nordlicht.test',
        'password' => 'ein-sehr-langes-passwort',
        'password_confirmation' => 'ein-sehr-langes-passwort',
    ]);

    return User::where('email', 'christian@nordlicht.test')->sole();
}

it('sends a verification mail on setup', function () {
    Notification::fake();

    $user = createOwner();

    Notification::assertSentTo($user, StudioEmailVerification::class);
});

it('also sends the welcome mail on setup', function () {
    Notification::fake();

    $user = createOwner();

    Notification::assertSentTo($user, StudioWelcome::class);
});

it('consumes the record and stamps the user via the link', function () {
    $user = createOwner();
    $verification = UserEmailVerification::where('user_id', $user->id)->sole();
    $token = 'known-test-token';
    $verification->forceFill(['token_hash' => UserEmailVerification::hash($token)])->save();

    $this->get("/e-mail-bestaetigen/{$token}")->assertRedirect('/login');

    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and($verification->fresh()->consumed_at)->not->toBeNull();
});

it('consumes the record and stamps the user via the right code', function () {
    $user = createOwner();
    $verification = UserEmailVerification::where('user_id', $user->id)->sole();
    $verification->forceFill(['code_hash' => UserEmailVerification::hash('123456')])->save();

    $this->actingAs($user, 'staff')
        ->post('/einrichtung/e-mail-bestaetigen', ['code' => '123456'])
        ->assertRedirect();

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects a wrong code and counts the attempt', function () {
    $user = createOwner();
    $verification = UserEmailVerification::where('user_id', $user->id)->sole();
    $verification->forceFill(['code_hash' => UserEmailVerification::hash('123456')])->save();

    $this->actingAs($user, 'staff')
        ->post('/einrichtung/e-mail-bestaetigen', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($verification->fresh()->attempts)->toBe(1)
        ->and($user->fresh()->email_verified_at)->toBeNull();
});

it('locks out the sixth attempt even with the right code', function () {
    $user = createOwner();
    $verification = UserEmailVerification::where('user_id', $user->id)->sole();
    $verification->forceFill(['code_hash' => UserEmailVerification::hash('123456'), 'attempts' => 5])->save();

    $this->actingAs($user, 'staff')
        ->post('/einrichtung/e-mail-bestaetigen', ['code' => '123456'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('treats an expired record the same as a consumed or missing one', function () {
    $user = createOwner();
    $verification = UserEmailVerification::where('user_id', $user->id)->sole();
    $token = 'known-test-token';
    $verification->forceFill(['token_hash' => UserEmailVerification::hash($token), 'expires_at' => now()->subMinute()])->save();

    $this->get("/e-mail-bestaetigen/{$token}")->assertRedirect('/login')->assertSessionHas('status');

    expect($user->fresh()->email_verified_at)->toBeNull();
});
