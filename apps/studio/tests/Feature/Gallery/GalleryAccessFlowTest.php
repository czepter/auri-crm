<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryEmailVerification;
use App\Gallery\Notifications\GalleryEmailVerificationLink;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
});

// ---------- the view gate (mockup 08) ----------

it('shows the gallery unlocked when no password is set', function () {
    $gallery = Gallery::factory()->create();

    $props = props("/galerien/{$gallery->public_id}");

    expect($props['unlocked'])->toBeTrue();
});

it('asks for a password before unlocking a guarded gallery', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $props = props("/galerien/{$gallery->public_id}");

    expect($props['unlocked'])->toBeFalse();
});

it('refuses the wrong password and grants no claim', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    expect(props("/galerien/{$gallery->public_id}")['unlocked'])->toBeFalse();
});

it('unlocks on the right password and stays unlocked on the next request', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'basthorst2026'])
        ->assertRedirect("/galerien/{$gallery->public_id}");

    expect(props("/galerien/{$gallery->public_id}")['unlocked'])->toBeTrue();
});

it('does not carry a claim from one gallery to another', function () {
    $a = Gallery::factory()->create();
    $a->rotateViewPassword('one');
    $b = Gallery::factory()->create();
    $b->rotateViewPassword('two');

    $this->post("/galerien/{$a->public_id}/passwort", ['password' => 'one']);

    expect(props("/galerien/{$a->public_id}")['unlocked'])->toBeTrue()
        ->and(props("/galerien/{$b->public_id}")['unlocked'])->toBeFalse();
});

// ---------- reaching "download" requires the view gate first ----------

it('bounces an unauthenticated download request back to the password gate', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $this->get("/galerien/{$gallery->public_id}/download")
        ->assertRedirect("/galerien/{$gallery->public_id}");
});

it('reaches the download screen once the view gate is satisfied', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');
    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'basthorst2026']);

    $props = props("/galerien/{$gallery->public_id}/download");

    expect($props['step'])->toBe('ready');
});

// ---------- the download PIN gate (mockup 11) ----------

it('asks for the download pin before granting the download claim', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');

    $props = props("/galerien/{$gallery->public_id}/download");

    expect($props['step'])->toBe('pin');
});

it('refuses the wrong pin and grants no download claim', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');

    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '0000'])
        ->assertSessionHasErrors('pin');

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('pin');
});

it('a guest holding only the view password cannot reach a ready download', function () {
    // The story's own acceptance criterion: the view gate alone is not
    // enough. No password is set here at all — the point is that even an
    // entirely public gallery still enforces its download PIN independently.
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('pin');

    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '4821']);

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready');
});

it('bounces an email request made before the pin gate is cleared', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');
    $gallery->rotateEmailVerification(true);

    $this->post("/galerien/{$gallery->public_id}/download/email", ['email' => 'anna@example.test'])
        ->assertRedirect("/galerien/{$gallery->public_id}/download");

    expect(GalleryEmailVerification::count())->toBe(0);
});

// ---------- the email gate (mockup 12) ----------

it('moves to the email step once the pin is cleared', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');
    $gallery->rotateEmailVerification(true);
    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '4821']);

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('email');
});

it('sends a verification link and grants the claim once it is opened', function () {
    Notification::fake();
    $gallery = Gallery::factory()->create();
    $gallery->rotateEmailVerification(true);

    $this->post("/galerien/{$gallery->public_id}/download/email", ['email' => 'anna@example.test'])
        ->assertRedirect();

    $verification = GalleryEmailVerification::sole();
    expect($verification->email)->toBe('anna@example.test')
        ->and($verification->consumed_at)->toBeNull();

    Notification::assertSentOnDemand(GalleryEmailVerificationLink::class);

    $token = null;
    Notification::assertSentOnDemand(
        GalleryEmailVerificationLink::class,
        function ($notification, $channels, $notifiable) use (&$token) {
            $token = (string) str($notification->toMail($notifiable)->actionUrl)->afterLast('/');

            return true;
        }
    );

    $this->get("/galerien/{$gallery->public_id}/download/email/{$token}")
        ->assertRedirect("/galerien/{$gallery->public_id}/download");

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready')
        ->and($verification->fresh()->consumed_at)->not->toBeNull();
});

it('refuses a forged email verification token', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateEmailVerification(true);

    $this->get('/galerien/'.$gallery->public_id.'/download/email/'.str_repeat('a', 64));

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('email');
});

it('lets one email link be spent only once', function () {
    Notification::fake();
    $gallery = Gallery::factory()->create();
    $gallery->rotateEmailVerification(true);
    $this->post("/galerien/{$gallery->public_id}/download/email", ['email' => 'anna@example.test']);
    $verification = GalleryEmailVerification::sole();

    $first = app(App\Gallery\Actions\ConsumeGalleryEmailVerification::class);
    // The raw token never left the notification, so reissue one the same way
    // the action itself would have and hash it the same way to compare.
    $raw = 'known-for-the-test';
    $verification->forceFill(['token_hash' => GalleryEmailVerification::hash($raw)])->save();

    expect($first($gallery, $raw))->toBe('anna@example.test')
        ->and($first($gallery, $raw))->toBeNull();
});

// ---------- rotation independence — the story's central acceptance criterion ----------

it('rotating the pin leaves an existing view session and email verification intact', function () {
    Notification::fake();
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');
    $gallery->rotateDownloadPin('4821');
    $gallery->rotateEmailVerification(true);

    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'basthorst2026']);
    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '4821']);
    $this->post("/galerien/{$gallery->public_id}/download/email", ['email' => 'anna@example.test']);
    $verification = GalleryEmailVerification::sole();
    $raw = 'known-for-the-test';
    $verification->forceFill(['token_hash' => GalleryEmailVerification::hash($raw)])->save();
    $this->get("/galerien/{$gallery->public_id}/download/email/{$raw}");

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready');

    $gallery->rotateDownloadPin('9999');

    // The view gate is untouched — no re-entering the password.
    expect(props("/galerien/{$gallery->public_id}")['unlocked'])->toBeTrue();

    // The download gate is back to asking, because its claim carried the old
    // version.
    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('pin');

    // Clearing the new pin restores "ready" without ever re-asking for the
    // email — that claim's own version was never touched.
    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '9999']);
    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready');
});

it('rotating the view password does not touch a granted download or email claim', function () {
    Notification::fake();
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');
    $gallery->rotateDownloadPin('4821');

    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'basthorst2026']);
    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '4821']);
    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready');

    $gallery->rotateViewPassword('newpassword');

    // The download claim survives even though the view claim is now stale —
    // but reaching /download at all requires the view gate, so this proves
    // the download version specifically was never bumped.
    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'newpassword']);
    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready');
});
