<?php

use App\Gallery\Models\GalleryVersion;
use App\Media\Actions\RequestZipBuild;
use App\Media\Enums\ZipBuildScope;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Jobs\BuildZip;
use App\Media\Models\ZipBuild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// #59's own acceptance criterion: "a second request for the same (version,
// scope, size) reuses the built archive." zip_builds' own unique index
// (tenant, gallery_version_id, scope, size) is the actual dedupe mechanism
// — these prove App\Media\Actions\RequestZipBuild finds-or-creates against
// it correctly, and only dispatches App\Media\Jobs\BuildZip when there is
// genuinely new work to do.

it('creates a Building row and dispatches BuildZip onto media.zip for a brand new triple', function () {
    Queue::fake();
    $version = GalleryVersion::factory()->create();

    $zipBuild = app(RequestZipBuild::class)($version, ZipBuildScope::All, ZipBuildSize::Original);

    expect($zipBuild->status)->toBe(ZipBuildStatus::Building)
        ->and($zipBuild->gallery_version_id)->toBe($version->id)
        ->and($zipBuild->scope)->toBe(ZipBuildScope::All)
        ->and($zipBuild->size)->toBe(ZipBuildSize::Original)
        ->and(ZipBuild::count())->toBe(1);

    Queue::assertPushedOn('media.zip', BuildZip::class,
        fn (BuildZip $job) => $job->zipBuild->is($zipBuild));
});

it('reuses an already-Ready build for the same triple and never dispatches a second job', function () {
    Queue::fake();
    $version = GalleryVersion::factory()->create();
    $existing = ZipBuild::factory()->for($version, 'galleryVersion')->create([
        'scope' => ZipBuildScope::All,
        'size' => ZipBuildSize::Original,
        'status' => ZipBuildStatus::Ready,
    ]);

    $zipBuild = app(RequestZipBuild::class)($version, ZipBuildScope::All, ZipBuildSize::Original);

    expect($zipBuild->id)->toBe($existing->id)
        ->and($zipBuild->status)->toBe(ZipBuildStatus::Ready)
        ->and(ZipBuild::count())->toBe(1);

    Queue::assertNothingPushed();
});

it('does not dispatch a second job while a build for the same triple is still in flight', function () {
    Queue::fake();
    $version = GalleryVersion::factory()->create();
    $existing = ZipBuild::factory()->for($version, 'galleryVersion')->create([
        'scope' => ZipBuildScope::All,
        'size' => ZipBuildSize::Original,
        'status' => ZipBuildStatus::Building,
    ]);

    $zipBuild = app(RequestZipBuild::class)($version, ZipBuildScope::All, ZipBuildSize::Original);

    expect($zipBuild->id)->toBe($existing->id)
        ->and(ZipBuild::count())->toBe(1);

    Queue::assertNothingPushed();
});

it('retries a Failed build in place — same row, flipped back to Building, a fresh job dispatched', function () {
    Queue::fake();
    $version = GalleryVersion::factory()->create();
    $existing = ZipBuild::factory()->for($version, 'galleryVersion')->create([
        'scope' => ZipBuildScope::All,
        'size' => ZipBuildSize::Original,
        'status' => ZipBuildStatus::Failed,
        'error' => 'S3 timed out',
    ]);

    $zipBuild = app(RequestZipBuild::class)($version, ZipBuildScope::All, ZipBuildSize::Original);

    expect($zipBuild->id)->toBe($existing->id)
        ->and($zipBuild->status)->toBe(ZipBuildStatus::Building)
        ->and($zipBuild->error)->toBeNull()
        ->and(ZipBuild::count())->toBe(1);

    Queue::assertPushedOn('media.zip', BuildZip::class,
        fn (BuildZip $job) => $job->zipBuild->is($zipBuild));
});

it('treats a different size for the same version and scope as its own triple, not a dedupe hit', function () {
    Queue::fake();
    $version = GalleryVersion::factory()->create();
    ZipBuild::factory()->for($version, 'galleryVersion')->create([
        'scope' => ZipBuildScope::All,
        'size' => ZipBuildSize::Original,
        'status' => ZipBuildStatus::Ready,
    ]);

    $zipBuild = app(RequestZipBuild::class)($version, ZipBuildScope::All, ZipBuildSize::Web);

    expect($zipBuild->status)->toBe(ZipBuildStatus::Building)
        ->and($zipBuild->size)->toBe(ZipBuildSize::Web)
        ->and(ZipBuild::count())->toBe(2);
});
