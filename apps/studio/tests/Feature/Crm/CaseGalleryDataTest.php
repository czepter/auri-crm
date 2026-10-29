<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Crm\Screens\CaseGalleryData;
use App\Gallery\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reads a draft gallery as not published and not in the portal', function () {
    $project = ClientCase::factory()->create();
    $shoot = Shoot::factory()->for($project, 'case')->on('2026-09-03')->create();
    $gallery = Gallery::factory()->for($project, 'case')->for($shoot)->create(['title' => 'Trauung']);

    $row = CaseGalleryData::fromModel($gallery->fresh());

    expect($row->state)->toBe('draft')
        ->and($row->shootId)->toBe($shoot->public_id)
        ->and($row->version)->toBeNull()
        ->and($row->assetCount)->toBe(0)
        ->and($row->inPortal)->toBeFalse();
});

it('reads a published gallery as in the portal, and a withdrawn one as out of it', function () {
    $gallery = Gallery::factory()->create();
    $gallery->publish();

    expect(CaseGalleryData::fromModel($gallery->fresh())->state)->toBe('published')
        ->and(CaseGalleryData::fromModel($gallery->fresh())->inPortal)->toBeTrue()
        ->and(CaseGalleryData::fromModel($gallery->fresh())->version)->toBe(1);

    $gallery->forceFill(['unpublished_at' => now()])->save();

    expect(CaseGalleryData::fromModel($gallery->fresh())->state)->toBe('withdrawn')
        ->and(CaseGalleryData::fromModel($gallery->fresh())->inPortal)->toBeFalse();
});
