<?php

use App\Gallery\Actions\IssueGalleryDownloadLink;
use App\Gallery\Jobs\BuildZip;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryDownloadLink;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Notifications\GalleryZipReady;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildPart;
use App\Media\Models\ZipBuildWaiter;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

function downloadableGallery(?string $pin = null): GalleryVersion
{
    $gallery = Gallery::factory()->create();
    $pin === null || $gallery->rotateDownloadPin($pin);

    return GalleryVersion::factory()->for($gallery->fresh())->create();
}

/** @return array{ZipBuild, string, string} Bau, Token, Adresse des Links */
function mailedLink(GalleryVersion $version, string $email = 'anna@example.test', ZipBuildStatus $status = ZipBuildStatus::Ready): array
{
    $gallery = $version->gallery;
    $build = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => $status, 'guests' => true]);
    ZipBuildPart::factory()->for($build)->create(['part_number' => 1]);
    $guest = confirmedGuest($gallery, $email)[0];
    $url = app(IssueGalleryDownloadLink::class)($gallery, $build, $guest);

    return [$build, str($url)->afterLast('/')->toString(), $url];
}

it('starts one build and remembers who waits for it', function () {
    Queue::fake();
    $gallery = downloadableGallery()->gallery;

    $this->postJson("/api/galleries/{$gallery->public_id}/download/link", ['email' => 'Anna@Example.test', 'size' => 'web'])
        ->assertStatus(202);

    Queue::assertPushed(BuildZip::class, 1);
    expect(ZipBuildWaiter::sole()->guest->email)->toBe('anna@example.test')
        ->and(ZipBuild::sole()->guests)->toBeTrue();
});

it('mails at once when the archive already stands', function () {
    Notification::fake();
    $version = downloadableGallery();
    $gallery = $version->gallery;
    $build = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => ZipBuildStatus::Ready, 'size' => ZipBuildSize::Web, 'guests' => true]);
    ZipBuildPart::factory()->for($build)->create(['part_number' => 1]);

    $this->postJson("/api/galleries/{$gallery->public_id}/download/link", ['email' => 'anna@example.test', 'size' => 'web'])
        ->assertStatus(202);

    Notification::assertSentOnDemand(GalleryZipReady::class, fn ($mail, $channels, $notifiable) => $notifiable->routes['mail'] === 'anna@example.test');
    expect(GalleryDownloadLink::sole()->zip_build_id)->toBe($build->id)
        ->and($build->fresh()->status)->toBe(ZipBuildStatus::Ready);
});

it('wants the pin when the gallery has one', function () {
    Queue::fake();
    $gallery = downloadableGallery('4711')->gallery;
    $url = "/api/galleries/{$gallery->public_id}/download/link";

    $this->postJson($url, ['email' => 'anna@example.test', 'size' => 'web'])->assertStatus(422);
    $this->postJson($url, ['email' => 'anna@example.test', 'size' => 'web', 'pin' => '0000'])->assertStatus(422);
    Queue::assertNothingPushed();

    $this->postJson($url, ['email' => 'anna@example.test', 'size' => 'web', 'pin' => '4711'])->assertStatus(202);
});

it('refuses a size the gallery does not offer', function () {
    Queue::fake();
    $gallery = downloadableGallery()->gallery;
    $gallery->forceFill(['download_sizes' => ['web']])->save();

    $this->postJson("/api/galleries/{$gallery->public_id}/download/link", ['email' => 'anna@example.test', 'size' => 'original'])
        ->assertStatus(422);
});

it('stops a fourth request for the same address within the hour', function () {
    Queue::fake();
    $gallery = downloadableGallery()->gallery;
    $url = "/api/galleries/{$gallery->public_id}/download/link";

    foreach (range(1, 3) as $ignored) {
        $this->postJson($url, ['email' => 'anna@example.test', 'size' => 'web'])->assertStatus(202);
    }

    $this->postJson($url, ['email' => 'Anna@example.test', 'size' => 'web'])->assertStatus(429);
    $this->postJson($url, ['email' => 'ben@example.test', 'size' => 'web'])->assertStatus(202);
});

it('lists the parts for the token alone, even on a locked gallery', function () {
    $version = downloadableGallery('4711');
    $gallery = $version->gallery;
    $gallery->rotateViewPassword('geheim');
    [, $token] = mailedLink($version);

    $path = $this->getJson("/api/galleries/{$gallery->public_id}/download-mail/{$token}")
        ->assertOk()
        ->assertJsonPath('size', 'original')
        ->assertJsonCount(1, 'parts')
        ->json('parts.0.path');

    expect($path)->toStartWith('/api/galleries/');

    $this->get($path.'x')->assertForbidden();
});

it('knows no expired, foreign or unfinished token', function () {
    $version = downloadableGallery();
    $gallery = $version->gallery;
    [, $token] = mailedLink($version);
    [, $unfinished] = mailedLink($version, 'ben@example.test', ZipBuildStatus::Building);
    $other = downloadableGallery()->gallery;

    $this->getJson("/api/galleries/{$other->public_id}/download-mail/{$token}")->assertNotFound();
    $this->getJson("/api/galleries/{$gallery->public_id}/download-mail/{$unfinished}")->assertNotFound();
    $this->getJson("/api/galleries/{$gallery->public_id}/download-mail/unbekannt")->assertNotFound();

    $this->travel(GalleryDownloadLink::VALID_FOR_DAYS + 1)->days();

    $this->getJson("/api/galleries/{$gallery->public_id}/download-mail/{$token}")->assertNotFound();
});

it('stops a part link once the pin was rotated', function () {
    $version = downloadableGallery('4711');
    $gallery = $version->gallery;
    [, $token] = mailedLink($version);

    $path = $this->getJson("/api/galleries/{$gallery->public_id}/download-mail/{$token}")->json('parts.0.path');

    $gallery->fresh()->rotateDownloadPin('0815');

    $this->get($path)->assertNotFound();
});

it('stops a part link once the view password was rotated', function () {
    $version = downloadableGallery();
    $gallery = $version->gallery;
    [, $token] = mailedLink($version);

    $path = $this->getJson("/api/galleries/{$gallery->public_id}/download-mail/{$token}")->json('parts.0.path');

    $gallery->fresh()->rotateViewPassword('neu');

    $this->get($path)->assertNotFound();
});

it('carries the cover for the backdrop, except for a locked gallery', function () {
    $version = downloadableGallery();
    $version->forceFill(['cover_key' => 'aa/cover.jpg'])->save();
    $gallery = $version->gallery;
    [, $token] = mailedLink($version);
    $url = "/api/galleries/{$gallery->public_id}/download-mail/{$token}";

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('gallery.cover.key', 'aa/cover.jpg')
        ->assertJsonPath('gallery.cover.version', $version->public_id);

    $gallery->forceFill(['view_mode' => 'locked'])->save();

    $this->getJson($url)->assertOk()->assertJsonPath('gallery.cover', null);
});
