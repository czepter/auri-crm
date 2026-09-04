<?php

use App\Gallery\Models\Gallery;
use App\Settings\Support\StudioSettingsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps a gallery indefinitely when no expiry is set', function () {
    // Null means kept. Every gallery that exists today is one of these, which
    // is why the migration backfills nothing.
    expect(Gallery::factory()->create()->expires_at)->toBeNull();
});

it('carries an expiry as an immutable datetime', function () {
    $gallery = Gallery::factory()->create(['expires_at' => now()->addDays(30)]);

    expect($gallery->fresh()->expires_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('stamps an expiry at publish when the studio has set a retention', function () {
    app(StudioSettingsResolver::class)->set('gallery_default_expiry_days', 90, null);
    $gallery = Gallery::factory()->create();

    $gallery->publish();

    expect($gallery->fresh()->expires_at->toDateString())
        ->toBe(now()->addDays(90)->toDateString());
});

it('does not restart the clock on a second publish', function () {
    app(StudioSettingsResolver::class)->set('gallery_default_expiry_days', 90, null);
    $gallery = Gallery::factory()->create();
    $gallery->publish();
    $first = $gallery->fresh()->expires_at;

    $this->travel(10)->days();
    $gallery->fresh()->publish();

    expect($gallery->fresh()->expires_at->eq($first))->toBeTrue();
});

it('stamps nothing when no retention is configured', function () {
    app(StudioSettingsResolver::class)->set('gallery_default_expiry_days', null, null);
    $gallery = Gallery::factory()->create();

    $gallery->publish();

    expect($gallery->fresh()->expires_at)->toBeNull();
});

// Zwei Quellen für dieselbe Frist waren eine zu viel: config/gallery.php
// begründete, warum es kein Setting sein solle, während das Setting bereits
// existierte und in Settings/Galleries.vue editiert wurde.
it('stamps the expiry from the studio setting, not from a config value', function () {
    app(StudioSettingsResolver::class)->set('gallery_default_expiry_days', 30, null);

    $gallery = Gallery::factory()->create();
    $gallery->publish();

    expect($gallery->fresh()->expires_at->toDateString())
        ->toBe(now()->addDays(30)->toDateString());
});

it('leaves a gallery without a studio default standing indefinitely', function () {
    app(StudioSettingsResolver::class)->set('gallery_default_expiry_days', null, null);

    $gallery = Gallery::factory()->create();
    $gallery->publish();

    expect($gallery->fresh()->expires_at)->toBeNull();
});
