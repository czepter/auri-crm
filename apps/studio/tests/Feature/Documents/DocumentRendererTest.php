<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentRenderer;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** T-12's own acceptance criterion: the letterhead and footer are shared, not copied into each template. */
it('wraps a contract body in the studio\'s configured letterhead and footer', function () {
    $settings = new StudioSettingsResolver;
    $settings->set('document_letterhead_html', '<header>Studio Nordlicht</header>');
    $settings->set('document_footer_html', '<footer>Seite 1</footer>');

    $html = (new DocumentRenderer)->wrap(DocumentTemplateType::Contract, '<p>Vertragstext</p>', new StudioSettingsResolver);

    expect($html)->toContain('<header>Studio Nordlicht</header>')
        ->and($html)->toContain('<p>Vertragstext</p>')
        ->and($html)->toContain('<footer>Seite 1</footer>')
        ->and(strpos($html, '<header>'))->toBeLessThan(strpos($html, '<p>Vertragstext</p>'))
        ->and(strpos($html, '<p>Vertragstext</p>'))->toBeLessThan(strpos($html, '<footer>'));
});

it('wraps a quote body in the same shared letterhead and footer as a contract', function () {
    $settings = new StudioSettingsResolver;
    $settings->set('document_letterhead_html', '<header>Studio Nordlicht</header>');
    $settings->set('document_footer_html', '<footer>Seite 1</footer>');

    $html = (new DocumentRenderer)->wrap(DocumentTemplateType::Quote, '<p>Angebotstext</p>', new StudioSettingsResolver);

    expect($html)->toContain('<header>Studio Nordlicht</header>')
        ->and($html)->toContain('<footer>Seite 1</footer>');
});

it('leaves a mail body entirely unwrapped — it is not a PDF', function () {
    $settings = new StudioSettingsResolver;
    $settings->set('document_letterhead_html', '<header>Studio Nordlicht</header>');
    $settings->set('document_footer_html', '<footer>Seite 1</footer>');

    $html = (new DocumentRenderer)->wrap(DocumentTemplateType::Mail, '<p>Hallo</p>', new StudioSettingsResolver);

    expect($html)->toBe('<p>Hallo</p>');
});

it('still produces a valid document shell when no letterhead or footer is configured', function () {
    $html = (new DocumentRenderer)->wrap(DocumentTemplateType::Contract, '<p>Vertragstext</p>', new StudioSettingsResolver);

    expect($html)->toContain('<html')->and($html)->toContain('<p>Vertragstext</p>');
});

it('sets the paper typography once, in the file that goes to Gotenberg and to the preview', function () {
    $html = (new DocumentRenderer)->wrap(DocumentTemplateType::Contract, '<p>Vertragstext</p>', new StudioSettingsResolver);

    expect($html)->toContain('font-size: 10.5pt')
        ->and($html)->toContain("font-family: 'Hanken Grotesk'")
        ->and($html)->toContain('lang="de"');
});

it('embeds the house face rather than linking it — Gotenberg cannot reach /fonts', function () {
    $html = (new DocumentRenderer)->wrap(DocumentTemplateType::Contract, '<p>Vertragstext</p>', new StudioSettingsResolver);

    expect($html)->toContain('src:url(data:font/woff2;base64,')
        ->and($html)->not->toContain('/fonts/hanken-grotesk-latin.woff2');
});

it('keeps a mail free of the paper stylesheet, where a page size means nothing', function () {
    $html = (new DocumentRenderer)->wrap(DocumentTemplateType::Mail, '<p>Hallo</p>', new StudioSettingsResolver);

    expect($html)->not->toContain('font-size: 10.5pt');
});
