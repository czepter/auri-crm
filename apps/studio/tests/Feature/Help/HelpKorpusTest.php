<?php

use App\Help\Support\HelpIndex;
use Illuminate\Support\Facades\Route;

// CLAUDE.md: "Eine Route, die keiner geprüft hat." Ein screens-Eintrag, den
// es nicht gibt, macht einen Artikel unerreichbar und fällt sonst nirgends
// auf — hier fällt er auf.
it('nennt nur Routen, die es gibt', function () {
    $bekannt = collect(Route::getRoutes())->map->getName()->filter()->all();

    $unbekannt = app(HelpIndex::class)->all()
        ->flatMap(fn ($entry) => array_map(
            fn ($screen) => "{$entry->slug}: {$screen}",
            array_diff($entry->screens, $bekannt),
        ))
        ->values();

    expect($unbekannt->all())->toBe([]);
});

it('hält höchstens zwei Artikel je Route, einen je Art', function () {
    $doppelt = app(HelpIndex::class)->all()
        ->flatMap(fn ($entry) => array_map(
            fn ($screen) => ['screen' => $screen, 'art' => $entry->art, 'slug' => $entry->slug],
            $entry->screens,
        ))
        ->groupBy(fn ($row) => $row['screen'].'/'.$row['art'])
        ->filter(fn ($rows) => $rows->count() > 1)
        ->keys();

    expect($doppelt->all())->toBe([]);
});

it('kennt nur die zwei Arten', function () {
    $fremd = app(HelpIndex::class)->all()
        ->reject(fn ($entry) => in_array($entry->art, ['aufgabe', 'referenz'], true))
        ->map->slug;

    expect($fremd->all())->toBe([]);
});

// Dieselbe Klasse wie oben, eine Ebene tiefer: ein Link auf einen Slug, den
// es nicht gibt, ist eine 404 in der Spalte. Und ein ungegateter Artikel, der
// in einen gegateten zeigt, ist dieselbe 404 für jeden, dem die Fähigkeit
// fehlt — `erste-schritte` traf damit vier von fünf Rollen.
it('verlinkt nur Artikel, die der Leser des Links auch sehen darf', function () {
    $faehigkeit = app(HelpIndex::class)->all()
        ->mapWithKeys(fn ($entry) => [$entry->slug => $entry->faehigkeit]);

    $kaputt = [];

    foreach (glob(resource_path('help').'/*.md') as $pfad) {
        $von = basename($pfad, '.md');
        preg_match_all('#\]\(/hilfe/([a-z0-9-]+)\)#', file_get_contents($pfad), $treffer);

        foreach ($treffer[1] as $nach) {
            if (! $faehigkeit->has($nach)) {
                $kaputt[] = "{$von} -> {$nach}: kein solcher Artikel";

                continue;
            }

            if ($faehigkeit[$nach] !== null && $faehigkeit[$nach] !== $faehigkeit[$von]) {
                $kaputt[] = "{$von} -> {$nach}: braucht {$faehigkeit[$nach]}";
            }
        }
    }

    expect($kaputt)->toBe([]);
});

// Der Reintext entstand einmal aus einem zerschnittenen `<form …>` heraus:
// 3.575 Bytes Artikel wurden 171 Bytes Suchtext, drei Abschnitte fielen
// lautlos aus der Palette. Der Artikel mit dem HTML-Snippet hält 0,45,
// jeder andere liegt über 0,96.
it('behält im Suchtext den größeren Teil des Artikels', function () {
    $duenn = app(HelpIndex::class)->all()
        ->map(fn ($entry) => [
            'slug' => $entry->slug,
            'anteil' => mb_strlen($entry->suchtext) / mb_strlen(preg_replace(
                '/\A---\R.*?\R---\R/s', '', file_get_contents(resource_path("help/{$entry->slug}.md")),
            )),
        ])
        ->filter(fn ($zeile) => $zeile['anteil'] < 0.3)
        ->map(fn ($zeile) => sprintf('%s: %.2f', $zeile['slug'], $zeile['anteil']))
        ->values();

    expect($duenn->all())->toBe([]);
});
