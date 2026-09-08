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
