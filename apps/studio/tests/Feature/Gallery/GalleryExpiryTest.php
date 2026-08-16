<?php

use App\Gallery\Models\Gallery;
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
    config(['gallery.retention_days' => 90]);
    $gallery = Gallery::factory()->create(['assets' => ['a1']]);

    $gallery->publish();

    expect($gallery->fresh()->expires_at->toDateString())
        ->toBe(now()->addDays(90)->toDateString());
});

it('does not restart the clock on a second publish', function () {
    config(['gallery.retention_days' => 90]);
    $gallery = Gallery::factory()->create(['assets' => ['a1']]);
    $gallery->publish();
    $first = $gallery->fresh()->expires_at;

    $this->travel(10)->days();
    $gallery->fresh()->publish();

    expect($gallery->fresh()->expires_at->eq($first))->toBeTrue();
});

it('stamps nothing when no retention is configured', function () {
    config(['gallery.retention_days' => null]);
    $gallery = Gallery::factory()->create(['assets' => ['a1']]);

    $gallery->publish();

    expect($gallery->fresh()->expires_at)->toBeNull();
});
