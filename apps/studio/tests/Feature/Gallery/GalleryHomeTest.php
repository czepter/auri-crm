<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\ProjectType;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    homeMode('overview');
});

function homeMode(string $mode, array $more = []): void
{
    StudioSettings::query()->update(['gallery_home_mode' => $mode, ...$more]);
    StudioSettings::forgetCurrent();
}

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $version
 */
function listedGallery(string $title, array $attributes = [], array $version = []): Gallery
{
    $gallery = Gallery::factory()->create(['listed_on_home' => true, 'title' => $title, ...$attributes]);

    GalleryVersion::factory()->create([
        'gallery_id' => $gallery->id,
        'title' => $title,
        'assets' => [['key' => "k/{$gallery->id}-a.jpg", 'set' => 'Highlights', 'width' => 300, 'height' => 200]],
        ...$version,
    ]);

    return $gallery->fresh();
}

/** @return list<string> */
function homeTitles(string $query = ''): array
{
    return collect(test()->getJson("/api/gallery-home{$query}")->assertOk()->json('galleries'))->pluck('title')->all();
}

it('lists open, password and e-mail confirmation galleries and marks only the password one', function () {
    listedGallery('Offen');
    listedGallery('Passwort')->rotateViewPassword('geheim');
    listedGallery('Mail')->rotateEmailVerification(true);

    $tiles = collect(test()->getJson('/api/gallery-home')->assertOk()->json('galleries'))->keyBy('title');

    expect($tiles->keys()->all())->toEqualCanonicalizing(['Offen', 'Passwort', 'Mail'])
        ->and($tiles['Passwort']['protected'])->toBeTrue()
        ->and($tiles['Mail']['protected'])->toBeFalse()
        ->and($tiles['Offen']['cover'])->toBeTrue();
});

it('hides a gallery that breaks one rule, with the matching blocker', function (Closure $break, ?string $blocker) {
    $gallery = listedGallery('Weg');
    $break($gallery);

    expect(homeTitles())->toBe([])
        ->and($gallery->fresh()->homeBlocker())->toBe($blocker);
})->with([
    'not opted in' => [fn (Gallery $g) => $g->update(['listed_on_home' => false]), null],
    'no version' => [fn (Gallery $g) => $g->versions()->delete(), 'draft'],
    'withdrawn' => [fn (Gallery $g) => $g->forceFill(['unpublished_at' => now()])->save(), 'withdrawn'],
    'expired' => [fn (Gallery $g) => $g->update(['expires_at' => now()->subDay()]), 'expired'],
    'locked' => [fn (Gallery $g) => $g->update(['view_mode' => 'locked']), 'locked'],
    'external' => [fn (Gallery $g) => $g->update(['is_external' => true]), 'external'],
]);

it('keeps the opt-in after withdrawal so the gallery returns on its own', function () {
    $gallery = listedGallery('Zurück');
    $gallery->forceFill(['unpublished_at' => now()])->save();
    expect(homeTitles())->toBe([]);

    $gallery->forceFill(['unpublished_at' => null])->save();

    expect(homeTitles())->toBe(['Zurück'])->and($gallery->fresh()->listed_on_home)->toBeTrue();
});

