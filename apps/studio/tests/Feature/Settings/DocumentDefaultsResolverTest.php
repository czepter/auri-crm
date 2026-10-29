<?php

use App\Enums\StaffRole;
use App\Settings\Actions\UpdateStudioSetting;
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
        ->and($settings->documentLetterheadHtml())->toBeNull()
        ->and($settings->documentFooterHtml())->toBeNull();
});

it('writes each document default through set(), busts the cache, and audits the actor', function () {
    $owner = staffMember(StaffRole::Admin);

    app(UpdateStudioSetting::class)('quote_validity_days', 30, $owner);
    app(UpdateStudioSetting::class)('payment_term_days', 21, $owner);
    app(UpdateStudioSetting::class)('deposit_percentage', 5000, $owner);
    app(UpdateStudioSetting::class)('document_letterhead_html', '<header>Nordlicht</header>', $owner);
    app(UpdateStudioSetting::class)('document_footer_html', '<footer>Seite 1</footer>', $owner);

    $fresh = new StudioSettingsResolver;

    expect($fresh->quoteValidityDays())->toBe(30)
        ->and($fresh->paymentTermDays())->toBe(21)
        ->and($fresh->depositPercentage())->toBe(5000)
        ->and($fresh->documentLetterheadHtml())->toBe('<header>Nordlicht</header>')
        ->and($fresh->documentFooterHtml())->toBe('<footer>Seite 1</footer>');
});
