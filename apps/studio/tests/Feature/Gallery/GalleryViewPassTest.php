<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Gallery\Support\GalleryGuestPass;
use App\Gallery\Support\GalleryViewPass;
use App\Media\Models\Asset;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    RateLimiter::clear('gallery-secret-ip:127.0.0.1');
});

function gatedGallery(string $password = 'basthorst2026'): Gallery
{
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg', 'width' => 4000, 'height' => 3000]);
    $gallery->fresh()->publish();
    $gallery->rotateViewPassword($password);

    return $gallery->fresh();
}

// The whole point: a wedding gallery is the one a photographer always locks,
// and it could not be served by the fast viewer at all.
it('opens a gated gallery on the api surface once a pass is bought', function () {
    $gallery = gatedGallery();

    test()->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();

    $pass = test()->postJson("/api/galleries/{$gallery->public_id}/passwort", ['password' => 'basthorst2026'])
        ->assertOk()
        ->json('pass');

    test()->withToken($pass)->getJson("/api/galleries/{$gallery->public_id}")->assertOk();
});

it('refuses the wrong password without saying which gallery exists', function () {
    $gallery = gatedGallery();

    test()->postJson("/api/galleries/{$gallery->public_id}/passwort", ['password' => 'falsch'])
        ->assertStatus(422);

    RateLimiter::clear('gallery-secret-ip:127.0.0.1');

    // A gallery nobody ever created answers exactly the same way.
    test()->postJson('/api/galleries/does-not-exist/passwort', ['password' => 'falsch'])
        ->assertStatus(422);
});

it('hands a pass straight out for a gallery with no password', function () {
    $gallery = Gallery::factory()->create();
    $gallery->publish();

    $pass = test()->postJson("/api/galleries/{$gallery->public_id}/passwort")->assertOk()->json('pass');

    expect($pass)->not->toBeEmpty();
});

// R17: same uniform-failure reasoning as the portal's store() — a 404
// here would let a garbage password tell a withdrawn gallery apart from
// one that never existed, exactly what G-07 closes for the latter.
it('answers a withdrawn gallery exactly as it answers one that never existed', function () {
    $gallery = gatedGallery();
    $gallery->forceFill(['unpublished_at' => now()])->save();

    test()->postJson("/api/galleries/{$gallery->public_id}/passwort", ['password' => 'irgendwas'])
        ->assertStatus(422)
        ->assertJson(['message' => __('gallery.password_wrong')]);

    RateLimiter::clear('gallery-secret-ip:127.0.0.1');

    test()->postJson('/api/galleries/nonexistent-cuid/passwort', ['password' => 'irgendwas'])
        ->assertStatus(422)
        ->assertJson(['message' => __('gallery.password_wrong')]);

    expect($gallery->fresh()->versions()->count())->toBe(1);
});

// Rotation is the answer to a link that travelled too far, and it only is
// one if the passes already handed out die with the secret.
it('kills every pass already issued when the studio rotates', function () {
    $gallery = gatedGallery();

    $pass = test()->postJson("/api/galleries/{$gallery->public_id}/passwort", ['password' => 'basthorst2026'])
        ->json('pass');

    test()->withToken($pass)->getJson("/api/galleries/{$gallery->public_id}")->assertOk();

    $gallery->rotateViewPassword('etwas-anderes');

    test()->withToken($pass)->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
});

it('refuses a pass bought for a different gallery', function () {
    $mine = gatedGallery();
    RateLimiter::clear('gallery-secret-ip:127.0.0.1');
    $theirs = gatedGallery();

    $pass = test()->postJson("/api/galleries/{$mine->public_id}/passwort", ['password' => 'basthorst2026'])
        ->json('pass');

    test()->withToken($pass)->getJson("/api/galleries/{$theirs->public_id}")->assertNotFound();
});

