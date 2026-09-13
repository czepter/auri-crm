<?php

use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults quote_layout to classic', function () {
    [$user, $project] = studioWithProject();

    expect(app(StudioSettingsResolver::class)->quoteLayout())->toBe('classic');
});

it('reads back a saved quote_layout', function () {
    [$user, $project] = studioWithProject();

    app(StudioSettingsResolver::class)->set('quote_layout', 'header_band');

    expect(app(StudioSettingsResolver::class)->quoteLayout())->toBe('header_band');
});

it('saves quote_layout through the Dokumente pane', function () {
    [$user, $project] = studioWithProject();

    $this->actingAs($user, 'staff')->patch('/einstellungen/dokumente', [
        'quote_validity_days' => 14,
        'payment_term_days' => 14,
        'deposit_percentage' => 3000,
        'widerruf_included' => false,
        'quote_layout' => 'minimal',
    ])->assertRedirect();

    expect(app(StudioSettingsResolver::class)->quoteLayout())->toBe('minimal');
});

it('rejects an unknown quote_layout', function () {
    [$user, $project] = studioWithProject();

    $this->actingAs($user, 'staff')->patch('/einstellungen/dokumente', [
        'quote_validity_days' => 14,
        'payment_term_days' => 14,
        'deposit_percentage' => 3000,
        'widerruf_included' => false,
        'quote_layout' => 'not-a-layout',
    ])->assertSessionHasErrors('quote_layout');
});
