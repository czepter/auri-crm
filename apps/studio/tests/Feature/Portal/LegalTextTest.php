<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Portal\Support\LegalTexts;
use App\Settings\Actions\UpdateStudioSetting;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

function legalTemplate(string $name = 'Widerrufsbelehrung'): DocumentTemplate
{
    return DocumentTemplate::createFirstVersion([
        'type' => DocumentTemplateType::Legal->value,
        'name' => $name,
        'body' => '<p>Sie haben das Recht, binnen vierzehn Tagen zu widerrufen.</p>',
        'blocks' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Sie haben das Recht, binnen vierzehn Tagen zu widerrufen.']]]],
    ]);
}

function pointAt(string $text, ?string $family = null, ?string $url = null): void
{
    app(UpdateStudioSetting::class)("portal_legal_{$text}_family", $family, null);
    app(UpdateStudioSetting::class)("portal_legal_{$text}_url", $url, null);
    Cache::store('redis')->flush();
}

it('shows a legal text the studio wrote itself', function () {
    $template = legalTemplate();
    pointAt('widerruf', $template->family_id);

    $this->get('/rechtstexte/widerruf')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // false: der Seitenfinder kennt nur js/admin/pages, siehe config/inertia.php
            ->component('Rechtstext', false)
            ->where('title', 'Widerrufsbelehrung'));
});

it('prefers an external address over a template', function () {
    $template = legalTemplate();
    pointAt('widerruf', $template->family_id, 'https://example.test/widerruf');

    expect(LegalTexts::url('widerruf'))->toBe('https://example.test/widerruf');
});

it('keeps the generated Impressum page when nothing else is set', function () {
    expect(LegalTexts::url('impressum'))->toBe('/impressum');
});

it('keeps all four legal links in the footer and lists only attached ones beside the quote', function () {
    $template = legalTemplate();
    pointAt('impressum', null, 'https://example.test/impressum');
    pointAt('datenschutz', null, 'https://example.test/datenschutz');
    pointAt('agb', null, 'https://example.test/agb');
    pointAt('widerruf', $template->family_id);

    $this->get('/rechtstexte/widerruf')
        ->assertInertia(fn ($page) => $page
            ->where('portalLegal.impressumUrl', 'https://example.test/impressum')
            ->where('portalLegal.datenschutzUrl', 'https://example.test/datenschutz')
            ->where('portalLegal.agbUrl', 'https://example.test/agb')
            ->where('portalLegal.widerrufUrl', '/rechtstexte/widerruf')
            ->where('portalQuoteLegal', []));

    app(UpdateStudioSetting::class)('portal_legal_widerruf_attach', true, null);
    Cache::store('redis')->flush();

    $this->get('/rechtstexte/widerruf')
        ->assertInertia(fn ($page) => $page
            ->where('portalLegal.agbUrl', 'https://example.test/agb')
            ->where('portalQuoteLegal', ['widerruf' => '/rechtstexte/widerruf']));
});

it('has no page for a text nobody wrote', function () {
    $this->get('/rechtstexte/agb')->assertNotFound();
});

it('resolves the studio fields a Widerrufsbelehrung names', function () {
    $template = DocumentTemplate::createFirstVersion([
        'type' => DocumentTemplateType::Legal->value,
        'name' => 'Widerrufsbelehrung',
        'body' => '<p>{{ studio.name }}, {{ studio.city }}</p>',
        'blocks' => ['blocks' => []],
    ]);

    app(UpdateStudioSetting::class)('legal_name', 'Atelier Nordlicht Fotografie GmbH', null);
    app(UpdateStudioSetting::class)('city', 'Hamburg', null);
    pointAt('widerruf', $template->family_id);

    $this->get('/rechtstexte/widerruf')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Rechtstext', false)
            ->where('body', '<p>Atelier Nordlicht Fotografie GmbH, Hamburg</p>'));
});

it('names where a Widerruf goes, after the text', function () {
    $template = legalTemplate();
    pointAt('widerruf', $template->family_id);
    app(UpdateStudioSetting::class)('street', 'Hafenstr. 12', null);
    app(UpdateStudioSetting::class)('portal_contact_email', 'hallo@nordlicht.test', null);
    Cache::store('redis')->flush();

    $this->get('/rechtstexte/widerruf')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('addressee', ['Hafenstr. 12', 'hallo@nordlicht.test']));
});
