<?php

use App\Gallery\Actions\ConfirmGalleryGuest;
use App\Gallery\Actions\ConsumeGalleryEmailVerification;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryEmailVerification;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Models\GallerySet;
use App\Gallery\Notifications\GalleryGuestCode;
use App\Gallery\Support\GalleryGuestPass;
use App\Media\Models\Asset;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    Notification::fake();

    $this->gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $set->id,
        'key' => 'galleries/g1/a.jpg', 'filename' => 'a.jpg', 'width' => 200, 'height' => 100,
    ]);
    $this->version = $this->gallery->fresh()->publish();
});

function askForCode(Gallery $gallery, string $email = 'anna@example.com'): void
{
    test()->postJson("/api/galleries/{$gallery->public_id}/guest", ['email' => $email, 'name' => 'Anna Meier'])
        ->assertOk()
        ->assertJson(['sent' => true]);
}

/** Der Code steht nur in der Mail — im Test wird er dort abgeholt. */
function sentCode(): string
{
    $captured = null;

    Notification::assertSentOnDemand(GalleryGuestCode::class, function ($notification) use (&$captured) {
        $captured = (new ReflectionProperty($notification, 'code'))->getValue($notification);

        return true;
    });

    return (string) $captured;
}

it('sends a code and lays the guest down unconfirmed', function () {
    askForCode($this->gallery);

    $guest = GalleryGuest::sole();

    expect($guest->email)->toBe('anna@example.com')
        ->and($guest->name)->toBe('Anna Meier')
        ->and($guest->confirmed_at)->toBeNull()
        ->and(sentCode())->toHaveLength(6);
});

it('confirms with the code and hands back a pass', function () {
    askForCode($this->gallery);

    $this->postJson("/api/galleries/{$this->gallery->public_id}/guest/bestaetigung", [
        'email' => 'anna@example.com',
        'code' => sentCode(),
    ])->assertOk()->assertJson(['confirmed' => true, 'name' => 'Anna Meier']);

    expect(GalleryGuest::sole()->confirmed_at)->not->toBeNull();
});

// Entscheidung 3: sechs Ziffern sind eine Million Möglichkeiten, aber ohne
// Zähler reichen ein paar Minuten.
it('burns the attempt on a wrong code and gives up after five', function () {
    askForCode($this->gallery);

    foreach (range(1, ConfirmGalleryGuest::MAX_ATTEMPTS) as $ignored) {
        $this->postJson("/api/galleries/{$this->gallery->public_id}/guest/bestaetigung", [
            'email' => 'anna@example.com', 'code' => '000000',
        ])->assertStatus(422);
    }

    expect(GalleryEmailVerification::sole()->attempts)->toBe(ConfirmGalleryGuest::MAX_ATTEMPTS);

    // Selbst der richtige Code hilft jetzt nicht mehr.
    $this->postJson("/api/galleries/{$this->gallery->public_id}/guest/bestaetigung", [
        'email' => 'anna@example.com', 'code' => sentCode(),
    ])->assertStatus(422);
});

// Sonst holt sich ein Angreifer beliebig viele frische Codes und der
// Fehlversuchszähler ist wertlos.
it('brakes the asking, not only the guessing', function () {
    RateLimiter::clear('gallery-guest:'.$this->gallery->public_id.':anna@example.com');

    foreach (range(1, 3) as $ignored) {
        askForCode($this->gallery);
    }

    $this->postJson("/api/galleries/{$this->gallery->public_id}/guest", ['email' => 'anna@example.com', 'name' => 'Anna'])
        ->assertStatus(429);
});

// R44: der Betrachter rendert serverseitig — über eine IP zu zählen hieße,
// die ganze Hochzeitsgesellschaft als einen Absender zu behandeln.
it('counts per address, so one guest cannot lock out the next', function () {
    foreach (range(1, 3) as $ignored) {
        askForCode($this->gallery);
    }

    $this->postJson("/api/galleries/{$this->gallery->public_id}/guest", ['email' => 'ben@example.com', 'name' => 'Ben'])
        ->assertOk();
});

