<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Gallery\Jobs\PurgeGalleryObjects;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\AssetHide;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryDownloadLink;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Models\GalleryView;
use App\Media\Models\Asset;
use App\Media\Models\AssetDerivative;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildPart;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->admin = User::factory()->withMfa()->create(['role' => StaffRole::Admin]);
    $this->actingAs($this->admin, 'staff');

    $this->seed = function (Gallery $gallery): Asset {
        $base = "galleries/{$gallery->tenant_id}/{$gallery->public_id}";
        $asset = Asset::factory()->create(['gallery_id' => $gallery->id, 'key' => "{$base}/originals/a/x.jpg", 'filename' => 'x.jpg']);
        AssetDerivative::factory()->create(['asset_id' => $asset->id, 'key' => "{$base}/derivatives/a/800.webp"]);
        Storage::disk('s3')->put($asset->key, 'original');
        Storage::disk('s3')->put("{$base}/derivatives/a/800.webp", 'derivative');
        Storage::disk('s3')->put("{$base}/zips/b/part-1.zip", 'zip');

        return $asset;
    };
});

it('removes rows and storage objects, writes the export and a project note, and spares another gallery', function () {
    $case = ClientCase::factory()->create();
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit', 'case_id' => $case->id]);
    $other = Gallery::factory()->create();
    ($this->seed)($gallery);
    $kept = ($this->seed)($other);

    $version = $gallery->publish();
    AssetFavorite::factory()->create(['gallery_version_id' => $version->id, 'asset_key' => 'originals/a/x.jpg']);
    AssetComment::factory()->create(['gallery_version_id' => $version->id, 'asset_key' => 'originals/a/x.jpg', 'body' => 'Schön']);

    AssetHide::create(['gallery_id' => $gallery->id, 'asset_key' => 'originals/a/x.jpg', 'contact_id' => Contact::factory()->create()->id]);
    GalleryView::create(['gallery_id' => $gallery->id, 'visitor_identifier' => 'v', 'viewed_on' => today()]);
    GalleryGuest::create(['gallery_id' => $gallery->id, 'email' => 'g@example.test', 'name' => 'G', 'identifier' => 'gid']);
    GalleryDownloadLink::create(['gallery_id' => $gallery->id, 'token_hash' => 'h', 'expires_at' => now()->addDay()]);
    $build = ZipBuild::factory()->create(['gallery_version_id' => $version->id]);
    ZipBuildPart::factory()->create(['zip_build_id' => $build->id, 'key' => 'elsewhere/part-1.zip']);
    Storage::disk('s3')->put('elsewhere/part-1.zip', 'zip');

    $this->delete("/galerien/{$gallery->public_id}", ['title' => 'Hochzeit'])->assertRedirect(route('galleries.index'));

    expect(Gallery::find($gallery->id))->toBeNull()
        ->and(Asset::where('gallery_id', $gallery->id)->count())->toBe(0)
        ->and(AssetFavorite::count() + AssetComment::count() + AssetHide::count() + GalleryView::count() + GalleryGuest::count() + GalleryDownloadLink::count() + ZipBuild::count() + ZipBuildPart::count())->toBe(0)
        ->and(Storage::disk('s3')->exists('elsewhere/part-1.zip'))->toBeFalse()
        ->and(Storage::disk('s3')->allFiles("galleries/{$gallery->tenant_id}/{$gallery->public_id}"))->toBe([])
        ->and(Storage::disk('s3')->exists($kept->key))->toBeTrue()
        ->and(Gallery::find($other->id))->not->toBeNull()
        ->and(Storage::disk('s3')->get("exports/galleries/{$gallery->public_id}.json"))->toContain('Schön')
        ->and($case->notes()->first()->body)->toContain("exports/galleries/{$gallery->public_id}.json")
        ->and(AuditLog::where('event', 'gallery.deleted')->count())->toBe(1);
});

it('refuses a wrong title', function () {
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit']);

    $this->delete("/galerien/{$gallery->public_id}", ['title' => 'hochzeit '])->assertSessionHasErrors('title');

    expect(Gallery::find($gallery->id))->not->toBeNull();
});

it('is forbidden without the owner capability', function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Manager]), 'staff');
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit']);

    $this->delete("/galerien/{$gallery->public_id}", ['title' => 'Hochzeit'])->assertForbidden();
});

it('purges idempotently and tolerates objects that are gone', function () {
    Storage::disk('s3')->put('galleries/t/g/originals/a/x.jpg', 'x');
    Storage::disk('s3')->put('quarantine/q/x.jpg', 'x');

    $job = new PurgeGalleryObjects('galleries/t/g/', ['quarantine/q/x.jpg', 'quarantine/missing.jpg']);
    $job->handle();
    $job->handle();

    expect(Storage::disk('s3')->allFiles())->toBe([]);
});
