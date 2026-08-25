<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPrereleaseSignup;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

/** @return array{email: string, elapsed: int} the two fields every legitimate submission needs to clear refused(). */
function prereleasePayload(string $email): array
{
    return ['email' => $email, 'elapsed' => 5];
}

it('records a new address for a gallery in pre-release', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", prereleasePayload('Anna@Example.test'))
        ->assertStatus(202);

    $signup = GalleryPrereleaseSignup::sole();
    expect($signup->gallery_id)->toBe($gallery->id)
        // R42's "same address" call: capitals must not create a second row.
        ->and($signup->email)->toBe('anna@example.test');
});

// R41: the whole reason this test exists — a stranger walking addresses
// against this endpoint must not be able to tell an address already on the
// list from a fresh one. Compared as two independent addresses (one
// pre-seeded directly, bypassing the HTTP layer) rather than the same
// address submitted twice — R44's per-address limiter would legitimately
// (and visibly, via 429) tell those two calls apart, which is a different
// invariant from the one this test pins.
it('answers byte-identical whether the address is new or already on the list', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();
    GalleryPrereleaseSignup::query()->insertOrIgnore([
        'tenant_id' => $gallery->tenant_id, 'gallery_id' => $gallery->id,
        'email' => 'already@example.test', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $new = $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", prereleasePayload('brandnew@example.test'));
    $already = $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", prereleasePayload('already@example.test'));

    expect($already->status())->toBe($new->status())
        ->and($already->getContent())->toBe($new->getContent());

    expect(GalleryPrereleaseSignup::count())->toBe(2);
});

// A gallery not in pre-release — never entered it, already published, or
// withdrawn — has nothing to sign up for. The endpoint answers exactly as
// it does for a real signup rather than a different shape, or the response
// itself would tell a caller which state a gallery is in.
it('answers the same 202 for a gallery not in pre-release, and records nothing', function () {
    $gallery = Gallery::factory()->create();

    $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", prereleasePayload('anna@example.test'))
        ->assertStatus(202);

    expect(GalleryPrereleaseSignup::count())->toBe(0);
});

it('answers the same 202 for an unknown gallery id', function () {
    $this->postJson('/api/galleries/nonexistent-cuid/benachrichtigung', prereleasePayload('anna@example.test'))
        ->assertStatus(202);
});

it('still validates the address itself', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", prereleasePayload('not-an-email'))
        ->assertStatus(422);

    expect(GalleryPrereleaseSignup::count())->toBe(0);
});

// ---------- R44 (Critical fix round 1): honest refusal, honeypot, timing floor ----------

it('refuses a submission that filled the honeypot, answering 429 rather than confirming it', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", [
        ...prereleasePayload('bot@example.test'),
        'website' => 'https://spam.example',
    ])->assertStatus(429);

    expect(GalleryPrereleaseSignup::count())->toBe(0);
});

it('refuses a submission completed faster than a human reads the page', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", [
        'email' => 'fast@example.test',
        'elapsed' => 1,
    ])->assertStatus(429);

    expect(GalleryPrereleaseSignup::count())->toBe(0);
});

it('accepts a submission with no elapsed at all — the no-JavaScript, first-ever-render case', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", ['email' => 'nojs@example.test'])
        ->assertStatus(202);

    expect(GalleryPrereleaseSignup::count())->toBe(1);
});

// R44's central ruling, stated as a test: unlike PublicInquiryController,
// a refusal here is NOT the same 202 an acceptance gets.
it('refuses the same address resubmitted inside the window — honestly, not as a silent 202', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $first = $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", prereleasePayload('repeat@example.test'));
    $second = $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", prereleasePayload('repeat@example.test'));

    $first->assertStatus(202);
    $second->assertStatus(429);
    expect($second->status())->not->toBe($first->status());

    expect(GalleryPrereleaseSignup::count())->toBe(1);
});

it('blocks further signups against one gallery once its own limit is exceeded, even for different addresses', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();
    RateLimiter::clear('gallery-prerelease-gallery:'.$gallery->public_id);

    foreach (range(1, 30) as $i) {
        $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", prereleasePayload("guest{$i}@example.test"))
            ->assertStatus(202);
    }

    $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", prereleasePayload('guest31@example.test'))
        ->assertStatus(429);

    expect(GalleryPrereleaseSignup::count())->toBe(30);
});

// R44's Critical fix, pinned directly: every one of these requests arrives
// from the test client's own default IP, exactly as every real guest
// arrives from the Astro SSR process's IP — if a per-IP counter still
// existed here, the eleventh of these (any earlier PER_IP budget) would
// 429 despite being a distinct gallery and a distinct address both.
it('never throttles on the caller\'s IP — every guest arrives from the same SSR process', function () {
    foreach (range(1, 15) as $i) {
        $gallery = Gallery::factory()->create();
        $gallery->forceFill(['prerelease_at' => now()])->save();

        $this->postJson("/api/galleries/{$gallery->public_id}/benachrichtigung", prereleasePayload("guest{$i}@example.test"))
            ->assertStatus(202);
    }

    expect(GalleryPrereleaseSignup::count())->toBe(15);
});
