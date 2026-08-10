<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\DownloadEvent;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildPart;
use App\Support\Surface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

// mockS3Client() lives in tests/Pest.php.

/**
 * A GetObject stand-in that actually honours the `Range` parameter the way
 * a real bucket does — returning only the requested slice of $bytes plus a
 * correct ContentRange — so a test can prove the controller asked for
 * exactly the right bytes, not just that it called GetObject at all.
 */
function zipPartGetObjectMock(string $bytes): S3Client
{
    $total = strlen($bytes);

    $handler = new MockHandler(array_fill(0, 50, function ($command) use ($bytes, $total) {
        $range = $command['Range'] ?? null;

        if ($range === null) {
            return new Result(['Body' => Utils::streamFor($bytes), 'ContentLength' => $total]);
        }

        preg_match('/^bytes=(\d+)-(\d+)$/', $range, $m);
        [$start, $end] = [(int) $m[1], (int) $m[2]];
        $slice = substr($bytes, $start, $end - $start + 1);

        return new Result([
            'Body' => Utils::streamFor($slice),
            'ContentLength' => strlen($slice),
            'ContentRange' => "bytes {$start}-{$end}/{$total}",
        ]);
    }));

    return mockS3Client($handler);
}

/** @return array{gallery: Gallery, zipBuild: ZipBuild, part: ZipBuildPart, bytes: string, url: string} */
function readyDownload(string $bytes, string $guestId = 'guest-1'): array
{
    $gallery = Gallery::factory()->create();
    $version = GalleryVersion::factory()->for($gallery)->create();
    $zipBuild = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => ZipBuildStatus::Ready]);
    $part = ZipBuildPart::factory()->for($zipBuild)->create([
        'part_number' => 1,
        'key' => "galleries/{$gallery->tenant_id}/{$gallery->public_id}/zips/{$zipBuild->public_id}/part-1.zip",
        'byte_size' => strlen($bytes),
    ]);

    $url = URL::temporarySignedRoute('gallery.download.part', now()->addHours(24), [
        'gallery' => $gallery->public_id,
        'zipBuildPart' => $part->public_id,
        'guestId' => $guestId,
    ]);

    return compact('gallery', 'zipBuild', 'part', 'bytes', 'url');
}

it('serves the whole object as a plain 200 when there is no Range header', function () {
    $bytes = str_repeat('x', 1000);
    ['zipBuild' => $zipBuild, 'url' => $url, 'bytes' => $bytes] = readyDownload($bytes);
    app()->instance(S3Client::class, zipPartGetObjectMock($bytes));

    $response = $this->get($url);

    $response->assertOk()
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Length', '1000');
    expect($response->streamedContent())->toBe($bytes);
});

it('returns 206 and exactly the requested slice for a Range: bytes=0-99 request', function () {
    $bytes = str_repeat('abcdefghij', 100); // 1000 bytes, predictable content
    ['url' => $url] = readyDownload($bytes);
    app()->instance(S3Client::class, zipPartGetObjectMock($bytes));

    $response = $this->withHeaders(['Range' => 'bytes=0-99'])->get($url);

    $response->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 0-99/1000')
        ->assertHeader('Content-Length', '100')
        ->assertHeader('Accept-Ranges', 'bytes');
    expect($response->streamedContent())->toBe(substr($bytes, 0, 100))
        ->and(strlen($response->streamedContent()))->toBe(100);
});

it('reassembles three resumed ranged requests into a byte-identical copy of the source', function () {
    $bytes = random_bytes(3000);
    ['url' => $url] = readyDownload($bytes);
    app()->instance(S3Client::class, zipPartGetObjectMock($bytes));

    $first = $this->withHeaders(['Range' => 'bytes=0-999'])->get($url)->assertStatus(206)->streamedContent();
    $second = $this->withHeaders(['Range' => 'bytes=1000-1999'])->get($url)->assertStatus(206)->streamedContent();
    $third = $this->withHeaders(['Range' => 'bytes=2000-2999'])->get($url)->assertStatus(206)->streamedContent();

    expect($first.$second.$third)->toBe($bytes);
});

it('responds 416 with Content-Range: bytes */total for a range past the end of the object', function () {
    $bytes = str_repeat('x', 1000);
    ['url' => $url] = readyDownload($bytes);
    app()->instance(S3Client::class, zipPartGetObjectMock($bytes));

    $this->withHeaders(['Range' => 'bytes=5000-6000'])->get($url)
        ->assertStatus(416)
        ->assertHeader('Content-Range', 'bytes */1000');
});

