<?php

use App\Crm\Models\ClientCase;
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
    $case = ClientCase::factory()->create();
    Gallery::factory()->for($case, 'case')->create(['title' => 'Hochzeit Müller']);
    $second = Gallery::factory()->for($case, 'case')->create(['title' => 'Hochzeit Müller']);

    expect($second->slug)->toBe('hochzeit-mueller-2');
});

it('resolves the admin record by the original public_id for ever, after the slug changes', function () {
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller']);

    test()->patch("/galerien/{$gallery->public_id}", ['slug' => 'unser-tag'])->assertRedirect();

    $props = props("/galerien/{$gallery->public_id}");

    expect($props['gallery']['slug'])->toBe('unser-tag');
});

it('refuses a slug already taken within the same prefix', function () {
    $case = ClientCase::factory()->create();
    Gallery::factory()->for($case, 'case')->create(['slug' => 'unser-tag']);
    $gallery = Gallery::factory()->for($case, 'case')->create();

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

it('refuses a slug already taken by another tenant under the same prefix', function () {
    $gallery = Gallery::factory()->create();

    DB::table('galleries')->insert([
        'tenant_id' => '00000000-0000-4000-8000-00000000dead',
        'public_id' => (string) new Cuid2,
        'title' => 'Andere Studio-Galerie',
        'prefix' => $gallery->prefix,
        'slug' => 'geteilter-name',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    test()->patch("/galerien/{$gallery->public_id}", ['slug' => 'geteilter-name'])
        ->assertSessionHasErrors('slug');
});

// Generation-time collisions must see across tenants too, or two studios
// naming a gallery the same thing would collide on the un-tenanted route.
it('generates around a slug another tenant already holds under the same prefix', function () {
    DB::table('galleries')->insert([
        'tenant_id' => '00000000-0000-4000-8000-00000000dead',
        'public_id' => (string) new Cuid2,
        'title' => 'Ihre Galerie',
        'prefix' => 'gemeinsam',
        'slug' => 'hochzeit-mueller',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller', 'prefix' => 'gemeinsam']);

    expect($gallery->slug)->toBe('hochzeit-mueller-2');
});

it('addresses a gallery as prefix and slug under the viewer host', function () {
    config(['services.gallery.url' => 'http://gallery.test']);
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller', 'prefix' => 'mueller']);
    $gallery->update(['slug' => 'afterwedding']);

    expect($gallery->publicUrl())->toBe('http://gallery.test/mueller/afterwedding');
});

it('resolves the gallery on the api surface by prefix~slug, not only by public_id', function () {
    useSurface(Surface::API);

    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller']);
    $gallery->update(['slug' => 'unser-tag']);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg', 'width' => 4000, 'height' => 3000]);
    $gallery->fresh()->publish();

    test()->getJson('/api/galleries/'.$gallery->reference())->assertOk()
        ->assertJsonPath('gallery.public_id', $gallery->public_id);

    // The original link, still in an inbox somewhere, must keep resolving.
    test()->getJson("/api/galleries/{$gallery->public_id}")->assertOk();
});

it('buys a view pass by address on the api surface', function () {
    useSurface(Surface::API);

    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Müller']);
    $gallery->update(['slug' => 'unser-tag']);
    $gallery->rotateViewPassword('basthorst2026');

    test()->postJson('/api/galleries/'.$gallery->reference().'/passwort', ['password' => 'basthorst2026'])->assertOk();
});

// RefreshDatabase migrates an empty database — proving the backfill needs a
// row that predates the column, the same rollback --path pattern
// GalleryIngestTargetTest uses for gallery_set_id.
it('backfills a prefix from the case name, one per case, and a hash without a case', function () {
    $migration = '2026_11_02_000100_add_prefix_to_galleries_table';
    $case = ClientCase::factory()->create(['name' => 'Hochzeit Müller']);

    Artisan::call('migrate:rollback', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

    $row = fn (array $extra) => DB::table('galleries')->insertGetId([
        'tenant_id' => config('tenancy.tenant_id'),
        'public_id' => (string) new Cuid2,
        'title' => 'Vor der Spalte',
        'slug' => (string) new Cuid2,
        'created_at' => now(),
        'updated_at' => now(),
    ] + $extra);

    $first = $row(['case_id' => $case->id]);
    $second = $row(['case_id' => $case->id]);
    $loose = $row([]);

    Artisan::call('migrate', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

    expect(DB::table('galleries')->whereIn('id', [$first, $second])->pluck('prefix')->all())->toBe(['hochzeit-mueller', 'hochzeit-mueller'])
        ->and(DB::table('galleries')->where('id', $loose)->value('prefix'))->toMatch('/^[a-z0-9]{10}$/');
});

it('formats a slug as lowercase letters, digits and hyphens only, within its bounds', function () {
    expect(GallerySlug::format('Hôtel  --  de   Ville!! 42'))->toMatch(GallerySlug::PATTERN)
        ->and(strlen(GallerySlug::format(str_repeat('a', 200))))->toBeLessThanOrEqual(GallerySlug::MAX);
});

it('lets two prefixes carry the same slug', function () {
    $first = Gallery::factory()->create(['prefix' => 'meier', 'title' => 'Afterwedding']);
    $second = Gallery::factory()->create(['prefix' => 'schulz', 'title' => 'Afterwedding']);

    expect($first->slug)->toBe('afterwedding')
        ->and($second->slug)->toBe('afterwedding')
        ->and(Gallery::findByAddress('schulz', 'afterwedding')->id)->toBe($second->id)
        ->and(Gallery::findByReference('meier~afterwedding')->id)->toBe($first->id);
});

it('gives galleries of one case the case name as prefix', function () {
    $case = ClientCase::factory()->create(['name' => 'Hochzeit Meier']);
    $first = Gallery::factory()->for($case, 'case')->create(['title' => 'Trauung']);
    $second = Gallery::factory()->for($case, 'case')->create(['title' => 'Trauung']);

    expect($first->prefix)->toBe('hochzeit-meier')
        ->and($second->prefix)->toBe('hochzeit-meier')
        ->and($second->slug)->toBe('trauung-2');
});

it('keeps prefixes of two cases with one name apart', function () {
    $first = Gallery::factory()->for(ClientCase::factory()->create(['name' => 'Hochzeit Meier']), 'case')->create();
    $second = Gallery::factory()->for(ClientCase::factory()->create(['name' => 'Hochzeit Meier']), 'case')->create();

    expect($second->prefix)->toBe('hochzeit-meier-2')->not->toBe($first->prefix);
});

it('does not hand out a reserved word as prefix', function () {
    $gallery = Gallery::factory()->for(ClientCase::factory()->create(['name' => 'API']), 'case')->create();

    expect($gallery->prefix)->toBe('api-2');
});

it('stores a hash prefix once for a gallery without a case', function () {
    $gallery = Gallery::factory()->create(['case_id' => null]);

    expect($gallery->prefix)->toMatch('/^[a-z0-9]{10}$/')
        ->and($gallery->fresh()->prefix)->toBe($gallery->prefix);
});

it('keeps the prefix when the case is renamed', function () {
    $case = ClientCase::factory()->create(['name' => 'Hochzeit Meier']);
    $gallery = Gallery::factory()->for($case, 'case')->create();

    $case->update(['name' => 'Hochzeit Schulz']);

    expect($gallery->fresh()->prefix)->toBe('hochzeit-meier');
});

it('points an old slug link at the new address', function () {
    useSurface(Surface::API);
    config(['services.gallery.url' => 'http://gallery.test']);
    $gallery = Gallery::factory()->create(['prefix' => 'meier', 'title' => 'Afterwedding']);

    test()->getJson('/api/gallery-address/afterwedding')->assertOk()->assertExactJson(['path' => 'meier/afterwedding']);
    test()->getJson("/api/gallery-address/{$gallery->public_id}")->assertOk()->assertExactJson(['path' => 'meier/afterwedding']);
});

it('does not point an ambiguous old slug at either gallery', function () {
    useSurface(Surface::API);
    Gallery::factory()->create(['prefix' => 'meier', 'title' => 'Afterwedding']);
    Gallery::factory()->create(['prefix' => 'schulz', 'title' => 'Afterwedding']);

    test()->getJson('/api/gallery-address/afterwedding')->assertNotFound();
});
