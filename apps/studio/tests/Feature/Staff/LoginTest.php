<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => RateLimiter::clear('staff-login|127.0.0.1'));

it('signs in a user without mfa and starts a staff session', function () {
    $user = User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->post('/login', ['email' => 'owner@studio.test', 'password' => 'correct-horse'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user, 'staff');
});

it('sends a user with confirmed mfa to the challenge without starting a session', function () {
    User::factory()->withMfa()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->post('/login', ['email' => 'owner@studio.test', 'password' => 'correct-horse'])
        ->assertRedirect('/auth/mfa');

    $this->assertGuest('staff');
});

it('returns an identical error for a wrong password and an unknown account', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->from('/login')
        ->post('/login', ['email' => 'owner@studio.test', 'password' => 'nope'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    RateLimiter::clear('staff-login|127.0.0.1');

    $this->from('/login')
        ->post('/login', ['email' => 'ghost@studio.test', 'password' => 'nope'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
});

it('throttles repeated failures from one address', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    foreach (range(1, 5) as $attempt) {
        $this->from('/login')->post('/login', [
            'email' => 'owner@studio.test',
            'password' => 'nope',
        ])->assertSessionHasErrors('email');
    }

    // The correct password is now refused too: the limit is on the address.
    $this->from('/login')->post('/login', [
        'email' => 'owner@studio.test',
        'password' => 'correct-horse',
    ])->assertSessionHasErrors(['email' => 'Too many attempts. Try again later.']);

    $this->assertGuest('staff');
    expect(AuditLog::where('event', 'staff.login.throttled')->count())->toBe(1);
});

it('audits successful and failed logins without storing the password', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->post('/login', ['email' => 'owner@studio.test', 'password' => 'nope']);
    $this->post('/login', ['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $events = AuditLog::orderBy('id')->pluck('event')->all();

    expect($events)->toBe(['staff.login.failed', 'staff.login.succeeded'])
        ->and(AuditLog::all()->pluck('context')->flatten()->all())->not->toContain('correct-horse');
});

it('signs out and clears the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'staff')
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest('staff');
    expect(AuditLog::where('event', 'staff.logout')->count())->toBe(1);
});

it('redirects an unauthenticated visitor from the dashboard to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});
