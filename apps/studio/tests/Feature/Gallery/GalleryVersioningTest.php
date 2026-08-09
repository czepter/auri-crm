<?php

use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a gallery with a nullable shoot_id', function () {
    $project = Project::factory()->create();

    $projectWide = Gallery::factory()->for($project)->create();
    $perShoot = Gallery::factory()->for($project)->for(Shoot::factory()->for($project), 'shoot')->create();

    expect($projectWide->shoot_id)->toBeNull()
        ->and($perShoot->shoot_id)->not->toBeNull();
});

it('has no current version before the first publish', function () {
    $gallery = Gallery::factory()->create();

    expect($gallery->currentVersion)->toBeNull()
        ->and($gallery->versions()->count())->toBe(0);
});

it('publishing produces an immutable version 1', function () {
    $gallery = Gallery::factory()->create(['title' => 'Verlobung', 'assets' => ['a1', 'a2']]);

    $v1 = $gallery->publish();

    expect($v1)->toBeInstanceOf(GalleryVersion::class)
        ->and($v1->version)->toBe(1)
        ->and($v1->title)->toBe('Verlobung')
        ->and($v1->assets)->toBe(['a1', 'a2'])
        ->and($v1->published_at)->not->toBeNull();

    expect($gallery->currentVersion()->first()->id)->toBe($v1->id);
});

it('editing the draft afterwards requires publishing a new version to change what is served', function () {
    $gallery = Gallery::factory()->create(['title' => 'Verlobung', 'assets' => ['a1']]);
    $v1 = $gallery->publish();

    $gallery->update(['title' => 'Verlobung (korrigiert)', 'assets' => ['a1', 'a2', 'a3']]);

    // The draft changed, but nothing published moved — v1 still exists, and
    // it is still what a caller reading "the current version" would get.
    $v1->refresh();
    expect($v1->title)->toBe('Verlobung')
        ->and($v1->assets)->toBe(['a1'])
        ->and($gallery->currentVersion()->first()->id)->toBe($v1->id);
});

it('publishing again creates a new version and leaves the previous one serving its exact original contents', function () {
    $gallery = Gallery::factory()->create(['title' => 'Verlobung', 'assets' => ['a1', 'a2']]);
    $v1 = $gallery->publish();

    $gallery->update(['title' => 'Verlobung (korrigiert)', 'assets' => ['a1', 'a2', 'a3']]);
    $v2 = $gallery->publish();

    expect($v2->id)->not->toBe($v1->id)
        ->and($v2->version)->toBe(2)
        ->and($v2->title)->toBe('Verlobung (korrigiert)')
        ->and($v2->assets)->toBe(['a1', 'a2', 'a3']);

    // v1's exact original snapshot — untouched by the edit, untouched by v2.
    $v1->refresh();
    expect($v1->version)->toBe(1)
        ->and($v1->title)->toBe('Verlobung')
        ->and($v1->assets)->toBe(['a1', 'a2']);

    expect($gallery->currentVersion()->first()->id)->toBe($v2->id);
    expect(GalleryVersion::findByPublicId($v1->public_id)->assets)->toBe(['a1', 'a2']);
});

it('numbers versions off the gallery maximum, not off a counter that could be raced', function () {
    $gallery = Gallery::factory()->create();
    $gallery->publish();
    $gallery->publish();
    $v3 = $gallery->publish();

    expect($v3->version)->toBe(3)
        ->and($gallery->versions()->count())->toBe(3);
});

it('scopes versions to their own gallery, not to every gallery in the family of the migration pattern', function () {
    $a = Gallery::factory()->create();
    $b = Gallery::factory()->create();

    $a->publish();
    $a->publish();
    $b->publish();

    expect($a->versions()->count())->toBe(2)
        ->and($b->versions()->count())->toBe(1)
        ->and($b->currentVersion->version)->toBe(1);
});
