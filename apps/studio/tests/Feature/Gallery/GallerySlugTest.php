<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Gallery\Support\GallerySlug;
use App\Media\Models\Asset;
use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Visus\Cuid2\Cuid2;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('generates a slug from the title on creation', function () {
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller']);

    expect($gallery->slug)->toBe('hochzeit-mueller');
});

it('makes a slug unique against an existing one from the same title', function () {
    Gallery::factory()->create(['title' => 'Hochzeit Müller']);
    $second = Gallery::factory()->create(['title' => 'Hochzeit Müller']);

    expect($second->slug)->toBe('hochzeit-mueller-2');
});

it('resolves the admin record by the original public_id for ever, after the slug changes', function () {
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller']);

    test()->patch("/galerien/{$gallery->public_id}", ['slug' => 'unser-tag'])->assertRedirect();

    $props = props("/galerien/{$gallery->public_id}");

    expect($props['gallery']['slug'])->toBe('unser-tag');
});

it('resolves the admin record by the new slug too', function () {
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller']);
    $gallery->update(['slug' => 'unser-tag']);

    $props = props('/galerien/unser-tag');

    expect($props['gallery']['id'])->toBe($gallery->public_id);
});

it('refuses a slug already taken by another gallery', function () {
    Gallery::factory()->create(['slug' => 'unser-tag']);
    $gallery = Gallery::factory()->create();

    test()->patch("/galerien/{$gallery->public_id}", ['slug' => 'unser-tag'])
        ->assertSessionHasErrors('slug');

    expect($gallery->fresh()->slug)->not->toBe('unser-tag');
});

it('refuses a reserved word as a slug', function () {
    $gallery = Gallery::factory()->create();

    test()->patch("/galerien/{$gallery->public_id}", ['slug' => 'neu'])
        ->assertSessionHasErrors('slug');

    expect($gallery->fresh()->slug)->not->toBe('neu');
});

// Not a route yet (routes/admin.php carries no /galerien/extern/neu today),
// but the spec schedules it for the next stage — reserved ahead of time so
// a gallery slugged today never blocks that route from ever existing.
it('refuses extern as a slug, ahead of the route that will need it', function () {
    $gallery = Gallery::factory()->create();

    test()->patch("/galerien/{$gallery->public_id}", ['slug' => 'extern'])
        ->assertSessionHasErrors('slug');

    expect($gallery->fresh()->slug)->not->toBe('extern');
});

it('refuses a slug already taken by another tenant', function () {
    $gallery = Gallery::factory()->create();

    DB::table('galleries')->insert([
        'tenant_id' => '00000000-0000-4000-8000-00000000dead',
        'public_id' => (string) new Cuid2,
        'title' => 'Andere Studio-Galerie',
        'slug' => 'geteilter-name',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    test()->patch("/galerien/{$gallery->public_id}", ['slug' => 'geteilter-name'])
        ->assertSessionHasErrors('slug');
});

// Generation-time collisions must see across tenants too, or two studios
// naming a gallery the same thing would collide on the un-tenanted route.
it('generates around a slug another tenant already holds', function () {
    DB::table('galleries')->insert([
        'tenant_id' => '00000000-0000-4000-8000-00000000dead',
        'public_id' => (string) new Cuid2,
        'title' => 'Ihre Galerie',
        'slug' => 'hochzeit-mueller',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller']);

    expect($gallery->slug)->toBe('hochzeit-mueller-2');
});

it('uses the slug in the public URL once one is set', function () {
    config(['services.gallery.url' => 'http://gallery.test']);
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller']);
    $gallery->update(['slug' => 'unser-tag']);

    expect($gallery->publicUrl())->toBe('http://gallery.test/g/unser-tag');
});

// Generation always sets a slug (the column is NOT NULL) — this pins the
// fallback branch itself, the floor resolveRouteBinding() relies on, without
// asserting a database state the schema no longer allows.
it('falls back to the public_id in the public URL when no slug is set', function () {
    config(['services.gallery.url' => 'http://gallery.test']);
    $gallery = Gallery::factory()->create();
    $gallery->slug = null;

    expect($gallery->publicUrl())->toBe("http://gallery.test/g/{$gallery->public_id}");
});

it('resolves the gallery on the api surface by slug, not only by public_id', function () {
    useSurface(Surface::API);

    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller']);
    $gallery->update(['slug' => 'unser-tag']);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg', 'width' => 4000, 'height' => 3000]);
    $gallery->fresh()->publish();

    test()->getJson('/api/galleries/unser-tag')->assertOk()
        ->assertJsonPath('gallery.public_id', $gallery->public_id);

    // The original link, still in an inbox somewhere, must keep resolving.
    test()->getJson("/api/galleries/{$gallery->public_id}")->assertOk();
});

it('buys a view pass by slug on the api surface', function () {
    useSurface(Surface::API);

    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller']);
    $gallery->update(['slug' => 'unser-tag']);
    $gallery->rotateViewPassword('basthorst2026');

    test()->postJson('/api/galleries/unser-tag/passwort', ['password' => 'basthorst2026'])->assertOk();
});

// RefreshDatabase migrates an empty database — proving the backfill needs a
// row that predates the column, the same rollback --path pattern
// GalleryIngestTargetTest uses for gallery_set_id.
it('backfills a slug for a gallery row written before the column existed', function () {
    $migration = '2026_08_25_000100_add_slug_to_galleries_table';

    Artisan::call('migrate:rollback', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

    $galleryId = DB::table('galleries')->insertGetId([
        'tenant_id' => config('tenancy.tenant_id'),
        'public_id' => (string) new Cuid2,
        'title' => 'Vor der Spalte',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('migrate', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

    expect(DB::table('galleries')->where('id', $galleryId)->value('slug'))->toBe('vor-der-spalte');
});

it('formats a slug as lowercase letters, digits and hyphens only, within its bounds', function () {
    expect(GallerySlug::format('Hôtel  --  de   Ville!! 42'))->toMatch(GallerySlug::PATTERN)
        ->and(strlen(GallerySlug::format(str_repeat('a', 200))))->toBeLessThanOrEqual(GallerySlug::MAX);
});

it('refuses the public_id of another gallery as a slug', function () {
    $other = Gallery::factory()->create();
    $gallery = Gallery::factory()->create();

    test()->patch("/galerien/{$gallery->public_id}", ['slug' => $other->public_id])
        ->assertSessionHasErrors('slug');

    expect($gallery->fresh()->slug)->not->toBe($other->public_id);
});

it('resolves a public_id to its own gallery even when another carries it as slug', function () {
    $owner = Gallery::factory()->create(['expires_at' => now()->addDays(30)]);
    $squatter = Gallery::factory()->create(['expires_at' => now()->addDay()]);
    $squatter->update(['slug' => $owner->public_id]);
    $owner->update(['title' => 'bewegt']);

    expect(Gallery::findByPublicIdOrSlug($owner->public_id)->id)->toBe($owner->id);
});
