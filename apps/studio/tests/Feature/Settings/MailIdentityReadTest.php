<?php

use App\Enums\StaffRole;
use App\Models\StudioSettings;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('falls back to config(mail.from.*) until a studio sets its own', function () {
    config(['mail.from.address' => 'hello@example.com', 'mail.from.name' => 'Example']);

    expect(StudioSettings::current()->mailFromAddress())->toBe('hello@example.com')
        ->and(StudioSettings::current()->mailFromName())->toBe('Example')
        ->and(StudioSettings::current()->mail_reply_to)->toBeNull()
        ->and(StudioSettings::current()->mail_bcc)->toBeNull()
        ->and(StudioSettings::current()->mail_signature)->toBeNull();
});

it('sends under the studio name while no sender name is set', function () {
    config(['mail.from.name' => 'Studio']);

    app(UpdateStudioSetting::class)('display_name', '50mm freunde', staffMember(StaffRole::Admin));

    expect(StudioSettings::current()->mailFromName())->toBe('50mm freunde');
});

it('resolves the tenant value the moment it is set, with no deploy', function () {
    $owner = staffMember(StaffRole::Admin);

    app(UpdateStudioSetting::class)('mail_from_address', 'studio@nordlicht.test', $owner);
    app(UpdateStudioSetting::class)('mail_from_name', 'Atelier Nordlicht', $owner);
    app(UpdateStudioSetting::class)('mail_reply_to', 'buero@nordlicht.test', $owner);
    app(UpdateStudioSetting::class)('mail_bcc', 'ablage@nordlicht.test', $owner);
    app(UpdateStudioSetting::class)('mail_signature', 'Herzliche Grüße, das Team von Atelier Nordlicht', $owner);

    // A completely fresh instance — the same "no cache-clear command" shape
    // StudioSettingsReadTest already proves for display_name.

    expect(StudioSettings::current()->mailFromAddress())->toBe('studio@nordlicht.test')
        ->and(StudioSettings::current()->mailFromName())->toBe('Atelier Nordlicht')
        ->and(StudioSettings::current()->mail_reply_to)->toBe('buero@nordlicht.test')
        ->and(StudioSettings::current()->mail_bcc)->toBe('ablage@nordlicht.test')
        ->and(StudioSettings::current()->mail_signature)->toBe('Herzliche Grüße, das Team von Atelier Nordlicht');
});
