<?php

use App\Enums\StaffRole;
use App\Models\StudioSettings;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('seeds sensible document defaults on the migration itself', function () {

    expect(StudioSettings::current()->quote_validity_days)->toBe(14)
        ->and(StudioSettings::current()->payment_term_days)->toBe(14)
        ->and(StudioSettings::current()->deposit_percentage)->toBe(3000)
        ->and(StudioSettings::current()->document_letterhead_html)->toBeNull()
        ->and(StudioSettings::current()->document_footer_html)->toBeNull();
});

it('writes each document default through set(), busts the cache, and audits the actor', function () {
    $owner = staffMember(StaffRole::Admin);

    app(UpdateStudioSetting::class)('quote_validity_days', 30, $owner);
    app(UpdateStudioSetting::class)('payment_term_days', 21, $owner);
    app(UpdateStudioSetting::class)('deposit_percentage', 5000, $owner);
    app(UpdateStudioSetting::class)('document_letterhead_html', '<header>Nordlicht</header>', $owner);
    app(UpdateStudioSetting::class)('document_footer_html', '<footer>Seite 1</footer>', $owner);

    expect(StudioSettings::current()->quote_validity_days)->toBe(30)
        ->and(StudioSettings::current()->payment_term_days)->toBe(21)
        ->and(StudioSettings::current()->deposit_percentage)->toBe(5000)
        ->and(StudioSettings::current()->document_letterhead_html)->toBe('<header>Nordlicht</header>')
        ->and(StudioSettings::current()->document_footer_html)->toBe('<footer>Seite 1</footer>');
});
