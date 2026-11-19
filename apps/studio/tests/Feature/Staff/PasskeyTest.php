<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Staff\Models\Passkey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\SoftwareAuthenticator;

uses(RefreshDatabase::class);

beforeEach(fn () => RateLimiter::clear('staff-login|127.0.0.1'));

function adminOrigin(): string
{
    return config('services.admin.url');
}

function enrolPasskey(User $user, ?SoftwareAuthenticator $device = null, int $counter = 0): SoftwareAuthenticator
{
    $device ??= new SoftwareAuthenticator;

    $options = test()->actingAs($user, 'staff')
        ->postJson('/profil/passkeys/optionen', ['current_password' => 'password'])
        ->assertOk()
        ->json();

    test()->post('/profil/passkeys', [
        'name' => 'MacBook',
        'credential' => $device->attestation($options, adminOrigin(), counter: $counter),
    ])->assertSessionHasNoErrors();

    return $device;
}

function loginOptions(): array
{
    return test()->postJson('/login/passkey/optionen')->assertOk()->json();
}

it('registers a passkey after the password and lists it on the security pane', function () {
    $user = User::factory()->create(['password' => 'password']);

    $options = $this->actingAs($user, 'staff')
        ->postJson('/profil/passkeys/optionen', ['current_password' => 'password'])
        ->assertOk()
        ->assertJsonPath('rp.id', parse_url(adminOrigin(), PHP_URL_HOST))
        ->assertJsonPath('authenticatorSelection.residentKey', 'required')
        ->assertJsonPath('authenticatorSelection.userVerification', 'required')
        ->assertJsonPath('attestation', 'none')
        ->json();

    expect($options['user']['id'])->toBe(rtrim(strtr(base64_encode($user->public_id), '+/', '-_'), '='));

    $this->post('/profil/passkeys', [
        'name' => 'MacBook',
        'credential' => (new SoftwareAuthenticator)->attestation($options, adminOrigin()),
    ])->assertSessionHasNoErrors();

    $passkeys = $this->get('/profil/sicherheit')->viewData('page')['props']['passkeys'];

    expect($passkeys)->toHaveCount(1)
        ->and($passkeys[0]['name'])->toBe('MacBook')
        ->and($passkeys[0]['lastUsedAt'])->toBeNull();

    $this->get('/profil')->assertOk()->assertInertia(fn ($page) => $page->where('passkeys', []));

    expect(AuditLog::where('event', 'staff.passkey.registered')->count())->toBe(1);
});

