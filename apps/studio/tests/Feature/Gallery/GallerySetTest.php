<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('holds a named set with its own sort rule', function () {
    $gallery = Gallery::factory()->create();

    $set = GallerySet::create([
        'gallery_id' => $gallery->id,
        'name' => 'Trauung',
        'position' => 0,
    ]);

    expect($set->sort_key)->toBe('taken_at')
        ->and($set->sort_dir)->toBe('asc')
        ->and($set->gallery->is($gallery))->toBeTrue();
});

it('refuses two sets of the same name in one gallery', function () {
    $gallery = Gallery::factory()->create();

    GallerySet::create(['gallery_id' => $gallery->id, 'name' => 'Feier', 'position' => 0]);

    expect(fn () => GallerySet::create(['gallery_id' => $gallery->id, 'name' => 'Feier', 'position' => 1]))
        ->toThrow(QueryException::class);
});

it('gives every fresh gallery one set to put pictures in', function () {
    $gallery = Gallery::factory()->create();

    expect($gallery->sets()->pluck('name')->all())->toBe(['Highlights']);
});

it('gives an external gallery none, it holds no pictures', function () {
    $gallery = Gallery::factory()->create(['is_external' => true, 'external_url' => 'https://example.test/g']);

    expect($gallery->sets()->count())->toBe(0);
});

it('backfills a Highlights set for every non-external gallery already on the table', function () {
    Gallery::factory()->create(['is_external' => true, 'external_url' => 'https://example.test/g']);
    $gallery = Gallery::factory()->create();

    $migration = '2026_08_24_000100_create_gallery_sets_table';

    Artisan::call('migrate:rollback', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);
    Artisan::call('migrate', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

    $sets = GallerySet::all();

    expect($sets)->toHaveCount(1)
        ->and($sets->first()->gallery_id)->toBe($gallery->id)
        ->and($sets->first()->name)->toBe('Highlights')
        ->and(strlen($sets->first()->public_id))->toBe(24);
});
