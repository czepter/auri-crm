<?php

use App\Gallery\Jobs\BuildZip;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Models\GalleryVersion;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildPart;
use App\Media\Models\ZipBuildWaiter;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

function downloadableGallery(?string $pin = null): GalleryVersion
{
    $gallery = Gallery::factory()->create();
    $pin === null || $gallery->rotateDownloadPin($pin);

    return GalleryVersion::factory()->for($gallery->fresh())->create();
}

it('asks for the pin before the address, then for the address, then offers the sizes', function () {
    $version = downloadableGallery('4711');
    $gallery = $version->gallery;
    [, $guestPass] = confirmedGuest($gallery);

    $this->getJson("/api/galleries/{$gallery->public_id}/download", ['X-Guest-Pass' => $guestPass])
        ->assertOk()->assertJsonPath('step', 'pin');

    $downloadPass = $this->postJson("/api/galleries/{$gallery->public_id}/download/pin", ['pin' => '4711'])
        ->assertOk()->json('pass');

    $this->getJson("/api/galleries/{$gallery->public_id}/download", ['X-Download-Pass' => $downloadPass])
        ->assertOk()->assertJsonPath('step', 'email');

    $this->getJson("/api/galleries/{$gallery->public_id}/download", ['X-Download-Pass' => $downloadPass, 'X-Guest-Pass' => $guestPass])
        ->assertOk()->assertJsonPath('step', 'ready')->assertJsonStructure(['sizes' => ['original', 'web']]);
});

it('refuses a wrong pin', function () {
    $gallery = downloadableGallery('4711')->gallery;

    $this->postJson("/api/galleries/{$gallery->public_id}/download/pin", ['pin' => '0000'])->assertStatus(422);
});

it('starts one build and remembers who waits for it', function () {
    Queue::fake();
    $gallery = downloadableGallery()->gallery;
    [$guest, $guestPass] = confirmedGuest($gallery);

    $this->postJson("/api/galleries/{$gallery->public_id}/download/vorbereiten", ['size' => 'web'], ['X-Guest-Pass' => $guestPass])
        ->assertOk()->assertJsonPath('builds.0.status', 'building');

    Queue::assertPushed(BuildZip::class, 1);
    expect(ZipBuildWaiter::query()->where('gallery_guest_id', $guest->id)->count())->toBe(1);
});

it('refuses to prepare without a confirmed guest', function () {
    $gallery = downloadableGallery()->gallery;

    $this->postJson("/api/galleries/{$gallery->public_id}/download/vorbereiten", ['size' => 'web'])->assertNotFound();
});

it('hands out a part link that downloads without any bearer', function () {
    $version = downloadableGallery();
    $gallery = $version->gallery;
    [$guest, $guestPass] = confirmedGuest($gallery);
    $build = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => ZipBuildStatus::Ready]);
    ZipBuildPart::factory()->for($build)->create(['part_number' => 1]);

    $path = $this->getJson("/api/galleries/{$gallery->public_id}/download", ['X-Guest-Pass' => $guestPass])
        ->assertOk()->json('builds.0.parts.0.path');

    expect($path)->toStartWith('/api/galleries/');

    $this->get($path.'x')->assertForbidden();
    expect(GalleryGuest::query()->whereKey($guest->id)->exists())->toBeTrue();
});

it('refuses the pin to a visitor without the view pass of a locked gallery', function () {
    $gallery = downloadableGallery('4711')->gallery;
    $gallery->rotateViewPassword('geheim');

    $this->postJson("/api/galleries/{$gallery->public_id}/download/pin", ['pin' => '4711'])->assertStatus(422);
});

it('stops a part link once the pin was rotated', function () {
    $version = downloadableGallery('4711');
    $gallery = $version->gallery;
    [, $guestPass] = confirmedGuest($gallery);
    $build = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => ZipBuildStatus::Ready]);
    ZipBuildPart::factory()->for($build)->create(['part_number' => 1]);
    $pass = $this->postJson("/api/galleries/{$gallery->public_id}/download/pin", ['pin' => '4711'])->json('pass');

    $path = $this->getJson("/api/galleries/{$gallery->public_id}/download", ['X-Guest-Pass' => $guestPass, 'X-Download-Pass' => $pass])
        ->assertOk()->json('builds.0.parts.0.path');

    $gallery->fresh()->rotateDownloadPin('0815');

    $this->get($path)->assertNotFound();
});

it('stops a part link once the view password was rotated', function () {
    $version = downloadableGallery();
    $gallery = $version->gallery;
    [, $guestPass] = confirmedGuest($gallery);
    $build = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => ZipBuildStatus::Ready]);
    ZipBuildPart::factory()->for($build)->create(['part_number' => 1]);

    $path = $this->getJson("/api/galleries/{$gallery->public_id}/download", ['X-Guest-Pass' => $guestPass])
        ->assertOk()->json('builds.0.parts.0.path');

    $gallery->fresh()->rotateViewPassword('neu');

    $this->get($path)->assertNotFound();
});
