<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function legalTemplate(string $name = 'Widerrufsbelehrung'): DocumentTemplate
{
    return DocumentTemplate::createFirstVersion([
        'type' => DocumentTemplateType::Legal->value,
        'name' => $name,
        'body' => '<p>Sie haben das Recht, binnen vierzehn Tagen zu widerrufen.</p>',
        'blocks' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Sie haben das Recht, binnen vierzehn Tagen zu widerrufen.']]]],
    ]);
}

function pointAt(string $text, ?string $family = null, ?string $url = null): StudioSettingsResolver
{
    $settings = app(StudioSettingsResolver::class);
    $settings->set("portal_legal_{$text}_family", $family, null);
    $settings->set("portal_legal_{$text}_url", $url, null);
    Cache::store('redis')->flush();

    return app(StudioSettingsResolver::class);
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
    $settings = pointAt('widerruf', $template->family_id, 'https://example.test/widerruf');

    expect($settings->legalUrl('widerruf'))->toBe('https://example.test/widerruf');
});

it('keeps the generated Impressum page when nothing else is set', function () {
    expect(app(StudioSettingsResolver::class)->legalUrl('impressum'))->toBe('/impressum');
});

it('has no page for a text nobody wrote', function () {
    $this->get('/rechtstexte/agb')->assertNotFound();
});
