<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPrereleaseSignup;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

it('records a new address for a gallery in pre-release', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", ['email' => 'Anna@Example.test'])
        ->assertStatus(202);

    $signup = GalleryPrereleaseSignup::sole();
    expect($signup->gallery_id)->toBe($gallery->id)
        // R42's "same address" call: capitals must not create a second row.
        ->and($signup->email)->toBe('anna@example.test');
});

// R41: the whole reason this test exists — a stranger walking addresses
// against this endpoint must not be able to tell an address already on the
// list from a fresh one.
it('answers byte-identical whether the address is new or already on the list', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $first = $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", ['email' => 'anna@example.test']);
    $second = $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", ['email' => 'ANNA@EXAMPLE.TEST']);

    expect($second->status())->toBe($first->status())
        ->and($second->getContent())->toBe($first->getContent());

    expect(GalleryPrereleaseSignup::count())->toBe(1);
});

// A gallery not in pre-release — never entered it, already published, or
// withdrawn — has nothing to sign up for. The endpoint answers exactly as
// it does for a real signup rather than a different shape, or the response
// itself would tell a caller which state a gallery is in.
it('answers the same 202 for a gallery not in pre-release, and records nothing', function () {
    $gallery = Gallery::factory()->create();

    $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", ['email' => 'anna@example.test'])
        ->assertStatus(202);

    expect(GalleryPrereleaseSignup::count())->toBe(0);
});

it('answers the same 202 for an unknown gallery id', function () {
    $this->postJson('/api/galleries/nonexistent-cuid/benachrichtigung', ['email' => 'anna@example.test'])
        ->assertStatus(202);
});

it('still validates the address itself', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", ['email' => 'not-an-email'])
        ->assertStatus(422);

    expect(GalleryPrereleaseSignup::count())->toBe(0);
});
