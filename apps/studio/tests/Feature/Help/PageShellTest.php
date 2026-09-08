<?php

// Die Hilfespalte lebt im vierten Slot von Page.vue. Ein Screen, der
// AdminLayout ohne Page rendert, hat den Slot nicht — und bekommt still
// keine Hilfe. Das ist der Test, der die Migration erzwingt und sie
// erzwungen hält.
it('rendert jede Admin-Seite durch Page.vue', function () {
    $pages = collect(
        \Symfony\Component\Finder\Finder::create()
            ->files()
            ->in(resource_path('js/admin/pages'))
            ->name('*.vue')
    );

    $ohnePage = $pages
        ->map(fn ($file) => [$file->getRelativePathname(), $file->getContents()])
        ->filter(fn ($pair) => str_contains($pair[1], 'AdminLayout'))
        ->reject(fn ($pair) => str_contains($pair[1], 'ui/page/Page.vue')
            || str_contains($pair[1], 'ProjectShell'))
        ->map(fn ($pair) => $pair[0])
        ->values();

    expect($ohnePage->all())->toBe([]);
});
