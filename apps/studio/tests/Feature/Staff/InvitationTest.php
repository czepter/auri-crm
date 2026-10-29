<?php

use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Settings\Support\StudioSettingsResolver;
use App\Staff\Notifications\StaffInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function invite(array $overrides = []): StaffInvitation
{
    return StaffInvitation::create([
        'email' => 'nina@nordlicht.test',
        'name' => 'Nina Sturm',
        'role' => StaffRole::Contributor,
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
    [StaffRole::Admin, 200],
    [StaffRole::Manager, 403],
    [StaffRole::Contributor, 403],
    [StaffRole::Contributor, 403],
]);

it('sends an invitation carrying a link', function () {
    Notification::fake();

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff')
        ->post('/team/einladungen', [
            'email' => 'nina@nordlicht.test',
            'name' => 'Nina Sturm',
            'role' => StaffRole::Contributor->value,
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

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff')
        ->post('/team/einladungen', [
            'email' => 'nina@nordlicht.test',
            'name' => 'Nina Sturm',
            'role' => StaffRole::Contributor->value,
        ]);

    $context = AuditLog::where('event', 'staff.invited')->sole()->context;

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

    expect($user->role)->toBe(StaffRole::Contributor)
        ->and($user->hasConfirmedMfa())->toBeFalse();

    $this->get('/')->assertOk();
});

it('refuses to invite an address that already has an account', function () {
    Notification::fake();
    User::factory()->create(['email' => 'nina@nordlicht.test']);

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff')
        ->post('/team/einladungen', [
            'email' => 'nina@nordlicht.test',
            'name' => 'Nina Sturm',
            'role' => StaffRole::Contributor->value,
        ])->assertSessionHasErrors('email');
});

it('replaces a live invitation rather than leaving two open', function () {
    Notification::fake();
    invite();

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff')
        ->post('/team/einladungen', [
            'email' => 'nina@nordlicht.test',
            'name' => 'Nina Sturm',
            'role' => StaffRole::Contributor->value,
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

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff')
        ->delete("/team/einladungen/{$invitation->public_id}")
        ->assertRedirect();

    auth('staff')->logout();

    $this->post('/einladung/raw-token', [
        'password' => 'ein-sehr-langes-passwort',
        'password_confirmation' => 'ein-sehr-langes-passwort',
    ])->assertRedirect('/login');

    expect(User::where('email', 'nina@nordlicht.test')->exists())->toBeFalse();
});

it('renders the calendar and reply blocks, and drops the 2FA line', function () {
    $owner = User::factory()->create(['role' => StaffRole::Admin]);
    $invitation = invite(['invited_by_id' => $owner->id]);

    $html = (string) (new StaffInvited($invitation, 'https://example.test/einladung/abc', app(StudioSettingsResolver::class)))
        ->toMail($owner)
        ->render();

    expect($html)
        ->toContain('Kalender abonnieren')
        ->toContain('Antworten am Auftrag')
        ->not->toContain('Zwei-Faktor');
});

it('shows each role with the capabilities the enum grants it', function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff')
        ->get('/team')
        ->assertInertia(fn ($page) => $page
            ->where('roles.1.value', StaffRole::Manager->value)
            ->where('roles.1.capabilities', array_map(fn ($capability) => $capability->value, StaffRole::Manager->capabilities()))
            ->has('capabilities', count(Capability::cases())));
});
