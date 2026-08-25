<?php

use App\Gallery\Actions\PublishGallery;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Gallery\Support\GalleryPreviewPass;
use App\Media\Models\Asset;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

it('404s a gallery nobody has published yet', function () {
    $gallery = Gallery::factory()->create();

    $this->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
});

it('404s an external gallery, which owns no version to serve', function () {
    $gallery = Gallery::factory()->create(['is_external' => true, 'external_url' => 'https://legacy.example/g/1']);

    $this->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
});

// G-14: EnsureGalleryPass must close this before ever asking whether a
// password is required — a withdrawn gallery with no password would
// otherwise sail straight through to GalleryReadController.
it('404s a withdrawn gallery on the api surface, even with no password set', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg']);
    $gallery->fresh()->publish();
    $gallery->forceFill(['unpublished_at' => now()])->save();

    $this->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();

    expect($gallery->fresh()->versions()->count())->toBe(1);
});

it('404s an unknown public_id rather than leaking whether it ever existed', function () {
    $this->getJson('/api/galleries/nonexistent-cuid')->assertNotFound();
});

it('serves the currently-published version, not the draft, as no-store', function () {
    $gallery = Gallery::factory()->create(['title' => 'Draft title']);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    $asset = Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg', 'filename' => 'a.jpg', 'width' => 4000, 'height' => 3000]);
    $version = $gallery->fresh()->publish();

    // Edit-after-publish must not move what this endpoint returns.
    $gallery->update(['title' => 'Edited after publish']);
    $asset->update(['removed_from_draft_at' => now()]);

    $response = $this->getJson("/api/galleries/{$gallery->public_id}")->assertOk();

    $response->assertJson([
        'gallery' => [
            'public_id' => $gallery->public_id,
            'title' => 'Draft title',
            'version' => [
                'public_id' => $version->public_id,
                'version' => 1,
                'assets' => [
                    ['key' => 'galleries/g1/a.jpg', 'width' => 4000, 'height' => 3000],
                ],
            ],
        ],
    ]);

    // Symfony's ResponseHeaderBag normalises Cache-Control (adds `private`
    // when nothing marks the response public) — the directive that matters
    // here is `no-store` itself, not the exact string it ends up composed
    // into.
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('serves the latest version after a republish', function () {
    $gallery = Gallery::factory()->create(['title' => 'v1']);
    $gallery->publish();

    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/b.jpg', 'filename' => 'b.jpg', 'width' => 100, 'height' => 100]);
    $gallery->update(['title' => 'v2']);
    $v2 = $gallery->fresh()->publish();

    $this->getJson("/api/galleries/{$gallery->public_id}")
        ->assertOk()
        ->assertJson(['gallery' => ['title' => 'v2', 'version' => ['public_id' => $v2->public_id, 'version' => 2]]]);
});

// R31/spec decision 26: the whole point of the preview pass — a gallery
// nobody has published yet, the exact case the button above 404s on, opens
// once the request carries a pass for it.
it('serves the draft instead of 404ing once a valid preview pass is presented', function () {
    $gallery = Gallery::factory()->create(['title' => 'Entwurf viola-chris']);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg', 'width' => 4000, 'height' => 3000]);

    $token = app(GalleryPreviewPass::class)->issue($gallery, GalleryPreviewPass::BUTTON_MINUTES);

    $this->getJson("/api/galleries/{$gallery->public_id}?preview=".urlencode($token))
        ->assertOk()
        ->assertJson([
            'gallery' => [
                'public_id' => $gallery->public_id,
                'title' => 'Entwurf viola-chris',
                'draft' => true,
                'version' => [
                    'public_id' => null,
                    'assets' => [
                        ['key' => 'galleries/g1/a.jpg', 'width' => 4000, 'height' => 3000],
                    ],
                ],
            ],
        ]);
});

// The exact production case this preview exists for: 22 draft pictures, no
// published version — must answer 200 with an empty grid, never a 404,
// once the pictures do exist there is nothing structurally different about
// an empty one.
it('answers a picture-less draft with an empty asset list, not a 404', function () {
    $gallery = Gallery::factory()->create();
    $token = app(GalleryPreviewPass::class)->issue($gallery, GalleryPreviewPass::BUTTON_MINUTES);

    $this->getJson("/api/galleries/{$gallery->public_id}?preview=".urlencode($token))
        ->assertOk()
        ->assertJson(['gallery' => ['draft' => true, 'version' => ['assets' => []]]]);
});

it('404s an external gallery even with a valid preview pass, same as the published path', function () {
    $gallery = Gallery::factory()->create(['is_external' => true, 'external_url' => 'https://legacy.example/g/1']);
    $token = app(GalleryPreviewPass::class)->issue($gallery, GalleryPreviewPass::BUTTON_MINUTES);

    $this->getJson("/api/galleries/{$gallery->public_id}?preview=".urlencode($token))->assertNotFound();
});

it('falls back to the published version when the preview pass is stale, not to the draft', function () {
    $gallery = Gallery::factory()->create(['title' => 'v1']);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg']);
    $version = $gallery->fresh()->publish();

    $token = app(GalleryPreviewPass::class)->issue($gallery, GalleryPreviewPass::BUTTON_MINUTES);
    $gallery->rotatePreviewPass();

    $this->getJson("/api/galleries/{$gallery->public_id}?preview=".urlencode($token))
        ->assertOk()
        ->assertJson(['gallery' => ['draft' => false, 'version' => ['public_id' => $version->public_id]]]);
});

// A guest carrying nothing must see byte-identical behaviour to before
// GalleryPreviewPass existed — the 404 on an unpublished gallery, unchanged.
it('still 404s a gallery nobody has published yet when no preview pass is presented', function () {
    $gallery = Gallery::factory()->create();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => GallerySet::where('gallery_id', $gallery->id)->first()->id]);

    $this->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
});

