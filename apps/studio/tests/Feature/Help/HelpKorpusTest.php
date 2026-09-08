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
