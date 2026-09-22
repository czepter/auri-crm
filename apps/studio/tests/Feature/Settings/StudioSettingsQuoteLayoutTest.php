<?php

use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults document_layout to classic', function () {
    [$user, $project] = studioWithProject();

    expect(app(StudioSettingsResolver::class)->documentLayout())->toBe('classic');
});

it('reads back a saved document_layout', function () {
    [$user, $project] = studioWithProject();

    app(StudioSettingsResolver::class)->set('document_layout', 'header_band');

    expect(app(StudioSettingsResolver::class)->documentLayout())->toBe('header_band');
});

it('saves document_layout through the Belege page', function () {
    [$user, $project] = studioWithProject();

    $this->actingAs($user, 'staff')->patch('/einstellungen/belege', [
        'document_layout' => 'minimal',
    ])->assertRedirect();

    expect(app(StudioSettingsResolver::class)->documentLayout())->toBe('minimal');
});

it('rejects an unknown document_layout', function () {
    [$user, $project] = studioWithProject();

    $this->actingAs($user, 'staff')->patch('/einstellungen/belege', [
        'document_layout' => 'not-a-layout',
    ])->assertSessionHasErrors('document_layout');
});
