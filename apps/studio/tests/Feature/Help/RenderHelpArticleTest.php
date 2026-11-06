<?php

use App\Help\Actions\RenderHelpArticle;
use App\Help\Support\HelpIndex;
use App\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    foreach (['platzhalter-probe', 'escape-probe'] as $slug) {
        $pfad = resource_path("help/{$slug}.md");

        if (file_exists($pfad)) {
            unlink($pfad);
        }
    }
});

it('rendert Markdown zu HTML', function () {
    $entry = app(HelpIndex::class)->find('erste-schritte', []);

    expect(app(RenderHelpArticle::class)($entry)->html)
        ->toContain('<h2>Projekttypen zuerst</h2>');
});

it('setzt die API-Domain ein', function () {
    config(['services.api.url' => 'https://api.beispiel.test']);
    file_put_contents(resource_path('help/platzhalter-probe.md'), <<<'MD'
        ---
        titel: Probe
        beschreibung: Probe
        art: aufgabe
        ---

        Endpunkt: %api_url%/anfragen
        MD);

    $entry = app(HelpIndex::class)->find('platzhalter-probe', []);

    expect(app(RenderHelpArticle::class)($entry)->html)
        ->toContain('https://api.beispiel.test/anfragen');
});

it('escaped einen Wert, der aus einer Eingabe des Inhabers stammt', function () {
    // Der Studioname steckt geslugged in der Inbound-Adresse. Der Artikeltext
    // ist unser eigener und darf HTML sein; ein Wert aus der Datenbank nicht.
    StudioSettings::query()->updateOrCreate([], ['display_name' => '<script>alert(1)</script>']);

    file_put_contents(resource_path('help/escape-probe.md'), <<<'MD'
        ---
        titel: Probe
        beschreibung: Probe
        art: aufgabe
        ---

        Studio: %studio_name%
        MD);

    $entry = app(HelpIndex::class)->find('escape-probe', []);
    $html = app(RenderHelpArticle::class)($entry)->html;

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('lässt einen Abschnitt mit leerem Platzhalter ganz weg', function () {
    StudioSettings::current()->forceFill(['inbound_secret' => null])->save();

    $entry = app(HelpIndex::class)->find('mail-weiterleitung', []);

    expect(app(RenderHelpArticle::class)($entry)->html)
        ->not->toContain('genau eine eigene Adresse')
        ->and(app(RenderHelpArticle::class)($entry)->html)->not->toContain('<pre>');
});
