<?php

use App\Enums\StaffRole;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('falls back to config(mail.from.*) until a studio sets its own', function () {
    config(['mail.from.address' => 'hello@example.com', 'mail.from.name' => 'Example']);

    $resolver = new StudioSettingsResolver;

    expect($resolver->mailFromAddress())->toBe('hello@example.com')
        ->and($resolver->mailFromName())->toBe('Example')
        ->and($resolver->mailReplyTo())->toBeNull()
        ->and($resolver->mailBcc())->toBeNull()
        ->and($resolver->mailSignature())->toBeNull();
});

it('resolves the tenant value the moment it is set, with no deploy', function () {
    $owner = staffMember(StaffRole::Owner);

    (new StudioSettingsResolver)->set('mail_from_address', 'studio@nordlicht.test', $owner);
    (new StudioSettingsResolver)->set('mail_from_name', 'Atelier Nordlicht', $owner);
    (new StudioSettingsResolver)->set('mail_reply_to', 'buero@nordlicht.test', $owner);
    (new StudioSettingsResolver)->set('mail_bcc', 'ablage@nordlicht.test', $owner);
    (new StudioSettingsResolver)->set('mail_signature', 'Herzliche Grüße, das Team von Atelier Nordlicht', $owner);

    // A completely fresh instance — the same "no cache-clear command" shape
    // StudioSettingsResolverTest already proves for display_name.
    $fresh = new StudioSettingsResolver;

    expect($fresh->mailFromAddress())->toBe('studio@nordlicht.test')
        ->and($fresh->mailFromName())->toBe('Atelier Nordlicht')
        ->and($fresh->mailReplyTo())->toBe('buero@nordlicht.test')
        ->and($fresh->mailBcc())->toBe('ablage@nordlicht.test')
        ->and($fresh->mailSignature())->toBe('Herzliche Grüße, das Team von Atelier Nordlicht');
});