it('refuses a pass past its hour', function () {
    $gallery = gatedGallery();

    $pass = test()->postJson("/api/galleries/{$gallery->public_id}/passwort", ['password' => 'basthorst2026'])
        ->json('pass');

    test()->travel(GalleryViewPass::VALID_FOR_HOURS + 1)->hours();

    test()->withToken($pass)->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
});

it('refuses a forged pass', function () {
    $gallery = gatedGallery();

    $forged = "{$gallery->public_id}.{$gallery->view_password_version}.".now()->addDay()->timestamp.'.'.str_repeat('a', 64);

    test()->withToken($forged)->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
});

// The pass covers the whole viewer, not just the asset list: the bytes were
// the other half of what #227 found open.
it('covers the image bytes, the favourites and the comments too', function () {
    $gallery = gatedGallery();
    $version = $gallery->currentVersion;

    $pass = test()->postJson("/api/galleries/{$gallery->public_id}/passwort", ['password' => 'basthorst2026'])
        ->json('pass');

    test()->getJson("/api/gallery-versions/{$version->public_id}/favorites")->assertNotFound();
    test()->getJson("/api/gallery-versions/{$version->public_id}/comments")->assertNotFound();

    test()->withToken($pass)->getJson("/api/gallery-versions/{$version->public_id}/favorites")->assertOk();
    test()->withToken($pass)->getJson("/api/gallery-versions/{$version->public_id}/comments")->assertOk();
});

it('refuses a forged pass in the query as readily as in the header', function () {
    $gallery = gatedGallery();

    $forged = "{$gallery->public_id}.1.".now()->addDay()->timestamp.'.'.str_repeat('a', 64);

    test()->getJson("/api/galleries/{$gallery->public_id}?pass=".urlencode($forged))->assertNotFound();
});

it('opens the asset list from the query as well as the header', function () {
    $gallery = gatedGallery();

    $pass = test()->postJson("/api/galleries/{$gallery->public_id}/passwort", ['password' => 'basthorst2026'])
        ->json('pass');

    test()->getJson("/api/galleries/{$gallery->public_id}?pass=".urlencode($pass))->assertOk();
});

it('refuses a guest pass as a view pass', function () {
    $gallery = gatedGallery();
    [$guest] = confirmedGuest($gallery);
    // Kennung mit passendem Ziffernpräfix, so wie ein Gast sie offline erzeugt.
    $guest->forceFill(['identifier' => $gallery->view_password_version.'f00d'])->save();
    $guestPass = app(GalleryGuestPass::class)->issue($gallery, $guest);

    test()->withToken($guestPass)->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
    test()->getJson("/api/galleries/{$gallery->public_id}?pass={$guestPass}")->assertNotFound();
});

// R44: every guest reaches the api as the SSR container, so one IP's misses
// must not lock the correct password of a real gallery.
it('keeps a real gallery open after ten wrong passwords for made-up ids from one IP', function () {
    $gallery = gatedGallery();

    foreach (range(1, 10) as $i) {
        test()->postJson("/api/galleries/ghost-{$i}/passwort", ['password' => 'falsch'])->assertStatus(422);
    }

    test()->postJson("/api/galleries/{$gallery->public_id}/passwort", ['password' => 'basthorst2026'])->assertOk();
});

it('counts slug and public_id of one gallery against the same limit', function () {
    $gallery = gatedGallery();
    $gallery->update(['slug' => 'hochzeit-basthorst']);

    foreach (range(1, 15) as $i) {
        foreach (['hochzeit-basthorst', $gallery->public_id] as $key) {
            test()->withServerVariables(['REMOTE_ADDR' => "10.2.{$i}.".strlen($key)])
                ->postJson("/api/galleries/{$key}/passwort", ['password' => 'falsch'])->assertStatus(422);
        }
    }

    test()->postJson("/api/galleries/{$gallery->public_id}/passwort", ['password' => 'basthorst2026'])->assertStatus(422);
});