// ---------- the actually-bounded quantity: distinct requester-days ----------

it('counts three resumed requests from the same guest on the same day as one requester-day, while still writing three audit rows', function () {
    $bytes = str_repeat('x', 3000);
    ['url' => $url, 'zipBuild' => $zipBuild] = readyDownload($bytes, guestId: 'guest-resuming');
    app()->instance(S3Client::class, zipPartGetObjectMock($bytes));

    $this->withHeaders(['Range' => 'bytes=0-999'])->get($url)->assertStatus(206);
    $this->withHeaders(['Range' => 'bytes=1000-1999'])->get($url)->assertStatus(206);
    $this->withHeaders(['Range' => 'bytes=2000-2999'])->get($url)->assertStatus(206);

    expect(DownloadEvent::where('zip_build_id', $zipBuild->id)->count())->toBe(3)
        ->and(DownloadEvent::requesterDayCount($zipBuild))->toBe(1);
});

it('counts a second, different guest downloading the same day as a second requester-day', function () {
    $bytes = str_repeat('x', 100);
    $gallery = Gallery::factory()->create();
    $version = GalleryVersion::factory()->for($gallery)->create();
    $zipBuild = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => ZipBuildStatus::Ready]);
    $part = ZipBuildPart::factory()->for($zipBuild)->create(['byte_size' => strlen($bytes)]);
    app()->instance(S3Client::class, zipPartGetObjectMock($bytes));

    foreach (['guest-a', 'guest-b'] as $guestId) {
        $url = URL::temporarySignedRoute('gallery.download.part', now()->addHours(24), [
            'gallery' => $gallery->public_id,
            'zipBuildPart' => $part->public_id,
            'guestId' => $guestId,
        ]);
        $this->get($url)->assertOk();
    }

    expect(DownloadEvent::requesterDayCount($zipBuild))->toBe(2);
});

// ---------- signature and gate enforcement ----------

it('refuses a request whose signature has expired', function () {
    $bytes = 'x';
    $gallery = Gallery::factory()->create();
    $version = GalleryVersion::factory()->for($gallery)->create();
    $zipBuild = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => ZipBuildStatus::Ready]);
    $part = ZipBuildPart::factory()->for($zipBuild)->create(['byte_size' => 1]);

    $expiredUrl = URL::temporarySignedRoute('gallery.download.part', now()->subHour(), [
        'gallery' => $gallery->public_id,
        'zipBuildPart' => $part->public_id,
        'guestId' => 'guest-1',
    ]);

    $this->get($expiredUrl)->assertForbidden();
});

it('refuses a tampered guestId even though the rest of the URL is untouched', function () {
    $bytes = 'x';
    ['url' => $url] = readyDownload($bytes);

    $tampered = str_replace('guest-1', 'someone-else', $url);

    $this->get($tampered)->assertForbidden();
});

it('bounces a request behind an uncleared download PIN back to the gate, never reaching S3', function () {
    $bytes = 'x';
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');
    $version = GalleryVersion::factory()->for($gallery)->create();
    $zipBuild = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => ZipBuildStatus::Ready]);
    $part = ZipBuildPart::factory()->for($zipBuild)->create(['byte_size' => 1]);

    $url = URL::temporarySignedRoute('gallery.download.part', now()->addHours(24), [
        'gallery' => $gallery->public_id,
        'zipBuildPart' => $part->public_id,
        'guestId' => 'guest-1',
    ]);

    // No S3Client bound at all — a MockHandler-less call would blow up if
    // the controller ever reached it, so this doubles as "never called".
    $this->get($url)->assertRedirect("/galerien/{$gallery->public_id}/download");
});

// ---------- no storage path or presigned URL ever reaches the client ----------

it('never puts the raw S3 key, bucket name or any AWS host in a response header or body', function () {
    $bytes = str_repeat('x', 500);
    ['url' => $url, 'part' => $part] = readyDownload($bytes);
    app()->instance(S3Client::class, zipPartGetObjectMock($bytes));

    $response = $this->get($url);
    $response->assertOk();

    $headerDump = json_encode($response->headers->all());
    expect($headerDump)->not->toContain($part->key)
        ->not->toContain('amazonaws')
        ->not->toContain(config('filesystems.disks.s3.bucket'));
    expect($response->streamedContent())->not->toContain($part->key);
});
