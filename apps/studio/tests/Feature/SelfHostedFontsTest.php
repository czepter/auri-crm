<?php

use Symfony\Component\Finder\Finder;

// #233. A stylesheet that names fonts.googleapis.com makes the visitor's
// browser hand its IP to Google — on the portal and in the gallery that
// visitor is a client or one of their guests, who never consented and were
// never asked. The rule is easier to keep than to re-discover in a cease
// and desist letter, so it is a test.

$roots = [
    __DIR__.'/../../resources/css',
    __DIR__.'/../../../../mockups',
];

it('never loads a font from a third party', function () use ($roots) {
    $offenders = [];

    foreach ($roots as $root) {
        foreach (Finder::create()->files()->in($root)->name(['*.css', '*.html', '*.vue']) as $file) {
            if (str_contains($file->getContents(), 'fonts.googleapis.com')
                || str_contains($file->getContents(), 'fonts.gstatic.com')) {
                $offenders[] = $file->getRelativePathname();
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('ships the woff2 files every @font-face points at', function () {
    $sheets = [
        __DIR__.'/../../resources/css/fonts.css' => __DIR__.'/../../public/fonts',
        __DIR__.'/../../../../mockups/_shared/fonts.css' => __DIR__.'/../../../../mockups/_shared/fonts',
    ];

    foreach ($sheets as $sheet => $dir) {
        preg_match_all("/url\('[^']*\/([^\/']+\.woff2)'\)/", file_get_contents($sheet), $matches);

        expect($matches[1])->not->toBeEmpty();

        foreach ($matches[1] as $file) {
            expect(is_file("{$dir}/{$file}"))->toBeTrue("missing {$dir}/{$file}");
        }
    }
});
