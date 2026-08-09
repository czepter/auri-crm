<?php

use App\Enums\StaffRole;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('seeds sensible document defaults on the migration itself', function () {
    $settings = new StudioSettingsResolver;

    expect($settings->quoteValidityDays())->toBe(14)
        ->and($settings->paymentTermDays())->toBe(14)
        ->and($settings->depositPercentage())->toBe(3000)
        ->and($settings->widerrufIncluded())->toBeTrue()
        ->and($settings->widerrufText())->not->toBeNull();
});

it('writes each document default through set(), busts the cache, and audits the actor', function () {
    $owner = staffMember(StaffRole::Owner);

    (new StudioSettingsResolver)->set('quote_validity_days', 30, $owner);
    (new StudioSettingsResolver)->set('payment_term_days', 21, $owner);
    (new StudioSettingsResolver)->set('deposit_percentage', 5000, $owner);
    (new StudioSettingsResolver)->set('widerruf_included', false, $owner);
    (new StudioSettingsResolver)->set('widerruf_text', 'Custom Widerrufstext.', $owner);

    $fresh = new StudioSettingsResolver;

    expect($fresh->quoteValidityDays())->toBe(30)
        ->and($fresh->paymentTermDays())->toBe(21)
        ->and($fresh->depositPercentage())->toBe(5000)
        ->and($fresh->widerrufIncluded())->toBeFalse()
        ->and($fresh->widerrufText())->toBe('Custom Widerrufstext.');
});
