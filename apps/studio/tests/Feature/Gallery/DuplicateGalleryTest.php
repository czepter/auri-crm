<?php

use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/g/a.jpg', 'original');
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff');
    $this->gallery = Gallery::factory()->create(['title' => 'Hochzeit', 'design' => ['cover' => 'geteilt', 'type' => 'serif', 'colour' => 'sand', 'size' => 'mittel', 'spacing' => 'eng', 'flow' => 'zeilen']]);
    $this->gallery->sets()->delete();
    GallerySet::factory()->create(['gallery_id' => $this->gallery->id, 'name' => 'Trauung', 'position' => 0]);
    GallerySet::factory()->create(['gallery_id' => $this->gallery->id, 'name' => 'Feier', 'position' => 1, 'sort_dir' => 'desc']);
    Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->gallery->sets()->first()->id, 'key' => 'galleries/g/a.jpg']);
    $this->gallery->rotateViewPassword('Wiese-4821');
});

it('copies sets, design, switches and pictures but no secrets', function () {
    $this->gallery->update(['comments_enabled' => false]);

    $response = $this->post("/galerien/{$this->gallery->public_id}/duplizieren");

    $copy = Gallery::query()->where('title', 'Hochzeit Kopie')->firstOrFail();
    $response->assertRedirect(route('galleries.show', $copy));

    expect($copy->id)->not->toBe($this->gallery->id)
        ->and($copy->case_id)->toBe($this->gallery->case_id)
        ->and($copy->design)->toEqual($this->gallery->fresh()->design)
        ->and($copy->comments_enabled)->toBeFalse()
        ->and($copy->sets->map(fn ($set) => [$set->name, $set->sort_dir])->all())->toBe([['Trauung', 'asc'], ['Feier', 'desc']])
        ->and($copy->assets()->count())->toBe(1)
        ->and($copy->view_password)->toBeNull()
        ->and($this->gallery->assets()->count())->toBe(1);
});

it('refuses an external gallery', function () {
    $external = Gallery::factory()->create(['is_external' => true, 'external_url' => 'https://example.test/g']);

    $this->post("/galerien/{$external->public_id}/duplizieren")->assertStatus(422);
});
