<?php

use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;

it('prints the quote number and Gueltig-bis date under the classic layout', function () {
    [$user, $project] = studioWithProject();

    $quote = Quote::factory()->for($project)->create([
        'number' => '2026-014',
        'valid_until' => '2026-09-26',
        'vat_mode' => 'standard',
        'vat_rate' => 1900,
    ]);

    app(StudioSettingsResolver::class)->set('quote_layout', 'classic');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('2026-014')
        ->and($html)->toContain('26.09.2026')
        ->and($html)->toContain('doc-table')
        ->and($html)->toContain('doc-layout-classic');
});

it('renders the minimal layout without a doc-table', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project)->create(['number' => '2026-014', 'valid_until' => '2026-09-26']);
    app(StudioSettingsResolver::class)->set('quote_layout', 'minimal');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-minimal')
        ->and($html)->toContain('2026-014')
        ->and($html)->not->toContain('class="doc-table"');
});

it('renders the letter layout with a closing paragraph', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project)->create(['number' => '2026-014', 'valid_until' => '2026-09-26']);
    app(StudioSettingsResolver::class)->set('quote_layout', 'letter');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-letter')
        ->and($html)->toContain('gültig bis');
});

it('renders the header-band layout with the band wrapper', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project)->create(['number' => '2026-014']);
    app(StudioSettingsResolver::class)->set('quote_layout', 'header_band');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-header_band')
        ->and($html)->toContain('dhb-band');
});

it('renders the structured layout with card wrappers', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project)->create(['number' => '2026-014']);
    app(StudioSettingsResolver::class)->set('quote_layout', 'structured');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-structured')
        ->and($html)->toContain('dst-card');
});
