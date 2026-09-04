<?php

use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Gallery\Support\GalleryPreviewPass;
use App\Media\Models\Asset;
use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
    $this->gallery = Gallery::factory()->create();
});

it('mints a short-lived preview pass for the head button', function () {
    $url = $this->post("/galerien/{$this->gallery->public_id}/vorschau")
        ->assertOk()
        ->json('url');

    expect($url)->toStartWith($this->gallery->publicUrl().'?preview=');

    $token = explode('preview=', $url)[1];
    expect(app(GalleryPreviewPass::class)->opens($this->gallery, $token))->toBeTrue();
});

it('refuses the head button for a role without manageMedia', function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Assistant]), 'staff');

    $this->post("/galerien/{$this->gallery->public_id}/vorschau")->assertForbidden();
});

it('refuses "Vorschaulink erzeugen" for a role without publish', function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::SecondShooter]), 'staff');

    $this->post("/galerien/{$this->gallery->public_id}/vorschau-link")->assertForbidden();
});

// R31: one shared counter is the whole of what revoking a link means — a
// fresh link kills every pass minted before it, the short button pass
// included if one happened to still be open.
it('revokes every earlier preview pass when a new link is generated', function () {
    $buttonUrl = $this->post("/galerien/{$this->gallery->public_id}/vorschau")->json('url');
    $buttonToken = explode('preview=', $buttonUrl)[1];

    $this->post("/galerien/{$this->gallery->public_id}/vorschau-link")->assertOk();

    expect(app(GalleryPreviewPass::class)->opens($this->gallery->fresh(), $buttonToken))->toBeFalse();
});

it('opens a revoked preview link exactly as a request carrying no pass does', function () {
    $url = $this->post("/galerien/{$this->gallery->public_id}/vorschau-link")->json('url');
    $token = explode('preview=', $url)[1];
    // rotatePreviewPass() already ran once inside link() above — reload
    // before rotating again, or this stale in-memory copy would bump the
    // same version the controller already bumped to, landing back on the
    // very version the token was minted against.
    $this->gallery->fresh()->rotatePreviewPass();

    useSurface(Surface::API);

    // No published version either — the unauthenticated 404 a plain guest gets.
    $this->getJson("/api/galleries/{$this->gallery->public_id}?preview=".urlencode($token))
        ->assertNotFound();
});

it('serves draft image bytes to a request carrying a valid preview pass', function () {
    useSurface(Surface::API);
    Storage::fake('s3');
    $set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg']);
    $image = imagecreatetruecolor(200, 100);
    ob_start();
    imagejpeg($image);
    Storage::disk('s3')->put($asset->key, ob_get_clean());

    $token = app(GalleryPreviewPass::class)->issue($this->gallery, GalleryPreviewPass::BUTTON_MINUTES);

    $this->get("/api/galleries/{$this->gallery->public_id}/entwurf-bilder/{$asset->key}?w=400&fmt=jpeg&preview=".urlencode($token))
        ->assertOk();
});

it('refuses draft image bytes without a valid preview pass', function () {
    useSurface(Surface::API);
    Storage::fake('s3');
    $set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg']);
    Storage::disk('s3')->put($asset->key, 'irrelevant');

    $this->get("/api/galleries/{$this->gallery->public_id}/entwurf-bilder/{$asset->key}?w=400&fmt=jpeg")
        ->assertNotFound();
});

it('refuses another gallery\'s asset on the draft image route, even with a valid pass for this one', function () {
    useSurface(Surface::API);
    Storage::fake('s3');
    $foreign = Asset::factory()->create(['key' => 'galleries/other/a.jpg']);
    Storage::disk('s3')->put($foreign->key, 'irrelevant');

    $token = app(GalleryPreviewPass::class)->issue($this->gallery, GalleryPreviewPass::BUTTON_MINUTES);

    $this->get("/api/galleries/{$this->gallery->public_id}/entwurf-bilder/{$foreign->key}?w=400&fmt=jpeg&preview=".urlencode($token))
        ->assertNotFound();
});

// R35 (fix round 1, Critical 1): the defect that shipped — EnsureGalleryPass
// treated a valid preview pass as satisfying its whole gate, so a 30-day
// preview link pasted onto a *published*, password-protected gallery's
// other routes read and wrote guest comments/favorites and fetched version
// images, without the password. The pass is scoped to two routes
// (GalleryReadController, GalleryDraftImageController, both of which check
// it themselves) and must reach nothing else — not via the query string, on
// any route in the gallery.pass group.
it('never lets a valid preview pass open a password-gated gallery\'s comments, favorites or version images', function () {
    useSurface(Surface::API);

    $set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg']);
    $version = $this->gallery->fresh()->publish();
    $this->gallery->rotateViewPassword('geheimnis');

    $token = app(GalleryPreviewPass::class)->issue($this->gallery->fresh(), GalleryPreviewPass::BUTTON_MINUTES);
    $q = '?preview='.urlencode($token);

    $this->getJson("/api/gallery-versions/{$version->public_id}/images/galleries/g1/a.jpg{$q}")->assertNotFound();
    $this->postJson("/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg{$q}")->assertNotFound();
    $this->postJson("/api/gallery-versions/{$version->public_id}/comments{$q}", [])->assertNotFound();
});

