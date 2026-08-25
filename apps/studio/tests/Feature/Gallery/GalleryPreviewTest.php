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
