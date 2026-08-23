<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Enums\AssetStatus;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Visus\Cuid2\Cuid2;

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

    // assets.gallery_set_id (Task 2) foreign-keys into gallery_sets, so rolling
    // back this migration alone would fail on the dependent constraint —
    // both migrations roll back and forward together, in dependency order.
    $paths = [
        'database/migrations/2026_08_24_000100_create_gallery_sets_table.php',
        'database/migrations/2026_08_24_000200_add_set_and_draft_state_to_assets_table.php',
    ];

    Artisan::call('migrate:rollback', ['--path' => $paths, '--force' => true]);
    Artisan::call('migrate', ['--path' => $paths, '--force' => true]);

    $sets = GallerySet::all();

    expect($sets)->toHaveCount(1)
        ->and($sets->first()->gallery_id)->toBe($gallery->id)
        ->and($sets->first()->name)->toBe('Highlights')
        ->and(strlen($sets->first()->public_id))->toBe(24);
});

it('keeps a removed asset out of the draft and inside the version', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    $stays = Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id]);
    $goes = Asset::factory()->create([
        'gallery_id' => $gallery->id,
        'gallery_set_id' => $set->id,
        'removed_from_draft_at' => now(),
    ]);

    expect(Asset::inDraft()->pluck('id')->all())->toBe([$stays->id])
        // Ohne globalen Scope bleibt die Zeile für BuildGalleryZip auffindbar,
        // sonst braeche das ZIP einer bereits ausgelieferten Version.
        ->and(Asset::whereKey($goes->id)->exists())->toBeTrue();
});

it('resolves a factory asset\'s set from its own gallery', function () {
    $gallery = Gallery::factory()->create();

    $asset = Asset::factory()->create(['gallery_id' => $gallery->id]);

    expect($asset->set->gallery_id)->toBe($gallery->id);
});

it('backfills gallery_set_id for assets already on the table', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    $migration = '2026_08_24_000200_add_set_and_draft_state_to_assets_table';

    Artisan::call('migrate:rollback', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

    $assetId = DB::table('assets')->insertGetId([
        'tenant_id' => $gallery->tenant_id,
        'public_id' => (string) new Cuid2,
        'gallery_id' => $gallery->id,
        'status' => AssetStatus::Ready->value,
        'filename' => 'IMG_0001.jpg',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('migrate', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

    expect(Asset::find($assetId)->gallery_set_id)->toBe($set->id);
});
