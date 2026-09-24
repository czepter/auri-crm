<?php

use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prints the quote number and Gueltig-bis date under the classic layout', function () {
    [$user, $project] = studioWithProject();

    $quote = Quote::factory()->for($project, 'case')->create([
        'number' => '2026-014',
        'valid_until' => '2026-09-26',
        'vat_mode' => 'standard',
        'vat_rate' => 1900,
    ]);

    app(StudioSettingsResolver::class)->set('document_layout', 'classic');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('2026-014')
        ->and($html)->toContain('26.09.2026')
        ->and($html)->toContain('doc-table')
        ->and($html)->toContain('doc-layout-classic');
});

it('prints the payment stages split against the gross total, in the same shares PaymentStageSplitter gives the booked plan', function () {
    [$user, $project] = studioWithProject();

    $quote = Quote::factory()->for($project, 'case')->create([
        'number' => '2026-014',
        'net' => 50000,
        'vat_mode' => 'standard',
        'vat_rate' => 1900,
        'vat_amount' => 9500,
    ]);
    $quote->paymentStages()->create(['percent' => 30, 'label' => 'Anzahlung', 'sort_order' => 0]);
    $quote->paymentStages()->create(['percent' => 70, 'label' => 'Restzahlung', 'sort_order' => 1]);

    app(StudioSettingsResolver::class)->set('document_layout', 'classic');

    $html = app(RenderQuotePdf::class)->html($quote->fresh());

    expect($html)->toContain('Zahlungsplan')
        ->and($html)->toContain('Anzahlung')
        ->and($html)->toContain('Restzahlung');
});

it('leaves the payment stages block out entirely when a quote carries none', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create(['number' => '2026-014']);
    app(StudioSettingsResolver::class)->set('document_layout', 'classic');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->not->toContain('Zahlungsplan');
});

it('renders the minimal layout without a doc-table', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create(['number' => '2026-014', 'valid_until' => '2026-09-26']);
    app(StudioSettingsResolver::class)->set('document_layout', 'minimal');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-minimal')
        ->and($html)->toContain('2026-014')
        ->and($html)->not->toContain('class="doc-table"');
});

it('renders the letter layout with a closing paragraph', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create(['number' => '2026-014', 'valid_until' => '2026-09-26']);
    app(StudioSettingsResolver::class)->set('document_layout', 'letter');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-letter')
        ->and($html)->toContain('gültig bis');
});

it('renders the header-band layout with the band wrapper', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create(['number' => '2026-014']);
    app(StudioSettingsResolver::class)->set('document_layout', 'header_band');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-header_band')
        ->and($html)->toContain('dhb-band');
});

it('renders the structured layout with card wrappers', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create(['number' => '2026-014']);
    app(StudioSettingsResolver::class)->set('document_layout', 'structured');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-structured')
        ->and($html)->toContain('dst-card');
});

it('folds a hidden discount into the line prices and prints no Rabatt line', function () {
    [$user, $project] = studioWithProject();

    $quote = Quote::factory()->for($project, 'case')->create([
        'discount_type' => 'amount',
        'discount_value' => 8000,
        'discount_hidden' => true,
        'vat_mode' => 'standard',
        'vat_rate' => 1900,
    ]);
    $quote->items()->create(['title' => 'Shooting', 'quantity' => 1, 'unit_price' => 25000, 'sort_order' => 0]);
    $quote->items()->create(['title' => 'Album', 'quantity' => 2, 'unit_price' => 10000, 'sort_order' => 1]);
    $quote->recalculateTotal();
    $quote = $quote->fresh('items');

    app(StudioSettingsResolver::class)->set('document_layout', 'classic');

    $html = app(RenderQuotePdf::class)->html($quote);
    $client = $quote->asSeenByClient();

    expect($html)->not->toContain('Rabatt')
        ->and($client->items->sum(fn ($item) => $item->discountedLineTotal()))->toBe(37000)
        ->and($client->items->map->discountedLineTotal()->all())->toBe([20556, 16444])
        ->and($quote->items->first()->unit_price)->toBe(25000)
        ->and($quote->discount_amount)->toBe(8000);
});
