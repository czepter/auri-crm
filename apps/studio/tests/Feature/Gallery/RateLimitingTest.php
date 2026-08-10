<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryEmailVerification;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
});

// ---------- uniform failure: a wrong password looks exactly like no gallery ----------

it('gives an identical redirect, status and error for a wrong password and a nonexistent gallery', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    // Both told they came from the same neutral page — back() then redirects
    // both to the identical location, so the response bodies are compared
    // byte for byte rather than merely "both mention the same error", which
    // would still let a differing URL in the body leak which case it was.
    $wrongOnReal = test()->from('/somewhere')
        ->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'nope']);

    $onFake = test()->from('/somewhere')
        ->post('/galerien/does-not-exist/passwort', ['password' => 'nope']);

    $wrongOnReal->assertRedirect('/somewhere')
        ->assertSessionHasErrors(['password' => __('gallery.password_wrong')]);
    $onFake->assertRedirect('/somewhere')
        ->assertSessionHasErrors(['password' => __('gallery.password_wrong')]);

    expect($wrongOnReal->getStatusCode())->toBe($onFake->getStatusCode())
        ->and($wrongOnReal->getContent())->toBe($onFake->getContent());
});

it('never 404s a nonexistent gallery from the password gate — it answers with the same wrong-password redirect', function () {
    test()->post('/galerien/does-not-exist/passwort', ['password' => 'anything'])
        ->assertStatus(302)
        ->assertSessionHasErrors(['password' => __('gallery.password_wrong')]);
});

it('keeps a wrong PIN and a nonexistent gallery indistinguishable the same way', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');
    // The download PIN route sits behind the view gate; satisfy it first so
    // this compares the PIN check itself, not the redirect the gate would
    // otherwise bounce an unauthenticated request to.
    test()->post("/galerien/{$gallery->public_id}/passwort", []);

    $wrongOnReal = test()->from('/somewhere')
        ->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '0000']);

    $onFake = test()->from('/somewhere')
        ->post('/galerien/does-not-exist/download/pin', ['pin' => '0000']);

    $wrongOnReal->assertSessionHasErrors(['pin' => __('gallery.pin_wrong')]);
    $onFake->assertSessionHasErrors(['pin' => __('gallery.pin_wrong')]);

    expect($wrongOnReal->getStatusCode())->toBe($onFake->getStatusCode())
        ->and($wrongOnReal->getContent())->toBe($onFake->getContent());
});

it('takes about the same time whether the gallery exists or not', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $start = microtime(true);
    test()->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'nope']);
    $realElapsed = microtime(true) - $start;

    $start = microtime(true);
    test()->post('/galerien/does-not-exist/passwort', ['password' => 'nope']);
    $fakeElapsed = microtime(true) - $start;

    // Generous on purpose — this is measuring real wall-clock time in a test
    // process, not asserting constant-time cryptography. The point is that
    // one path never pays a categorically different cost than the other
    // (e.g. skipping the hash check entirely), not pinning a tight bound.
    // Widened from 0.25s after a loaded CI runner tripped it by ~6ms.
    expect(abs($realElapsed - $fakeElapsed))->toBeLessThan(0.6);
});

// ---------- per-IP throttling ----------

it('blocks further password attempts from an IP that exceeded its limit, even against a different gallery', function () {
    RateLimiter::clear('gallery-secret-ip:9.9.9.9');

    // Ten wrong attempts spread across ten different galleries from the same
    // IP — each gallery's own counter stays low, only the IP counter climbs.
    foreach (range(1, 10) as $i) {
        $g = Gallery::factory()->create();
        $g->rotateViewPassword('secret'.$i);

        test()->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
            ->post("/galerien/{$g->public_id}/passwort", ['password' => 'wrong'])
            ->assertSessionHasErrors('password');
    }

    // An eleventh gallery, correct password, same IP: still refused — the IP
    // limit was tripped by attempts against ten entirely different galleries.
    $eleventh = Gallery::factory()->create();
    $eleventh->rotateViewPassword('correct-one');

    test()->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
        ->post("/galerien/{$eleventh->public_id}/passwort", ['password' => 'correct-one'])
        ->assertSessionHasErrors('password');

    expect(props("/galerien/{$eleventh->public_id}")['unlocked'])->toBeFalse();
});

// ---------- per-gallery throttling ----------

it('blocks further password attempts against one gallery once its own limit is exceeded, even from different IPs', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');
    RateLimiter::clear('gallery-secret-gallery:'.$gallery->public_id);

    // Thirty wrong attempts against the same gallery, each from its own IP —
    // no single IP gets anywhere near its own limit, only the gallery's does.
    foreach (range(1, 30) as $i) {
        test()->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
            ->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'wrong'])
            ->assertSessionHasErrors('password');
    }

    // A brand new IP, correct password, same gallery: still refused — the
    // gallery's own limit was tripped by thirty different IPs, not this one.
    test()->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
        ->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'basthorst2026'])
        ->assertSessionHasErrors('password');

    expect(props("/galerien/{$gallery->public_id}")['unlocked'])->toBeFalse();
});

// ---------- the download PIN gets the same two-key treatment ----------

it('rate limits the download PIN per gallery as well as per IP', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');
    RateLimiter::clear('gallery-secret-gallery:'.$gallery->public_id);
    test()->post("/galerien/{$gallery->public_id}/passwort", []);

    foreach (range(1, 30) as $i) {
        test()->withServerVariables(['REMOTE_ADDR' => "10.1.0.{$i}"])
            ->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '0000'])
            ->assertSessionHasErrors('pin');
    }

    test()->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])
        ->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '4821'])
        ->assertSessionHasErrors('pin');

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('pin');
});

// ---------- the email-verification request endpoint ----------

it('rate limits the email verification request per gallery without ever revealing it', function () {
    Notification::fake();
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');
    test()->post("/galerien/{$gallery->public_id}/passwort", []);
    test()->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '4821']);
    RateLimiter::clear('gallery-email-gallery:'.$gallery->public_id);

    foreach (range(1, 30) as $i) {
        test()->withServerVariables(['REMOTE_ADDR' => "10.2.0.{$i}"])
            ->post("/galerien/{$gallery->public_id}/download/email", ['email' => "guest{$i}@example.test"])
            ->assertRedirect()
            ->assertSessionHas('status', __('gallery.email_link_sent'));
    }

    // Rate-limited now — the response still claims to have sent the link
    // (uniform, same as RequestClientAccess never revealing a throttle
    // either), but no new verification row backs it.
    $response = test()->withServerVariables(['REMOTE_ADDR' => '10.2.1.1'])
        ->post("/galerien/{$gallery->public_id}/download/email", ['email' => 'late@example.test']);

    $response->assertRedirect()->assertSessionHas('status', __('gallery.email_link_sent'));
    expect(GalleryEmailVerification::where('email', 'late@example.test')->count())->toBe(0);
});