// ---------- R39/R40/R43: pre-release ----------

it('answers a pre-release gallery with only its title and public_id, no version', function () {
    $gallery = Gallery::factory()->create(['title' => 'Viola + Chris']);
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $response = $this->getJson("/api/galleries/{$gallery->public_id}")
        ->assertOk()
        ->assertExactJson([
            'gallery' => [
                'public_id' => $gallery->public_id,
                'title' => 'Viola + Chris',
                'prerelease' => true,
            ],
        ]);

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('answers a pre-release gallery the same with a password set, since R40 needs none', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('geheim123');
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $this->getJson("/api/galleries/{$gallery->public_id}")
        ->assertOk()
        ->assertJson(['gallery' => ['prerelease' => true]]);
});

it('404s a pre-release gallery that was withdrawn — closed beats being prepared', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now(), 'unpublished_at' => now()])->save();

    $this->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
});

it('stops answering pre-release once the gallery is published', function () {
    $gallery = Gallery::factory()->create(['title' => 'v1']);
    $gallery->forceFill(['prerelease_at' => now()])->save();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/a.jpg']);
    // PublishGallery, not Gallery::publish() directly — clearing
    // prerelease_at and notifying signups is the action's job, the same
    // way clearing unpublished_at already is (see PublishGallery itself).
    app(PublishGallery::class)($gallery->fresh());

    $this->getJson("/api/galleries/{$gallery->public_id}")
        ->assertOk()
        ->assertJson(['gallery' => ['version' => ['version' => 1]]]);

    expect($gallery->fresh()->prerelease_at)->toBeNull();
});

// R43: a preview pass is an explicit credential — issued deliberately,
// pinned to one gallery, expiring, revocable — and outranks pre-release,
// which is only that gallery's public default. The token holder is
// already entitled to the draft; pre-release was never protecting
// anything from them.
it('opens the draft for a valid preview pass even while pre-release is active', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/a.jpg']);
    $token = app(GalleryPreviewPass::class)->issue($gallery, GalleryPreviewPass::BUTTON_MINUTES);

    $this->getJson("/api/galleries/{$gallery->public_id}?preview=".urlencode($token))
        ->assertOk()
        ->assertJson(['gallery' => ['draft' => true, 'version' => ['assets' => [
            ['key' => 'galleries/g/a.jpg'],
        ]]]]);
});

// The swap above must not open the public path: the same gallery, without
// a token, still answers the teaser only — no assets, no draft flag.
it('still answers only the pre-release payload for the same gallery without a token', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/a.jpg']);

    $this->getJson("/api/galleries/{$gallery->public_id}")
        ->assertOk()
        ->assertExactJson([
            'gallery' => [
                'public_id' => $gallery->public_id,
                'title' => $gallery->title,
                'prerelease' => true,
            ],
        ]);
});

// Fix round 1, I3: isInPrerelease() checks currentVersion === null
// specifically so a stale prerelease_at can never resurrect the teaser
// once a version exists — this bypasses PublishGallery entirely (which
// would have cleared the flag itself) to pin the model's own guard rather
// than the action that happens to also do the clearing.
it('never answers pre-release once a version exists, even with a stale prerelease_at', function () {
    $gallery = Gallery::factory()->create(['title' => 'v1']);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/a.jpg']);
    $gallery->fresh()->publish();
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $this->getJson("/api/galleries/{$gallery->public_id}")
        ->assertOk()
        ->assertJson(['gallery' => ['version' => ['version' => 1]]]);
});

// Fix round 1: the state matrix's two missing preview-pass cases. Both
// read as "no valid pass at all" to GalleryPreviewPass::opens(), so both
// must fall to the pre-release rung exactly like a request with no token.
it('answers pre-release, not the version fallback, for a revoked preview pass', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();
    $token = app(GalleryPreviewPass::class)->issue($gallery, GalleryPreviewPass::BUTTON_MINUTES);
    $gallery->rotatePreviewPass();

    $this->getJson("/api/galleries/{$gallery->public_id}?preview=".urlencode($token))
        ->assertOk()
        ->assertJson(['gallery' => ['prerelease' => true]]);
});

it('answers pre-release for an expired preview pass', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['prerelease_at' => now()])->save();
    $token = app(GalleryPreviewPass::class)->issue($gallery, -1);

    $this->getJson("/api/galleries/{$gallery->public_id}?preview=".urlencode($token))
        ->assertOk()
        ->assertJson(['gallery' => ['prerelease' => true]]);
});

// Fix round 1, Minor: isInPrerelease() excludes is_external — an external
// gallery owns no draft, so the flag (which the admin controller already
// refuses to set on one) must never turn its 404 into a teaser either.
it('never answers pre-release for an external gallery, even with the flag set', function () {
    $gallery = Gallery::factory()->create(['is_external' => true, 'external_url' => 'https://legacy.example/g/1']);
    $gallery->forceFill(['prerelease_at' => now()])->save();

    $this->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
});
