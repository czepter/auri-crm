<?php

use App\Enums\StaffRole;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function invite(array $overrides = []): StaffInvitation
{
    return StaffInvitation::create([
        'email' => 'nina@nordlicht.test',
        'name' => 'Nina Sturm',
        'role' => StaffRole::SecondShooter,
        'token_hash' => StaffInvitation::hash('raw-token'),
        'expires_at' => now()->addDays(7),
        'invited_by_id' => User::factory()->create()->id,
        ...$overrides,
    ]);
}

it('lets only a role with staff management invite', function (StaffRole $role, int $status) {
    $this->actingAs(User::factory()->withMfa()->create(['role' => $role]), 'staff')
        ->get('/team')
        ->assertStatus($status);
})->with([
    [StaffRole::Owner, 200],
    [StaffRole::Photographer, 403],
    [StaffRole::Editor, 403],
    [StaffRole::SecondShooter, 403],
]);

it('sends an invitation carrying a link', function () {
    Notification::fake();

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Owner]), 'staff')
        ->post('/team/einladungen', [
            'email' => 'nina@nordlicht.test',
            'name' => 'Nina Sturm',
            'role' => StaffRole::SecondShooter->value,
        ])->assertRedirect();

    expect(StaffInvitation::sole()->email)->toBe('nina@nordlicht.test');

    Notification::assertSentOnDemand(StaffInvited::class);
});

it('stores the token as a hash and never in the clear', function () {
    // A leaked database must not hand over live invitations.
    $invitation = invite();

    expect($invitation->token_hash)->not->toBe('raw-token')
        ->and($invitation->token_hash)->toBe(hash('sha256', 'raw-token'))
        ->and($invitation->fresh()->toArray())->not->toHaveKey('token_hash');
});

it('keeps the token out of the audit row', function () {
    Notification::fake();

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Owner]), 'staff')
        ->post('/team/einladungen', [
            'email' => 'nina@nordlicht.test',
            'name' => 'Nina Sturm',
            'role' => StaffRole::SecondShooter->value,
        ]);

    $context = App\Models\AuditLog::where('event', 'staff.invited')->sole()->context;

    expect(json_encode($context))->not->toContain('token');
});

it('opens the acceptance screen for a live token', function () {
    invite();

    $this->get('/einladung/raw-token')->assertOk();
});

it('refuses an expired, unknown or spent token', function (callable $setup, string $token) {
    $setup();

    $this->get("/einladung/{$token}")->assertRedirect('/login');
})->with([
    'expired' => [fn () => invite(['expires_at' => now()->subDay()]), 'raw-token'],
    'unknown' => [fn () => invite(), 'not-a-token'],
    'accepted' => [fn () => invite()->forceFill(['accepted_at' => now()])->save(), 'raw-token'],
]);

it('creates the account with the invited role and signs them in directly — MFA is opt-in', function () {
    invite();

    $this->post('/einladung/raw-token', [
        'password' => 'ein-sehr-langes-passwort',
        'password_confirmation' => 'ein-sehr-langes-passwort',
    ])->assertRedirect('/');

    $user = User::where('email', 'nina@nordlicht.test')->sole();

    expect($user->role)->toBe(StaffRole::SecondShooter)
        ->and($user->hasConfirmedMfa())->toBeFalse();

    $this->get('/')->assertOk();
});

it('refuses to invite an address that already has an account', function () {
    Notification::fake();
    User::factory()->create(['email' => 'nina@nordlicht.test']);

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Owner]), 'staff')
        ->post('/team/einladungen', [
            'email' => 'nina@nordlicht.test',
            'name' => 'Nina Sturm',
            'role' => StaffRole::SecondShooter->value,
        ])->assertSessionHasErrors('email');
});

it('replaces a live invitation rather than leaving two open', function () {
    Notification::fake();
    invite();

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Owner]), 'staff')
        ->post('/team/einladungen', [
            'email' => 'nina@nordlicht.test',
            'name' => 'Nina Sturm',
            'role' => StaffRole::Assistant->value,
        ]);

    expect(StaffInvitation::count())->toBe(1);

    // Signed out, because an invitation link is followed by someone who is
    // not the owner who sent it — and the guest middleware would otherwise
    // bounce the request before the token is ever looked at.
    auth('staff')->logout();

    // The superseded link no longer opens.
    $this->get('/einladung/raw-token')->assertRedirect('/login');
});

it('stops a revoked invitation from being accepted', function () {
    $invitation = invite();

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Owner]), 'staff')
        ->delete("/team/einladungen/{$invitation->public_id}")
        ->assertRedirect();

    auth('staff')->logout();

    $this->post('/einladung/raw-token', [
        'password' => 'ein-sehr-langes-passwort',
        'password_confirmation' => 'ein-sehr-langes-passwort',
    ])->assertRedirect('/login');

    expect(User::where('email', 'nina@nordlicht.test')->exists())->toBeFalse();
});