// Same boundary, the other closed door: G-14 withdrawal. A preview pass
// proving the studio meant to show the *draft* is not a reason to reopen a
// withdrawn gallery's *published* routes.
it('never lets a valid preview pass open a withdrawn gallery\'s comments, favorites or version images', function () {
    useSurface(Surface::API);

    $set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg']);
    $version = $this->gallery->fresh()->publish();
    $this->gallery->forceFill(['unpublished_at' => now()])->save();

    $token = app(GalleryPreviewPass::class)->issue($this->gallery->fresh(), GalleryPreviewPass::BUTTON_MINUTES);
    $q = '?preview='.urlencode($token);

    $this->getJson("/api/gallery-versions/{$version->public_id}/images/galleries/g1/a.jpg{$q}")->assertNotFound();
    $this->postJson("/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg{$q}")->assertNotFound();
    $this->postJson("/api/gallery-versions/{$version->public_id}/comments{$q}", [])->assertNotFound();
});

// The gallery binding: opens() carries public_id inside the signed
// payload, so a token minted for one gallery must refuse another's draft —
// and refuse it exactly as an absent token would (404, not a distinguishable
// error), on both routes that honour a preview pass at all.
it('refuses a preview pass minted for a different gallery, on both routes that honour one', function () {
    useSurface(Surface::API);

    $other = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $other->id)->first();
    $asset = Asset::factory()->create(['gallery_id' => $other->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g2/a.jpg']);

    $tokenForThisGallery = app(GalleryPreviewPass::class)->issue($this->gallery, GalleryPreviewPass::BUTTON_MINUTES);
    $q = '?preview='.urlencode($tokenForThisGallery);

    $withToken = $this->getJson("/api/galleries/{$other->public_id}{$q}");
    $withoutToken = $this->getJson("/api/galleries/{$other->public_id}");
    expect($withToken->status())->toBe($withoutToken->status())->and($withToken->status())->toBe(404);

    $imageWithToken = $this->get("/api/galleries/{$other->public_id}/entwurf-bilder/{$asset->key}?w=400&fmt=jpeg{$q}");
    $imageWithoutToken = $this->get("/api/galleries/{$other->public_id}/entwurf-bilder/{$asset->key}?w=400&fmt=jpeg");
    expect($imageWithToken->status())->toBe($imageWithoutToken->status())->and($imageWithToken->status())->toBe(404);
});

// Minor (fix round 1): `?preview[]=x` reaches opens(?string) as a raw
// array — explode() on that fatals into a 500, breaking the uniform-failure
// rule the rest of this stage worked to establish. Both routes that read
// the preview query parameter must answer the same 404 an absent or
// malformed token gets, never a 500.
it('answers an array preview parameter with a 404, not a 500', function () {
    useSurface(Surface::API);

    $this->getJson("/api/galleries/{$this->gallery->public_id}?preview[]=x")->assertNotFound();

    Storage::fake('s3');
    $set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg']);
    Storage::disk('s3')->put($asset->key, 'irrelevant');

    $this->get("/api/galleries/{$this->gallery->public_id}/entwurf-bilder/{$asset->key}?w=400&fmt=jpeg&preview[]=x")
        ->assertNotFound();
});

// R37 (fix round 2, user ruling): the whole point of a preview link is to
// reach someone before the gallery is published — often before there is
// any reason to know its password, since a studio frequently sets one at
// creation. A preview pass must open the draft on its own, view password
// or not, and R35's boundary must hold at the very same time: it opens
// reading the draft and nothing else on that same gallery's published,
// password-protected side.
it('opens the draft with a preview pass alone on a gallery that also has a view password, and nothing else', function () {
    useSurface(Surface::API);

    $this->gallery->rotateViewPassword('geheimnis');
    $set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg']);
    $version = $this->gallery->fresh()->publish();

    $token = app(GalleryPreviewPass::class)->issue($this->gallery->fresh(), GalleryPreviewPass::BUTTON_MINUTES);
    $q = '?preview='.urlencode($token);

    // The pair that proves the boundary is about capability, not the
    // password: the very same token that opens the draft read below must
    // still refuse every version-scoped route on this same gallery.
    $this->getJson("/api/galleries/{$this->gallery->public_id}{$q}")
        ->assertOk()
        ->assertJson(['gallery' => ['draft' => true]]);

    $this->getJson("/api/gallery-versions/{$version->public_id}/images/galleries/g1/a.jpg{$q}")->assertNotFound();
    $this->postJson("/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg{$q}")->assertNotFound();
    $this->postJson("/api/gallery-versions/{$version->public_id}/comments{$q}", [])->assertNotFound();

    // The guest path itself, byte-for-byte: no token and no pass against
    // this same password-protected gallery is still refused exactly as it
    // is without any of this stage's work.
    $this->getJson("/api/galleries/{$this->gallery->public_id}")->assertNotFound();
});

// R38: withdrawal is the one gesture that means "stop showing this to
// people outside" — a preview link already sitting in an inbox must die
// with it, on both routes that ever honoured the token at all.
it('refuses an outstanding preview token on both routes once the gallery is withdrawn', function () {
    Storage::fake('s3');
    $set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg']);
    $this->gallery->fresh()->publish();

    $token = app(GalleryPreviewPass::class)->issue($this->gallery->fresh(), GalleryPreviewPass::BUTTON_MINUTES);
    $q = '?preview='.urlencode($token);

    $this->delete("/galerien/{$this->gallery->public_id}/veroeffentlichung")->assertRedirect();

    useSurface(Surface::API);

    $this->getJson("/api/galleries/{$this->gallery->public_id}{$q}")->assertNotFound();
    $this->get("/api/galleries/{$this->gallery->public_id}/entwurf-bilder/{$asset->key}?w=400&fmt=jpeg{$q}")->assertNotFound();
});