it('asks for the password before offering a ceremony', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->actingAs($user, 'staff')
        ->postJson('/profil/passkeys/optionen', ['current_password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect(session()->has('passkey.creation'))->toBeFalse();
});

it('lists existing passkeys as excluded on the next registration', function () {
    $user = User::factory()->create(['password' => 'password']);
    $device = enrolPasskey($user);

    $options = $this->postJson('/profil/passkeys/optionen', ['current_password' => 'password'])->json();

    expect($options['excludeCredentials'])->toHaveCount(1)
        ->and($options['excludeCredentials'][0]['id'])->toBe(Passkey::first()->credential_id);
});

it('refuses a registration whose challenge is spent, expired or another user\'s', function () {
    $user = User::factory()->create(['password' => 'password']);

    $options = $this->actingAs($user, 'staff')
        ->postJson('/profil/passkeys/optionen', ['current_password' => 'password'])->json();
    $credential = (new SoftwareAuthenticator)->attestation($options, adminOrigin());

    $this->post('/profil/passkeys', ['name' => 'A', 'credential' => $credential])->assertSessionHasNoErrors();
    $this->post('/profil/passkeys', ['name' => 'B', 'credential' => $credential])
        ->assertSessionHasErrors(['credential' => __('auth.passkey_failed')]);

    $options = $this->postJson('/profil/passkeys/optionen', ['current_password' => 'password'])->json();
    $this->travel(301)->seconds();
    $this->post('/profil/passkeys', ['name' => 'C', 'credential' => (new SoftwareAuthenticator)->attestation($options, adminOrigin())])
        ->assertSessionHasErrors('credential');
    $this->travelBack();

    $options = $this->postJson('/profil/passkeys/optionen', ['current_password' => 'password'])->json();
    $this->actingAs(User::factory()->create(), 'staff')
        ->post('/profil/passkeys', ['name' => 'D', 'credential' => (new SoftwareAuthenticator)->attestation($options, adminOrigin())])
        ->assertSessionHasErrors('credential');

    expect(Passkey::count())->toBe(1);
});

it('refuses a registration from another origin or without user verification', function (string $origin, int $flags) {
    $user = User::factory()->create(['password' => 'password']);

    $options = $this->actingAs($user, 'staff')
        ->postJson('/profil/passkeys/optionen', ['current_password' => 'password'])->json();

    $this->post('/profil/passkeys', [
        'name' => 'X',
        'credential' => (new SoftwareAuthenticator)->attestation($options, $origin === '' ? adminOrigin() : $origin, $flags),
    ])->assertSessionHasErrors('credential');

    expect(Passkey::count())->toBe(0);
})->with([
    'foreign origin' => ['https://evil.example', 0x45],
    'no user verification' => ['', 0x41],
]);

it('signs a passkey owner in past the second factor, without a remember cookie', function () {
    $user = User::factory()->withMfa()->create(['password' => 'password']);
    $device = enrolPasskey($user, counter: 3);
    Auth::guard('staff')->logout();
    $sessionId = session()->getId();

    $options = loginOptions();

    $this->post('/login/passkey', ['credential' => $device->assertion($options, adminOrigin(), 4)])
        ->assertRedirect('/')
        ->assertCookieMissing(Auth::guard('staff')->getRecallerName());

    $this->assertAuthenticatedAs($user, 'staff');
    expect(session()->getId())->not->toBe($sessionId);

    $passkey = Passkey::first();
    expect($passkey->last_used_at)->not->toBeNull()
        ->and(json_decode($passkey->source, true)['counter'])->toBe(4)
        ->and(AuditLog::where('event', 'staff.login.succeeded')->first()->context['method'])->toBe('passkey');
});

it('accepts the zero counter that synced passkeys always report', function () {
    $user = User::factory()->create(['password' => 'password']);
    $device = enrolPasskey($user);
    Auth::guard('staff')->logout();

    $this->post('/login/passkey', ['credential' => $device->assertion(loginOptions(), adminOrigin(), 0)])
        ->assertRedirect('/');
});

it('refuses a replayed assertion', function () {
    $user = User::factory()->create(['password' => 'password']);
    $device = enrolPasskey($user);
    Auth::guard('staff')->logout();

    $credential = $device->assertion(loginOptions(), adminOrigin(), 1);

    $this->post('/login/passkey', ['credential' => $credential])->assertRedirect('/');
    Auth::guard('staff')->logout();

    $this->post('/login/passkey', ['credential' => $credential])
        ->assertSessionHasErrors(['credential' => __('auth.passkey_failed')]);
    $this->assertGuest('staff');
});

it('refuses an assertion with one uniform error', function (string $break) {
    $user = User::factory()->create(['password' => 'password']);
    $device = enrolPasskey($user, counter: 5);
    Auth::guard('staff')->logout();
    $options = loginOptions();

    $credential = match ($break) {
        'origin' => $device->assertion($options, 'https://evil.example', 6),
        'user verification' => $device->assertion($options, adminOrigin(), 6, flags: 0x01),
        'counter' => $device->assertion($options, adminOrigin(), 5),
        'unknown credential' => (new SoftwareAuthenticator)->assertion($options, adminOrigin(), 6, userHandle: $user->public_id),
        'user handle' => $device->assertion($options, adminOrigin(), 6, userHandle: User::factory()->create()->public_id),
        'expired' => $device->assertion($options, adminOrigin(), 6),
    };

    if ($break === 'expired') {
        $this->travel(301)->seconds();
    }

    $this->post('/login/passkey', ['credential' => $credential])
        ->assertSessionHasErrors(['credential' => __('auth.passkey_failed')]);

    $this->assertGuest('staff');
    expect(AuditLog::where('event', 'staff.login.failed')->count())->toBe(1);
})->with(['origin', 'user verification', 'counter', 'unknown credential', 'user handle', 'expired']);

it('throttles passkey and password login from the same address together', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'password']);

    foreach (range(1, 5) as $_) {
        $this->post('/login/passkey', ['credential' => '{}'])->assertSessionHasErrors(['credential' => __('auth.passkey_failed')]);
    }

    $this->post('/login/passkey', ['credential' => '{}'])->assertSessionHasErrors('credential');
    expect(session('errors')->first('credential'))->toContain('Zu viele Anmeldeversuche');

    $this->post('/login', ['email' => 'owner@studio.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('staff');
});

it('deletes a passkey only with the password and only the own one', function () {
    $user = User::factory()->create(['password' => 'password']);
    enrolPasskey($user);
    $mine = Passkey::first();

    $theirs = User::factory()->create(['password' => 'password']);
    $other = enrolPasskey($theirs);
    $foreign = Passkey::where('user_id', $theirs->id)->first();

    $this->actingAs($user, 'staff')
        ->delete("/profil/passkeys/{$foreign->public_id}", ['current_password' => 'password'])
        ->assertNotFound();

    $this->delete("/profil/passkeys/{$mine->public_id}", ['current_password' => 'wrong'])
        ->assertSessionHasErrors('current_password');
    expect(Passkey::count())->toBe(2);

    $this->delete("/profil/passkeys/{$mine->public_id}", ['current_password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(Passkey::pluck('id')->all())->toBe([$foreign->id])
        ->and(AuditLog::where('event', 'staff.passkey.deleted')->count())->toBe(1);
});
