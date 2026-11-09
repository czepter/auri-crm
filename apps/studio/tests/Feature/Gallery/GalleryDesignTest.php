<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPreset;
use App\Gallery\Support\GalleryDesign;
use App\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function designMigration(): object
{
    return require database_path('migrations/2026_11_09_000100_rewrite_gallery_design_keys.php');
}

it('maps legacy keys to the new model', function (array $old, array $new) {
    expect(designMigration()->convert($old))->toBe($new);
})->with([
    'full wide' => [
        ['cover' => 'full', 'type' => 'serif', 'colour' => 'light', 'grid' => 'wide'],
        ['cover' => 'zentriert', 'type' => 'serif', 'colour' => 'hell', 'size' => 'mittel', 'spacing' => 'eng', 'flow' => 'zeilen'],
    ],
    'split dense' => [
        ['cover' => 'split', 'type' => 'comic', 'colour' => 'dark', 'grid' => 'dense'],
        ['cover' => 'geteilt', 'type' => 'sans', 'colour' => 'dunkel', 'size' => 'klein', 'spacing' => 'eng', 'flow' => 'zeilen'],
    ],
    'plain single' => [
        ['cover' => 'plain', 'colour' => 'warm', 'grid' => 'single'],
        ['cover' => 'links', 'colour' => 'sand', 'size' => 'gross', 'spacing' => 'weit', 'flow' => 'zeilen'],
    ],
    'already new' => [
        ['cover' => 'rahmen', 'colour' => 'meer', 'size' => 'klein', 'spacing' => 'weit', 'flow' => 'spalten'],
        ['cover' => 'rahmen', 'colour' => 'meer', 'size' => 'klein', 'spacing' => 'weit', 'flow' => 'spalten'],
    ],
]);

it('rewrites galleries, presets, versions and the studio default', function () {
    $legacy = ['cover' => 'split', 'type' => 'serif', 'colour' => 'dark', 'grid' => 'single'];
    $gallery = Gallery::factory()->create(['design' => $legacy]);
    $version = $gallery->versions()->create([
        'version' => 1, 'title' => 'x', 'assets' => [], 'design' => $legacy, 'published_at' => now(),
    ]);
    $preset = GalleryPreset::factory()->create(['design' => $legacy]);
    StudioSettings::current()->forceFill(['gallery_default_design' => $legacy])->save();

    designMigration()->up();

    $expected = ['cover' => 'geteilt', 'type' => 'serif', 'colour' => 'dunkel', 'size' => 'gross', 'spacing' => 'weit', 'flow' => 'zeilen'];

    expect($gallery->fresh()->design)->toEqual($expected)
        ->and($version->fresh()->design)->toEqual($expected)
        ->and($preset->fresh()->design)->toEqual($expected)
        ->and(StudioSettings::current()->fresh()->gallery_default_design)->toEqual($expected);
});

it('fills what a stored design leaves out with the built-in values', function () {
    expect(GalleryDesign::resolve(['colour' => 'meer', 'cover' => 'stripe']))
        ->toBe([...GalleryPreset::DESIGN, 'colour' => 'meer'])
        ->and(GalleryDesign::resolve(null))->toBe(GalleryPreset::DESIGN);
});
