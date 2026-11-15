<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\ProjectType;
use App\Gallery\Actions\CreateGallery;
use App\Gallery\Actions\DuplicateGallery;
use App\Gallery\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('copies the case type once at creation', function () {
    $case = ClientCase::factory()->create(['type' => 'wedding']);

    $gallery = app(CreateGallery::class)(['title' => 'Galerie'], $case, null, null);
    $case->update(['type' => 'portrait']);

    expect($gallery->fresh()->project_type_id)->toBe(ProjectType::query()->where('key', 'wedding')->value('id'));
});

it('keeps the category on a duplicate and starts it unlisted and unpinned', function () {
    $type = ProjectType::query()->where('key', 'portrait')->firstOrFail();
    $source = Gallery::factory()->create(['project_type_id' => $type->id, 'listed_on_home' => true, 'home_pinned' => true]);

    $copy = app(DuplicateGallery::class)($source, null);

    expect($copy->project_type_id)->toBe($type->id)
        ->and($copy->listed_on_home)->toBeFalse()
        ->and($copy->home_pinned)->toBeFalse();
});

it('backfills the category from the case type and leaves galleries without a case empty', function () {
    $case = ClientCase::factory()->create(['type' => 'wedding']);
    $withCase = Gallery::factory()->create(['case_id' => $case->id]);
    $without = Gallery::factory()->create(['case_id' => null]);
    DB::table('galleries')->update(['project_type_id' => null]);

    (require database_path('migrations/2026_11_15_100100_backfill_gallery_category.php'))->up();

    expect($withCase->fresh()->project_type_id)->toBe(ProjectType::query()->where('key', 'wedding')->value('id'))
        ->and($without->fresh()->project_type_id)->toBeNull();
});
