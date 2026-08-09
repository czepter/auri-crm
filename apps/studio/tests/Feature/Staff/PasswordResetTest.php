<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('sends a reset link', function () {
    Notification::fake();
    $user = User::factory()->withMfa()->create();

    $this->post('/passwort/vergessen', ['email' => $user->email])->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('answers the same for an address with no account', function () {
    // A differing response tells a stranger which addresses exist.
    Notification::fake();

    $known = User::factory()->create();

    $first = $this->post('/passwort/vergessen', ['email' => $known->email]);
    $second = $this->post('/passwort/vergessen', ['email' => 'niemand@nordlicht.test']);

    expect($second->getStatusCode())->toBe($first->getStatusCode())
        ->and(session('status'))->toBe(__('auth.reset_sent'));
});

it('changes the password without signing anyone in', function () {
    // The story's acceptance: a reset alone does not grant access.
    $user = User::factory()->withMfa()->create();
    $token = Password::broker()->createToken($user);

    $this->post('/passwort/neu', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'ein-ganz-neues-passwort',
        'password_confirmation' => 'ein-ganz-neues-passwort',
    ])->assertRedirect('/login');

    expect(auth('staff')->check())->toBeFalse()
        ->and(Hash::check('ein-ganz-neues-passwort', $user->fresh()->password))->toBeTrue();

    // And the protected surface is still shut.
    $this->get('/')->assertRedirect('/login');
});

it('never clears the second factor', function () {
    // A reset that unset MFA would turn "I forgot my password" into a way
    // around it — which is the one thing this flow must never become.
    $user = User::factory()->withMfa()->create();
    $secret = $user->mfa_secret;
    $token = Password::broker()->createToken($user);

    $this->post('/passwort/neu', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'ein-ganz-neues-passwort',
        'password_confirmation' => 'ein-ganz-neues-passwort',
    ]);

    $fresh = $user->fresh();

    expect($fresh->hasConfirmedMfa())->toBeTrue()
        ->and($fresh->mfa_secret)->toBe($secret);
});

it('still demands the second factor after a reset', function () {
    $user = User::factory()->withMfa()->create();
    $token = Password::broker()->createToken($user);

    $this->post('/passwort/neu', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'ein-ganz-neues-passwort',
        'password_confirmation' => 'ein-ganz-neues-passwort',
    ]);

    // The new password gets them as far as the challenge, and no further.
    $this->post('/login', ['email' => $user->email, 'password' => 'ein-ganz-neues-passwort'])
        ->assertRedirect('/auth/mfa');

    $this->get('/')->assertRedirect('/login');
});

it('refuses a spent or forged token', function () {
    $user = User::factory()->withMfa()->create();

    $this->post('/passwort/neu', [
        'token' => 'nicht-echt',
        'email' => $user->email,
        'password' => 'ein-ganz-neues-passwort',
        'password_confirmation' => 'ein-ganz-neues-passwort',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('ein-ganz-neues-passwort', $user->fresh()->password))->toBeFalse();
});

it('rejects a weak password', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->post('/passwort/neu', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'kurz',
        'password_confirmation' => 'kurz',
    ])->assertSessionHasErrors('password');
});