it('sends exactly the documented keys and no contact data', function () {
    $case = ClientCase::factory()->create(['name' => 'Familie Geheim']);
    listedGallery('Offen', ['case_id' => $case->id])->rotateViewPassword('x');

    $response = test()->getJson('/api/gallery-home')->assertOk();
    $json = $response->json();

    expect(array_keys($json))->toEqualCanonicalizing(['mode', 'noindex', 'studio', 'design', 'categories', 'category', 'more', 'galleries'])
        ->and(array_keys($json['studio']))->toEqualCanonicalizing(['name', 'logo', 'website', 'impressum', 'datenschutz'])
        ->and(array_keys($json['galleries'][0]))->toEqualCanonicalizing(['title', 'date', 'category', 'path', 'version', 'cover', 'protected', 'focal'])
        ->and($response->getContent())->not->toContain('case_id')->not->toContain('image_pass');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('chooses the guest-visible cover, else the first guest image, else none', function () {
    $gallery = listedGallery('Cover', [], [
        'cover_key' => 'intern.jpg',
        'assets' => [['key' => 'intern.jpg', 'set' => 'Privat'], ['key' => 'gast.jpg', 'set' => 'Highlights']],
    ]);
    $gallery->sets()->create(['name' => 'Privat', 'position' => 1, 'access' => 'internal']);
    $version = $gallery->currentVersion;

    expect($gallery->homeCoverKey($version))->toBe('gast.jpg');

    $version->cover_key = 'gast.jpg';
    expect($gallery->homeCoverKey($version))->toBe('gast.jpg');

    $version->assets = [['key' => 'intern.jpg', 'set' => 'Privat']];
    expect($gallery->homeCoverKey($version))->toBeNull();
});

it('puts pinned galleries first by date, also inside a category', function () {
    $type = ProjectType::query()->where('key', 'wedding')->firstOrFail();
    $make = fn (string $title, string $date, bool $pinned, bool $listed = true) => listedGallery($title, [
        'event_date' => $date, 'home_pinned' => $pinned, 'listed_on_home' => $listed, 'project_type_id' => $type->id,
    ]);
    $make('alt-angeheftet', '2025-01-01', true);
    $make('neu-angeheftet', '2026-01-01', true);
    $make('neu', '2026-06-01', false);
    $make('alt', '2024-01-01', false);
    $make('versteckt-angeheftet', '2027-01-01', true, false);

    $expected = ['neu-angeheftet', 'alt-angeheftet', 'neu', 'alt'];

    expect(homeTitles())->toBe($expected)
        ->and(homeTitles('?kategorie='.Str::slug($type->name)))->toBe($expected);
});

it('filters by category and ignores an unknown slug', function () {
    $wedding = ProjectType::query()->where('key', 'wedding')->firstOrFail();
    listedGallery('Hochzeit', ['project_type_id' => $wedding->id]);
    listedGallery('Ohne');

    $slug = Str::slug($wedding->name);
    $payload = test()->getJson("/api/gallery-home?kategorie={$slug}")->json();

    expect($payload['categories'])->toBe([['slug' => $slug, 'name' => $wedding->name]])
        ->and(collect($payload['galleries'])->pluck('title')->all())->toBe(['Hochzeit'])
        ->and(homeTitles('?kategorie=gibt-es-nicht'))->toEqualCanonicalizing(['Hochzeit', 'Ohne']);
});

it('pages cumulatively by 24', function () {
    foreach (range(1, 30) as $n) {
        listedGallery("G{$n}", ['event_date' => now()->subDays($n)->toDateString()]);
    }

    $first = test()->getJson('/api/gallery-home')->json();
    $second = test()->getJson('/api/gallery-home?seite=2')->json();

    expect($first['galleries'])->toHaveCount(24)->and($first['more'])->toBeTrue()
        ->and($second['galleries'])->toHaveCount(30)->and($second['more'])->toBeFalse()
        ->and(homeTitles('?seite=0'))->toHaveCount(24)
        ->and(homeTitles('?seite=abc'))->toHaveCount(24)
        ->and(homeTitles('?seite=99999999999999999999'))->toHaveCount(30)
        ->and(homeTitles('?seite[]=2'))->toHaveCount(24);
});

it('treats a non-string category as no filter', function () {
    listedGallery('Ohne');

    expect(homeTitles('?kategorie[]=x'))->toBe(['Ohne']);
});

it('answers the gate lookup only for a listed gallery in overview mode', function () {
    $gallery = listedGallery('Geheimtitel');
    $gallery->rotateViewPassword('pw');
    $unlisted = listedGallery('Verborgen', ['listed_on_home' => false]);
    $unlisted->rotateViewPassword('pw');

    $address = fn (Gallery $g) => "/api/gallery-home?adresse={$g->prefix}/{$g->slug}";

    test()->getJson($address($gallery))->assertJsonCount(1, 'galleries')->assertJsonPath('galleries.0.title', 'Geheimtitel');
    expect(test()->getJson($address($unlisted))->json('galleries'))->toBe([]);

    homeMode('redirect', ['gallery_home_redirect_url' => 'https://example.test']);
    expect(test()->getJson($address($gallery))->getContent())->not->toContain('Geheimtitel');
});

it('carries no tiles in none and redirect mode and is never cached', function () {
    listedGallery('Offen');

    homeMode('none');
    $none = test()->getJson('/api/gallery-home')->assertExactJson(['mode' => 'none']);

    homeMode('redirect', ['gallery_home_redirect_url' => 'https://example.test']);
    $redirect = test()->getJson('/api/gallery-home')->assertExactJson(['mode' => 'redirect', 'url' => 'https://example.test']);

    expect($none->headers->get('Cache-Control'))->toContain('no-store')
        ->and($redirect->headers->get('Cache-Control'))->toContain('no-store');
});
