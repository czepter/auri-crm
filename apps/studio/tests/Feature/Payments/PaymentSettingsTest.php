<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// staffMember() and props() live in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('defaults Stripe to off', function () {
    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen/zahlungen')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['settings']['stripeEnabled'])->toBeFalse()
        ->and($props['settings']['stripeMode'])->toBe('test')
        ->and($props['settings']['hasStripeTestKeys'])->toBeFalse()
        ->and($props['settings']['hasStripeLiveKeys'])->toBeFalse();
});

// Die Mahnstufe steht bei Belege & Fristen: sie hängt an der Frist, nicht an
// der Anbindung, über die das Geld hereinkommt.
it('saves the reminders default on the documents route', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/dokumente', [
            'quote_validity_days' => 14,
            'payment_term_days' => 14,
            'deposit_percentage' => 3000,
            'quote_layout' => 'classic',
            'payments_reminders_enabled' => false,
        ])
        ->assertRedirect();

    expect(StudioSettings::query()->sole()->payments_reminders_enabled)->toBeFalse();
});

it('switches Stripe on and stores a test key pair, encrypted at rest', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/zahlungen', [
            'payments_stripe_enabled' => true,
            'payments_stripe_mode' => 'test',
            'payments_stripe_test_publishable_key' => 'pk_test_abc123',
            'payments_stripe_test_secret_key' => 'sk_test_verySecretValue',
        ])
        ->assertRedirect('/einstellungen/zahlungen');

    $row = StudioSettings::query()->sole();
    expect($row->payments_stripe_enabled)->toBeTrue()
        ->and($row->payments_stripe_test_publishable_key)->toBe('pk_test_abc123')
        ->and($row->payments_stripe_test_secret_key)->toBe('sk_test_verySecretValue');

    // Encrypted at rest — the raw database column never holds the plaintext
    // the model's `encrypted` cast just decrypted above.
    $raw = DB::table('studio_settings')->where('id', $row->id)->value('payments_stripe_test_secret_key');
    expect($raw)->not->toBe('sk_test_verySecretValue')
        ->and($raw)->not->toContain('sk_test_verySecretValue');
});

it('leaves an existing key pair untouched when the write-only fields are submitted blank', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->patch('/einstellungen/zahlungen', [
        'payments_stripe_enabled' => true,
        'payments_stripe_mode' => 'test',
        'payments_stripe_test_publishable_key' => 'pk_test_abc123',
        'payments_stripe_test_secret_key' => 'sk_test_original',
    ]);

    // A second save — reminders changes, the key fields arrive blank exactly
    // as a write-only form would send them when nobody touched that field.
    $this->actingAs($owner, 'staff')->patch('/einstellungen/zahlungen', [
        'payments_stripe_enabled' => true,
        'payments_stripe_mode' => 'test',
        'payments_stripe_test_publishable_key' => '',
        'payments_stripe_test_secret_key' => '',
    ]);

    $row = StudioSettings::query()->sole();
    expect($row->payments_stripe_test_secret_key)->toBe('sk_test_original');
});

it('keeps both mode key pairs independently — switching modes never requires re-entering keys', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->patch('/einstellungen/zahlungen', [
        'payments_stripe_enabled' => true,
        'payments_stripe_mode' => 'test',
        'payments_stripe_test_publishable_key' => 'pk_test_abc123',
        'payments_stripe_test_secret_key' => 'sk_test_abc123',
    ]);

    $this->actingAs($owner, 'staff')->patch('/einstellungen/zahlungen', [
        'payments_stripe_enabled' => true,
        'payments_stripe_mode' => 'live',
        'payments_stripe_live_publishable_key' => 'pk_live_xyz789',
        'payments_stripe_live_secret_key' => 'sk_live_xyz789',
    ]);

    $row = StudioSettings::query()->sole();
    expect($row->payments_stripe_mode)->toBe('live')
        // The test pair is still there, untouched by the mode switch.
        ->and($row->payments_stripe_test_secret_key)->toBe('sk_test_abc123')
        ->and($row->payments_stripe_live_secret_key)->toBe('sk_live_xyz789');
});

it('never puts the Stripe secret key in any response body — the edit screen or the update redirect', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->patch('/einstellungen/zahlungen', [
        'payments_stripe_enabled' => true,
        'payments_stripe_mode' => 'test',
        'payments_stripe_test_publishable_key' => 'pk_test_abc123',
        'payments_stripe_test_secret_key' => 'sk_test_topSecretValue',
    ]);

    $secret = 'sk_test_topSecretValue';

    $updateResponse = $this->actingAs($owner, 'staff')->patch('/einstellungen/zahlungen', [
        'payments_stripe_enabled' => true,
        'payments_stripe_mode' => 'test',
    ]);
    expect($updateResponse->getContent())->not->toContain($secret);

    $showResponse = $this->actingAs($owner, 'staff')->get('/einstellungen/zahlungen')->assertOk();
    expect($showResponse->getContent())->not->toContain($secret);

    // Belt and suspenders: the whole decoded props tree, not just a
    // specific key, in case a future field accidentally carries it.
    $props = $showResponse->viewData('page')['props'];
    expect(json_encode($props))->not->toContain($secret);
});

it('leaves an audit row naming the changed keys by name only, never by value', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->patch('/einstellungen/zahlungen', [
        'payments_stripe_enabled' => true,
        'payments_stripe_mode' => 'test',
        'payments_stripe_test_publishable_key' => 'pk_test_abc123',
        'payments_stripe_test_secret_key' => 'sk_test_topSecretValue',
    ]);

    $row = AuditLog::where('event', 'studio_settings.updated')->latest('id')->first();
    expect($row->context['keys'])->toContain('payments_stripe_test_secret_key')
        ->and(json_encode($row->context))->not->toContain('sk_test_topSecretValue');
});

it('opens the payments settings screen to the owner, and gives every other role the same response as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');
    $response = $this->actingAs(staffMember($role), 'staff')->get('/einstellungen/zahlungen');

    $response->assertStatus($status);

    if ($status === 404) {
        expect($response->status())->toBe($missing->status());
    }
})->with([
    [StaffRole::Owner, 200],
    [StaffRole::Photographer, 404],
    [StaffRole::Editor, 404],
    [StaffRole::SecondShooter, 404],
    [StaffRole::Assistant, 404],
]);
