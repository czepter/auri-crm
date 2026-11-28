<?php

use App\Models\StudioSettings;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults document_layout to classic', function () {
    [$user, $project] = studioWithProject();

    expect(StudioSettings::current()->document_layout)->toBe('classic');
});

it('reads back a saved document_layout', function () {
    [$user, $project] = studioWithProject();

    app(UpdateStudioSetting::class)('document_layout', 'header_band');

    expect(StudioSettings::current()->document_layout)->toBe('header_band');
});

it('saves document_layout through the Belege page', function () {
    [$user, $project] = studioWithProject();

    $this->actingAs($user, 'staff')->patch('/einstellungen/layout', [
        'document_layout' => 'minimal',
    ])->assertRedirect();

    expect(StudioSettings::current()->document_layout)->toBe('minimal');
});

it('rejects an unknown document_layout', function () {
    [$user, $project] = studioWithProject();

    $this->actingAs($user, 'staff')->patch('/einstellungen/layout', [
        'document_layout' => 'not-a-layout',
    ])->assertSessionHasErrors('document_layout');
});
