<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Actions\IngestCompletedUpload;
use App\Media\Enums\AssetStatus;
use App\Media\Enums\MultipartUploadStatus;
use App\Media\Models\Asset;
use App\Media\Models\MultipartUpload;
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

it('starts an upload against a named set', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::factory()->for($gallery)->create(['name' => 'Fotobox', 'position' => 1]);

    $this->postJson("/galerien/{$gallery->public_id}/uploads", [
        'gallery_set_id' => $set->public_id,
        'filename' => 'IMG_0001.jpg',
        'file_size' => 5_000_000,
        'file_modified_at' => 1_760_000_000_000,
    ])->assertOk();

    expect(MultipartUpload::first()->gallery_set_id)->toBe($set->id);
});

it('files the ingested asset into the set the upload named', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::factory()->for($gallery)->create(['name' => 'Trauung', 'position' => 1]);

    $upload = MultipartUpload::factory()->for($gallery)->create([
        'gallery_set_id' => $set->id,
        'status' => MultipartUploadStatus::Completed,
    ]);

    app(IngestCompletedUpload::class)($upload);

    expect(Asset::first()->gallery_set_id)->toBe($set->id);
});

it('rejects a null gallery_set_id on an asset', function () {
    $gallery = Gallery::factory()->create();

    expect(fn () => Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => null]))
        ->toThrow(QueryException::class);
});

it('backfills gallery_set_id left null by an asset written before the not-null flip', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    $migration = '2026_08_24_000500_make_gallery_set_id_not_null_on_assets_table';

    Artisan::call('migrate:rollback', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

    $assetId = DB::table('assets')->insertGetId([
        'tenant_id' => $gallery->tenant_id,
        'public_id' => (string) new Cuid2,
        'gallery_id' => $gallery->id,
        'status' => AssetStatus::Ready->value,
        'filename' => 'IMG_0002.jpg',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('migrate', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

    expect(Asset::find($assetId)->gallery_set_id)->toBe($set->id);
});
