<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('staff-login|127.0.0.1');
    RateLimiter::clear('staff-mfa|127.0.0.1');
});

it('sends an enrolled-but-unconfirmed user to enrollment instead of the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'staff')
        ->get('/')
        ->assertRedirect('/auth/mfa/enroll');
});

it('issues a secret and an otpauth uri on the enrollment page', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'staff')
        ->get('/auth/mfa/enroll')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/MfaEnroll')
            ->where('secret', fn (string $secret) => strlen($secret) === 32)
            ->where('otpauthUri', fn (string $uri) => str_starts_with($uri, 'otpauth://totp/'))
        );
});

it('reuses the same secret when the enrollment page is reloaded', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user, 'staff')->get('/auth/mfa/enroll');
    $second = $this->actingAs($user, 'staff')->get('/auth/mfa/enroll');

    expect($first->viewData('page')['props']['secret'])
        ->toBe($second->viewData('page')['props']['secret']);
});

it('confirms enrollment with a valid code and shows recovery codes once', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'staff');

    $secret = $this->get('/auth/mfa/enroll')->viewData('page')['props']['secret'];
    $code = (new Google2FA)->getCurrentOtp($secret);

    $this->post('/auth/mfa/confirm', ['code' => $code])
        ->assertRedirect('/auth/mfa/recovery-codes');

    $this->get('/auth/mfa/recovery-codes')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/RecoveryCodes')
            ->has('codes', 8)
        );

    expect($user->fresh()->hasConfirmedMfa())->toBeTrue();

    // The flash is consumed; a reload does not show the codes again.
    $this->get('/auth/mfa/recovery-codes')->assertRedirect('/');
});

it('rejects confirmation with an invalid code', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'staff');
    $this->get('/auth/mfa/enroll');

    $this->from('/auth/mfa/enroll')
        ->post('/auth/mfa/confirm', ['code' => '000000'])
        ->assertSessionHasErrors(['code' => __('auth.mfa_invalid')]);

    expect($user->fresh()->hasConfirmedMfa())->toBeFalse();
});

it('allows the dashboard once mfa is confirmed', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'staff');

    $secret = $this->get('/auth/mfa/enroll')->viewData('page')['props']['secret'];
    $this->post('/auth/mfa/confirm', ['code' => (new Google2FA)->getCurrentOtp($secret)]);

    $this->get('/')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
});

it('completes login with a valid totp code', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->withMfa()->create(['mfa_secret' => $secret]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/auth/mfa');

    $this->post('/auth/mfa', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user, 'staff');
});

it('rejects an invalid totp code and leaves the session unauthenticated', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->withMfa()->create(['mfa_secret' => $secret]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->from('/auth/mfa')
        ->post('/auth/mfa', ['code' => '000000'])
        ->assertSessionHasErrors(['code' => __('auth.mfa_invalid')]);

    $this->assertGuest('staff');
    expect(AuditLog::where('event', 'staff.mfa.failed')->count())->toBe(1);
});

it('refuses the challenge page and the verify route without a pending login', function () {
    $this->get('/auth/mfa')->assertRedirect('/login');
    $this->post('/auth/mfa', ['code' => '123456'])->assertRedirect('/login');
});

it('throttles repeated code guesses', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->withMfa()->create(['mfa_secret' => $secret]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    foreach (range(1, 5) as $attempt) {
        $this->from('/auth/mfa')
            ->post('/auth/mfa', ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    // A valid code is refused once throttled. Asserted on the throttle line
    // rather than the invalid-code line, without pinning the countdown the
    // message interpolates.
    $this->from('/auth/mfa')
        ->post('/auth/mfa', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertSessionHasErrors('code');

    $message = session('errors')->first('code');

    expect($message)->toStartWith('Zu viele Anmeldeversuche.')
        ->and($message)->not->toBe(__('auth.mfa_invalid'));

    $this->assertGuest('staff');
});

it('accepts a recovery code once and then invalidates it', function () {
    $user = User::factory()->withMfa()->create(['mfa_recovery_codes' => ['rescue-me']]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/auth/mfa', ['code' => 'rescue-me'])->assertRedirect('/');

    expect($user->fresh()->mfa_recovery_codes)->toBe([]);
    expect(AuditLog::where('event', 'staff.mfa.recovery_code_used')->count())->toBe(1);
});