// Entscheidung 11: eine Bestätigung gilt nur dort, wo sie erteilt wurde.
//
// Zwei Ebenen, beide geprüft. Die Route allein bewies es nicht: der Gast
// wird ohnehin je Galerie gesucht und wäre auch ohne die Prüfung im Pass
// nicht gefunden worden. Ein Test, der bei entfernter Regel grün bleibt,
// bewacht sie nicht.
it('refuses a pass from another gallery', function () {
    $other = Gallery::factory()->create();
    [$guest, $pass] = confirmedGuest($other);

    expect(app(GalleryGuestPass::class)->identify($this->gallery, $pass))->toBeNull()
        ->and(app(GalleryGuestPass::class)->identify($other, $pass))->toBe($guest->identifier);

    $this->postJson("/api/gallery-versions/{$this->version->public_id}/favorites/galleries/g1/a.jpg", ['guest_pass' => $pass])
        ->assertForbidden();
});

it('refuses a forged or expired pass', function () {
    [$guest, $pass] = confirmedGuest($this->gallery);
    $parts = explode('.', $pass);

    expect(app(GalleryGuestPass::class)->identify($this->gallery, $pass.'x'))->toBeNull()
        ->and(app(GalleryGuestPass::class)->identify($this->gallery, "{$parts[0]}.{$parts[1]}.{$parts[2]}.1.{$parts[4]}"))->toBeNull();
});

// Entscheidung 5 und 6: wer erst markiert und dann aufgefordert wird, sich
// anzumelden, darf die Markierungen nicht verlieren — sonst meldet er sich
// nicht an.
it('takes the anonymous marks along, and drops the duplicate heart', function () {
    [$guest] = confirmedGuest($this->gallery, 'anna@example.com', 'Anna');

    AssetFavorite::create(['gallery_version_id' => $this->version->id, 'asset_key' => 'galleries/g1/a.jpg', 'guest_identifier' => $guest->identifier]);
    AssetFavorite::create(['gallery_version_id' => $this->version->id, 'asset_key' => 'galleries/g1/a.jpg', 'guest_identifier' => 'anonym-1']);
    AssetComment::create(['gallery_version_id' => $this->version->id, 'asset_key' => 'galleries/g1/a.jpg', 'guest_identifier' => 'anonym-1', 'body' => 'Vorher geschrieben.']);

    app(ConfirmGalleryGuest::class)->adopt($guest, 'anonym-1');

    expect(AssetFavorite::where('guest_identifier', 'anonym-1')->count())->toBe(0)
        ->and(AssetFavorite::where('guest_identifier', $guest->identifier)->count())->toBe(1)
        ->and(AssetComment::where('guest_identifier', $guest->identifier)->count())->toBe(1);
});

// Entscheidung 4: dieselbe Adresse ergibt dieselbe Kennung — nur so taucht
// der Verlauf auf dem zweiten Gerät wieder auf.
it('gives the same address the same identifier, and a different gallery a different one', function () {
    $other = Gallery::factory()->create();

    expect(GalleryGuest::identifierFor($this->gallery, 'Anna@Example.com'))
        ->toBe(GalleryGuest::identifierFor($this->gallery, 'anna@example.com'))
        ->and(GalleryGuest::identifierFor($other, 'anna@example.com'))
        ->not->toBe(GalleryGuest::identifierFor($this->gallery, 'anna@example.com'));
});

// Entscheidung 7: es ist dieselbe Tatsache über denselben Menschen. Der
// Betrachter trägt sie als signierten Pass, das Portal als Sitzung — der
// Datensatz dahinter ist einer.
it('lays down the same guest when the portal spends an email link', function () {
    $token = 'a'.str_repeat('b', 63);

    GalleryEmailVerification::create([
        'gallery_id' => $this->gallery->id,
        'email' => 'anna@example.com',
        'token_hash' => GalleryEmailVerification::hash($token),
        'expires_at' => now()->addMinutes(30),
    ]);

    expect(app(ConsumeGalleryEmailVerification::class)($this->gallery, $token))
        ->toBe('anna@example.com');

    $guest = GalleryGuest::sole();

    expect($guest->identifier)->toBe(GalleryGuest::identifierFor($this->gallery, 'anna@example.com'))
        ->and($guest->confirmed_at)->not->toBeNull()
        // Das Portal fragt nie nach einem Namen — der Vorschlag aus der
        // Adresse ist besser als „Gast".
        ->and($guest->name)->toBe('Anna');
});

it('suggests a name from the address when nobody was asked for one', function () {
    expect(GalleryGuest::nameFromEmail('anna.mueller1987@example.com'))->toBe('Anna Mueller')
        ->and(GalleryGuest::nameFromEmail('ben_schulz@example.com'))->toBe('Ben Schulz')
        ->and(GalleryGuest::nameFromEmail('1987@example.com'))->toBe('1987');
});
