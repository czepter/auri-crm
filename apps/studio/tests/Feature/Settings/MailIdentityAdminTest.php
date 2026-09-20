<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() and props() live in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('hands the resolved mail identity to the settings screen', function () {
    config(['mail.from.address' => 'hello@example.com', 'mail.from.name' => 'Example']);

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen/allgemein')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['settings']['mailFromAddress'])->toBe('hello@example.com')
        ->and($props['settings']['mailFromName'])->toBe('Example')
        ->and($props['settings']['mailReplyTo'])->toBeNull()
        ->and($props['settings']['mailBcc'])->toBeNull()
        ->and($props['settings']['mailSignature'])->toBeNull()
        ->and($props['mailSenderFromEnv'])->toBe(['name' => true, 'address' => true]);
});

it('updates the mail identity, redirects to the shell, and audits the owner', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen/email')
        ->patch('/einstellungen/email', [
            'mail_reply_to' => 'buero@nordlicht.test',
            'mail_bcc' => 'ablage@nordlicht.test',
            'mail_signature' => 'Herzliche Grüße, das Team von Atelier Nordlicht',
            'mail_clients_enabled' => false,
        ])
        ->assertRedirect('/einstellungen/email');

    $row = StudioSettings::query()->sole();

    expect($row->mail_reply_to)->toBe('buero@nordlicht.test')
        ->and($row->mail_clients_enabled)->toBeFalse()
        ->and($row->mail_bcc)->toBe('ablage@nordlicht.test')
        ->and($row->mail_signature)->toBe('Herzliche Grüße, das Team von Atelier Nordlicht')
        ->and(AuditLog::where('event', 'studio_settings.updated')->where('context->key', 'mail_reply_to')->sole()->actor_id)->toBe($owner->id);
});

it('leaves the sender to the environment: no field writes it, and a reset empties an inherited one', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->patch('/einstellungen/email', [
        'mail_from_name' => 'Atelier Nordlicht',
        'mail_from_address' => 'studio@nordlicht.test',
    ]);
    expect(StudioSettings::query()->sole()->mail_from_address)->toBeNull();

    (new StudioSettingsResolver)->set('mail_from_name', 'Atelier Nordlicht', $owner);
    (new StudioSettingsResolver)->set('mail_from_address', 'studio@nordlicht.test', $owner);

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen/email')
        ->patch('/einstellungen/email', ['mail_sender_reset' => true])
        ->assertRedirect('/einstellungen/email');

    $row = StudioSettings::query()->sole();

    expect($row->mail_from_name)->toBeNull()
        ->and($row->mail_from_address)->toBeNull();
});

it('rejects a reply-to or bcc address that is not a valid email', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/email', [
            'mail_reply_to' => 'not-an-email',
            'mail_bcc' => 'also-not-an-email',
        ])
        ->assertInvalid(['mail_reply_to', 'mail_bcc']);
});

it('clears an optional field back to null when submitted blank', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->patch('/einstellungen/email', [
        'mail_reply_to' => 'buero@nordlicht.test',
    ]);
    expect(StudioSettings::query()->sole()->mail_reply_to)->toBe('buero@nordlicht.test');

    $this->actingAs($owner, 'staff')->patch('/einstellungen/email', [
        'mail_reply_to' => null,
    ]);
    expect(StudioSettings::query()->sole()->mail_reply_to)->toBeNull();
});

it('opens the mail identity route to the owner, and gives every other role the same response as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs(staffMember($role), 'staff')->patch('/einstellungen/email', [
        'mail_reply_to' => 'buero@nordlicht.test',
    ]);

    $response->assertStatus($status);

    if ($status === 404) {
        expect($response->status())->toBe($missing->status());
    }
})->with([
    [StaffRole::Owner, 302],
    [StaffRole::Photographer, 404],
    [StaffRole::Editor, 404],
    [StaffRole::SecondShooter, 404],
    [StaffRole::Assistant, 404],
]);

it('declares an audience on the mail identity route, same as every other admin route', function () {
    $routes = routesForSurface('admin');

    $route = collect($routes)->firstWhere(fn ($route) => $route->uri() === 'einstellungen/email');

    expect($route)->not->toBeNull()
        ->and(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))->toBeTrue();
});
