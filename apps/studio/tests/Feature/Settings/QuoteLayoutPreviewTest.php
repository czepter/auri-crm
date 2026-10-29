<?php

use App\Quotes\Models\Quote;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews a chosen layout without persisting anything', function () {
    [$user, $project] = studioWithProject();

    $response = $this->actingAs($user, 'staff')->get('/einstellungen/belege/angebot-vorschau?layout=header_band');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('doc-layout-header_band')
        ->toContain('Muster GmbH');

    expect(Quote::query()->count())->toBe(0);
});

it('falls back to the saved layout when none is requested', function () {
    [$user, $project] = studioWithProject();
    app(UpdateStudioSetting::class)('document_layout', 'minimal');

    $response = $this->actingAs($user, 'staff')->get('/einstellungen/belege/angebot-vorschau');

    $response->assertOk();
    expect($response->getContent())->toContain('doc-layout-minimal');
});
