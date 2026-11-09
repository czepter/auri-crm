<?php

use App\Enums\StaffRole;
use App\Gallery\Actions\CopyAssetToGallery;
use App\Gallery\Actions\CopyGalleryAssets;
use App\Gallery\Actions\DeleteGallery;
use App\Gallery\Actions\DuplicateGallery;
use App\Gallery\Jobs\CopyGalleryAsset;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Media\Models\AssetDerivative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->admin = User::factory()->withMfa()->create(['role' => StaffRole::Admin]);
    $this->actingAs($this->admin, 'staff');

    $this->source = Gallery::factory()->create(['title' => 'Hochzeit']);
    $this->source->sets()->delete();
    $this->feier = GallerySet::factory()->create(['gallery_id' => $this->source->id, 'name' => 'Feier', 'position' => 0]);
    $this->trauung = GallerySet::factory()->create(['gallery_id' => $this->source->id, 'name' => 'Trauung', 'position' => 1]);

    $this->assets = collect(['a', 'b', 'c'])->map(function (string $name, int $index) {
        $asset = Asset::factory()->create([
            'gallery_id' => $this->source->id,
            'gallery_set_id' => $index === 2 ? $this->trauung->id : $this->feier->id,
            'key' => "galleries/t/{$this->source->public_id}/originals/{$name}/{$name}.jpg",
            'filename' => "{$name}.jpg",
        ]);
        AssetDerivative::factory()->create(['asset_id' => $asset->id, 'width' => 800, 'format' => 'webp', 'key' => "galleries/t/{$this->source->public_id}/derivatives/{$name}/800.webp"]);
        Storage::disk('s3')->put($asset->key, "original-{$name}");
        Storage::disk('s3')->put($asset->derivatives->first()->key, "derivative-{$name}");

        return $asset;
    });

    $this->source->update(['cover_asset_id' => $this->assets[1]->id, 'cover_focal_x' => 30, 'cover_focal_y' => 70]);
});

it('copies originals and derivatives to keys of its own and keeps set, metadata and cover', function () {
    $copy = app(DuplicateGallery::class)($this->source, $this->admin);

    $copied = $copy->assets()->with(['derivatives', 'set'])->orderBy('filename')->get();
    $disk = Storage::disk('s3');

    expect($copied)->toHaveCount(3)
        ->and($copied->map(fn ($asset) => $asset->set->name)->all())->toBe(['Feier', 'Feier', 'Trauung'])
        ->and($copied->every(fn ($asset) => str_starts_with($asset->key, "galleries/{$copy->tenant_id}/{$copy->public_id}/") && $disk->exists($asset->key)))->toBeTrue()
        ->and($copied->every(fn ($asset) => $asset->derivatives->count() === 1 && $disk->exists($asset->derivatives->first()->key)))->toBeTrue()
        ->and($copied[0]->checksum)->toBe($this->assets[0]->checksum)
        ->and($copy->fresh()->cover_asset_id)->toBe($copied[1]->id)
        ->and($copy->fresh()->cover_focal_x)->toBe(30)
        ->and($copy->fresh()->copyProgress())->toBeNull();
});

it('leaves the original objects alone when the copy is deleted', function () {
    $copy = app(DuplicateGallery::class)($this->source, $this->admin);

    app(DeleteGallery::class)($copy, $this->admin);

    expect(Storage::disk('s3')->get($this->assets[0]->key))->toBe('original-a')
        ->and(Storage::disk('s3')->exists($this->assets[0]->derivatives->first()->key))->toBeTrue()
        ->and(Asset::where('gallery_id', $this->source->id)->count())->toBe(3);
});

it('copies only the chosen pictures for an own prerelease and takes the cover only if chosen', function () {
    $this->post("/galerien/{$this->source->public_id}/pre-release", [
        'own' => true,
        'asset_ids' => [$this->assets[0]->public_id, $this->assets[2]->public_id],
    ])->assertRedirect();

    $copy = Gallery::query()->where('title', 'Hochzeit Vorabversion')->firstOrFail();

    expect($copy->assets()->pluck('filename')->sort()->values()->all())->toBe(['a.jpg', 'c.jpg'])
        ->and($copy->copy_total)->toBe(2)
        ->and($copy->coverAsset->filename)->toBe('a.jpg')
        ->and($copy->cover_focal_x)->toBeNull()
        ->and($copy->prerelease_at)->not->toBeNull()
        ->and($this->source->fresh()->prerelease_at)->toBeNull();
});

it('resumes with what is still missing and reports progress', function () {
    Queue::fake();
    $copy = app(DuplicateGallery::class)($this->source, $this->admin);

    Queue::assertPushed(CopyGalleryAsset::class, 3);
    expect($copy->copyProgress())->toBe(['done' => 0, 'total' => 3]);

    app(CopyAssetToGallery::class)($copy, $this->assets[0]);
    app(CopyAssetToGallery::class)($copy, $this->assets[0]);

    expect($copy->copyProgress())->toBe(['done' => 1, 'total' => 3])
        ->and($copy->assets()->count())->toBe(1);

    Queue::fake();
    expect(app(CopyGalleryAssets::class)($copy))->toBe(2);
    Queue::assertPushed(CopyGalleryAsset::class, 2);

    $this->post("/galerien/{$copy->public_id}/kopie-fortsetzen")->assertRedirect();
    Queue::assertPushed(CopyGalleryAsset::class, 4);
});

it('fails the job when the original is missing and creates no row', function () {
    Queue::fake();
    $copy = app(DuplicateGallery::class)($this->source, $this->admin);
    Storage::disk('s3')->delete($this->assets[0]->key);

    expect(fn () => app(CopyAssetToGallery::class)($copy, $this->assets[0]))->toThrow(RuntimeException::class)
        ->and($copy->assets()->count())->toBe(0);
});
