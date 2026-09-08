<?php

use App\Help\Support\HelpIndex;

it('findet den Artikel zu einer Route', function () {
    $treffer = app(HelpIndex::class)->forRoute('leads.index', ['manage_leads']);

    expect($treffer)->toHaveCount(1)
        ->and($treffer[0]->slug)->toBe('lead-anlegen')
        ->and($treffer[0]->titel)->toBe('Lead anlegen');
});

it('verschweigt einen Artikel, dessen Fähigkeit fehlt', function () {
    expect(app(HelpIndex::class)->forRoute('leads.index', []))->toBe([])
        ->and(app(HelpIndex::class)->find('lead-anlegen', []))->toBeNull();
});

it('gibt für eine Route ohne Artikel nichts zurück', function () {
    expect(app(HelpIndex::class)->forRoute('galleries.index', ['manage_leads']))->toBe([]);
});

it('lässt verborgene Artikel aus visible() weg', function () {
    expect(app(HelpIndex::class)->visible(['manage_leads'])->pluck('slug'))
        ->not->toContain('anfrageformular-einbinden')
        ->and(app(HelpIndex::class)->visible([])->pluck('slug'))->not->toContain('lead-anlegen');
});

// Die Fähigkeitsregel steht nur in HelpIndex::darf(); wer sie im Controller
// erneut aufschreibt statt visible()/find()/forRoute() zu rufen, öffnet die
// Lücke wieder, die dieser Task geschlossen hat.
it('implementiert die Fähigkeitsregel nur an einer Stelle', function () {
    $controller = file_get_contents(app_path('Help/Http/Controllers/HelpController.php'));

    expect($controller)->not->toContain('faehigkeit');
});
