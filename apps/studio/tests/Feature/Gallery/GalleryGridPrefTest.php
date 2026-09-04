<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryGridPref;
use App\Gallery\Models\GallerySet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('remembers the density per user and set', function () {
    $other = User::factory()->withMfa()->create();
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    $this->patch("/galerien/{$gallery->public_id}/sets/{$set->public_id}/dichte", ['density' => 'large'])
        ->assertRedirect();

    expect(collect($this->get("/galerien/{$gallery->public_id}")->viewData('page')['props']['sets'])
        ->firstWhere('id', $set->public_id)['density'])->toBe('large')
        ->and(collect($this->actingAs($other, 'staff')
            ->get("/galerien/{$gallery->public_id}")->viewData('page')['props']['sets'])
            ->firstWhere('id', $set->public_id)['density'])->toBe('medium');
});

it('rejects a density outside small, medium, large', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    $this->patch("/galerien/{$gallery->public_id}/sets/{$set->public_id}/dichte", ['density' => 'huge'])
        ->assertSessionHasErrors('density');
});

it('404s when the set belongs to another gallery', function () {
    $gallery = Gallery::factory()->create();
    $other = Gallery::factory()->create();
    $foreignSet = GallerySet::where('gallery_id', $other->id)->first();

    $this->patch("/galerien/{$gallery->public_id}/sets/{$foreignSet->public_id}/dichte", ['density' => 'large'])
        ->assertNotFound();
});

it('does not duplicate a row on a double submit', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    $this->patch("/galerien/{$gallery->public_id}/sets/{$set->public_id}/dichte", ['density' => 'small']);
    $this->patch("/galerien/{$gallery->public_id}/sets/{$set->public_id}/dichte", ['density' => 'large']);

    expect(GalleryGridPref::query()->where('user_id', $this->staff->id)
        ->where('gallery_set_id', $set->id)->count())->toBe(1);
});

it('drops the preference row when its set is deleted', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    $other = $gallery->sets()->create(['name' => 'Zweites Fach', 'position' => 1]);

    $this->patch("/galerien/{$gallery->public_id}/sets/{$set->public_id}/dichte", ['density' => 'large']);

    $this->delete("/galerien/{$gallery->public_id}/sets/{$set->public_id}", ['target_set_id' => $other->public_id]);

    expect(GalleryGridPref::query()->where('gallery_set_id', $set->id)->exists())->toBeFalse();
});
