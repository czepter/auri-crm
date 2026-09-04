# Galerie-Verwaltung Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Galerien bekommen einen Nav-Eintrag, einen Index, ein Anlegen-Formular und benannte Vorlagen — und die drei Portal-Schalter, die heute nichts steuern, steuern etwas.

**Architecture:** Die drei Schalter (Downloads, Listen, Kommentare) werden NOT-NULL-Spalten auf `galleries` und von einer Middleware `gallery.feature:` durchgesetzt, wie `gallery.gate:` es für die drei Zugangstore tut. Studio-Default, Kontakt und Vorlage bestimmen nur noch, womit eine Galerie geboren wird; zur Laufzeit liest das Gate die Spalte. Vorlagen sind eine eigene Tabelle ohne Fremdschlüssel auf `galleries` — sie füllen beim Anlegen das Formular und sind danach nicht mehr verbunden.

**Tech Stack:** Laravel 11 (Inertia, Spatie Laravel Data, Pest), Vue 3 + TypeScript, Tailwind, shadcn-vue, Postgres.

**Spec:** `docs/superpowers/specs/2026-08-22-galerie-management-design.md`

## Global Constraints

- **Testkommando Pest:** `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='<name>'`. Die Admin-Routen sind unter jeder anderen Surface nicht registriert und antworten 404.
- **Testkommando Vitest:** `pnpm --filter @studio/app test`.
- **Typen:** Nach jeder Änderung an einer `#[TypeScript]`-Data-Klasse `pnpm php php artisan typescript:transform` laufen lassen. CI bricht bei Drift.
- **Formularsteuerelemente** kommen aus `resources/js/ui` (shadcn-vue). Kein rohes `<input>`, `<select>`, `<textarea>`.
- **Deutsche Copy** ist knapp und benennend, nie erklärend. Kein Emoji, kein Ausrufezeichen. Vor dem Einchecken eines Strings die drei Nachbarn in `resources/js/locales/de.json` lesen; länger als sie heißt umschreiben.
- **Kommentare** nur für das *Warum*. Kein Kommentar, der den Code nacherzählt.
- **Commits:** Conventional Commits, Betreff ≤ 50 Zeichen, Body nur wenn das Warum nicht im Diff steht.
- **Kein produktives System.** Schemaänderungen gehen direkt in die Create-Migration, keine ALTER-Nachträge, kein Backfill.
- **Domain-first:** neuer Code liegt bei seinen Geschwistern unter `apps/studio/app/<Domain>/{Actions,Data,Http,Models}`.

---

### Task 1: Die drei Schalter auf der Galerie und die Sperre dahinter

**Files:**
- Modify: `apps/studio/database/migrations/2026_08_09_003100_create_galleries_table.php`
- Modify: `apps/studio/app/Gallery/Models/Gallery.php:43-58`
- Create: `apps/studio/app/Http/Middleware/EnsureGalleryFeature.php`
- Modify: `apps/studio/bootstrap/app.php:108-118`
- Modify: `apps/studio/routes/portal.php:139-153`, `:241-247`, `:250-256`
- Modify: `apps/studio/routes/api.php:60-74`
- Test: `apps/studio/tests/Feature/Gallery/GalleryFeatureSwitchesTest.php`

**Interfaces:**
- Consumes: nichts.
- Produces: Spalten `galleries.downloads_enabled`, `galleries.lists_enabled`, `galleries.comments_enabled` (bool, NOT NULL, default true, in `$fillable` und als `'boolean'` gecastet). Middleware-Alias `gallery.feature`, Parameter `downloads` | `lists` | `comments`.

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Gallery/GalleryFeatureSwitchesTest.php`:

```php
<?php

use App\Gallery\Models\Gallery;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts every gallery with all three offers open', function () {
    $gallery = Gallery::factory()->create();

    expect($gallery->downloads_enabled)->toBeTrue()
        ->and($gallery->lists_enabled)->toBeTrue()
        ->and($gallery->comments_enabled)->toBeTrue();
});

describe('on the portal surface', function () {
    beforeEach(fn () => useSurface(Surface::PORTAL));

    it('refuses the download screen when the gallery offers no download', function () {
        $gallery = Gallery::factory()->create(['downloads_enabled' => false]);

        $this->get("/galerien/{$gallery->public_id}/download")->assertForbidden();
    });

    it('still serves the download screen when the gallery offers one', function () {
        $gallery = Gallery::factory()->create(['downloads_enabled' => true]);

        $this->get("/galerien/{$gallery->public_id}/download")->assertOk();
    });

    it('refuses a mark when the gallery offers no lists', function () {
        $gallery = Gallery::factory()->create(['lists_enabled' => false]);

        $this->post("/galerien/{$gallery->public_id}/favorit", ['key' => 'galleries/g1/a.jpg'])
            ->assertForbidden();
    });

    it('refuses a comment when the gallery offers no comments', function () {
        $gallery = Gallery::factory()->create(['comments_enabled' => false]);

        $this->post("/galerien/{$gallery->public_id}/kommentar", [
            'key' => 'galleries/g1/a.jpg',
            'body' => 'Schön',
        ])->assertForbidden();
    });
});

describe('on the api surface', function () {
    beforeEach(fn () => useSurface(Surface::API));

    // The Astro viewer is the second surface the same switch has to cover —
    // a gallery closed on the portal that stays open here is not closed.
    it('refuses a mark from the viewer when the gallery offers no lists', function () {
        $gallery = Gallery::factory()->create([
            'lists_enabled' => false,
            'assets' => [['key' => 'galleries/g1/a.jpg', 'width' => 200, 'height' => 100]],
        ]);
        $version = $gallery->publish();

        $this->postJson(
            "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg",
            ['guest_id' => 'guest-1'],
        )->assertForbidden();
    });

    it('refuses a comment from the viewer when the gallery offers no comments', function () {
        $gallery = Gallery::factory()->create([
            'comments_enabled' => false,
            'assets' => [['key' => 'galleries/g1/a.jpg', 'width' => 200, 'height' => 100]],
        ]);
        $version = $gallery->publish();

        $this->postJson("/api/gallery-versions/{$version->public_id}/comments", [
            'asset_key' => 'galleries/g1/a.jpg',
            'body' => 'Schön',
            'guest_id' => 'guest-1',
        ])->assertForbidden();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='GalleryFeatureSwitches'`
Expected: FAIL — `Undefined property: App\Gallery\Models\Gallery::$downloads_enabled`

- [ ] **Step 3: Add the three columns to the create migration**

In `apps/studio/database/migrations/2026_08_09_003100_create_galleries_table.php`, directly below the `balance_settled` line:

```php
            // P-09's three portal switches, on the gallery rather than in the
            // settings store: they are answered per delivery, and the studio
            // default only decides what a new one is born with. Not
            // three-valued — a gallery either offers the thing or it does
            // not, and there is no level below it to inherit from.
            $table->boolean('downloads_enabled')->default(true);
            $table->boolean('lists_enabled')->default(true);
            $table->boolean('comments_enabled')->default(true);
```

- [ ] **Step 4: Make them writable and typed on the model**

In `apps/studio/app/Gallery/Models/Gallery.php`, extend `$fillable`:

```php
    protected $fillable = [
        'project_id', 'shoot_id', 'title', 'assets', 'design',
        'requires_settled_balance', 'balance_settled',
        'is_external', 'external_url', 'expires_at',
        'downloads_enabled', 'lists_enabled', 'comments_enabled',
    ];
```

and add to `casts()`, next to `requires_settled_balance`:

```php
            'downloads_enabled' => 'boolean',
            'lists_enabled' => 'boolean',
            'comments_enabled' => 'boolean',
```

- [ ] **Step 5: Write the middleware**

Create `apps/studio/app/Http/Middleware/EnsureGalleryFeature.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Whether this gallery offers at all what the route does — the counterpart to
 * EnsureGalleryGate, which asks whether the visitor may. Declared per route
 * in the same `alias:argument` shape, so the two read as one sentence where
 * they stack.
 */
class EnsureGalleryFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $gallery = $request->route('gallery');

        // The viewer's favorite and comment routes are bound on the version,
        // never on the gallery — the switch belongs to its parent.
        if ($gallery === null) {
            $version = $request->route('galleryVersion');
            $gallery = $version instanceof GalleryVersion ? $version->gallery : null;
        }

        if ($gallery !== null && ! $gallery instanceof Gallery) {
            $gallery = Gallery::findByPublicId((string) $gallery);
        }

        // A gallery that does not exist has no offer to withhold, and a 403
        // here would answer "no such gallery" before the controller's own
        // uniform failure gets to — the same reasoning EnsureGalleryGate
        // states for letting a missing gallery through.
        if ($gallery === null) {
            return $next($request);
        }

        // match, not a dynamic attribute: a typo in a route's argument throws
        // here instead of reading null off the model and opening the gate.
        abort_unless(match ($feature) {
            'downloads' => $gallery->downloads_enabled,
            'lists' => $gallery->lists_enabled,
            'comments' => $gallery->comments_enabled,
        }, 403);

        return $next($request);
    }
}
```

- [ ] **Step 6: Register the alias**

In `apps/studio/bootstrap/app.php`, inside `$middleware->alias([...])`, below `'gallery.pass'`:

```php
            'gallery.feature' => EnsureGalleryFeature::class,
```

and add the import next to the other middleware imports:

```php
use App\Http\Middleware\EnsureGalleryFeature;
```

- [ ] **Step 7: Declare the requirement on the portal routes**

In `apps/studio/routes/portal.php`, wrap the list actions (the `Route::prefix('/galerien/{gallery}/eure-auswahl')` group's four `listen` routes) in the feature. `rotateGate` stays outside it — rotating a password is not a list.

```php
        Route::prefix('/galerien/{gallery}/eure-auswahl')->group(function () {
            Route::middleware('gallery.feature:lists')->group(function () {
                Route::post('/listen', [PortalGalleryController::class, 'storeList'])->name('portal.galleries.lists.store');
                Route::patch('/listen/{list}', [PortalGalleryController::class, 'renameList'])
                    ->name('portal.galleries.lists.rename');
                Route::delete('/listen/{list}', [PortalGalleryController::class, 'destroyList'])
                    ->name('portal.galleries.lists.destroy');
                Route::post('/listen/{list}/bild', [PortalGalleryController::class, 'toggleItem'])
                    ->name('portal.galleries.lists.toggle');
            });

            Route::post('/tore/{gate}', [PortalGalleryController::class, 'rotateGate'])
                ->name('portal.galleries.gate.rotate');
        });
```

In the `gallery.gate:view` group holding the engagement routes — the heart is a list without a name, so it follows `lists`:

```php
    Route::middleware('gallery.gate:view')->group(function () {
        Route::get('/beteiligung', [GalleryEngagementController::class, 'index'])
            ->name('gallery.engagement.index');
        Route::post('/favorit', [GalleryEngagementController::class, 'toggleFavorite'])
            ->middleware('gallery.feature:lists')
            ->name('gallery.favorite.toggle');
        Route::post('/kommentar', [GalleryEngagementController::class, 'storeComment'])
            ->middleware('gallery.feature:comments')
            ->name('gallery.comment.store');
    });
```

And the download group — the whole group, including the two email-gate routes nested inside it:

```php
    Route::middleware(['gallery.gate:view', 'gallery.feature:downloads'])->group(function () {
```

- [ ] **Step 8: Declare it on the api routes**

In `apps/studio/routes/api.php`, inside the `gallery.pass` group:

```php
    Route::middleware('gallery.feature:lists')->group(function () {
        Route::get('/gallery-versions/{galleryVersion:public_id}/favorites', [AssetFavoriteController::class, 'index'])
            ->name('api.gallery-versions.favorites.index');
        Route::post('/gallery-versions/{galleryVersion:public_id}/favorites/{key}', [AssetFavoriteController::class, 'toggle'])
            ->where('key', '.*')
            ->name('api.gallery-versions.favorites.toggle');
    });

    Route::middleware('gallery.feature:comments')->group(function () {
        Route::get('/gallery-versions/{galleryVersion:public_id}/comments', [AssetCommentController::class, 'index'])
            ->name('api.gallery-versions.comments.index');
        Route::post('/gallery-versions/{galleryVersion:public_id}/comments', [AssetCommentController::class, 'store'])
            ->name('api.gallery-versions.comments.store');
    });
```

Die bestehenden Doc-Kommentare über den beiden Paaren bleiben, wo sie stehen.

- [ ] **Step 9: Run the tests**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='GalleryFeatureSwitches'`
Expected: PASS, sieben Tests.

- [ ] **Step 10: Run the neighbouring gallery suites for regressions**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery`
Expected: PASS. Schlägt hier etwas fehl, liegt es an einer Route, die jetzt hinter der Sperre steht — nicht am Test.

- [ ] **Step 11: Commit**

```bash
git add apps/studio/database/migrations/2026_08_09_003100_create_galleries_table.php \
        apps/studio/app/Gallery/Models/Gallery.php \
        apps/studio/app/Http/Middleware/EnsureGalleryFeature.php \
        apps/studio/bootstrap/app.php \
        apps/studio/routes/portal.php \
        apps/studio/routes/api.php \
        apps/studio/tests/Feature/Gallery/GalleryFeatureSwitchesTest.php
git commit -m "feat(gallery): Schalter fuer Download, Listen, Kommentare" -m "Die drei Werte lagen als Studio-Defaults vor, die niemand las. Sie
stehen jetzt auf der Galerie und werden von gallery.feature: auf beiden
Oberflaechen durchgesetzt."
```

---

### Task 2: Das Portal zeigt nur, was die Galerie anbietet

**Files:**
- Modify: `apps/studio/app/Gallery/Http/Controllers/GalleryPasswordController.php:58-100`
- Modify: `apps/studio/resources/js/portal/pages/Gallery/Show.vue:22-42`, `:309`
- Modify: `apps/studio/resources/js/portal/components/GalleryLightbox.vue`
- Test: `apps/studio/tests/Feature/Gallery/GalleryFeatureSwitchesTest.php` (erweitern)

**Interfaces:**
- Consumes: `galleries.downloads_enabled`, `lists_enabled`, `comments_enabled` aus Task 1.
- Produces: Inertia-Prop `offers: { downloads: boolean; lists: boolean; comments: boolean }` auf `Gallery/Show`.

- [ ] **Step 1: Write the failing test**

An `apps/studio/tests/Feature/Gallery/GalleryFeatureSwitchesTest.php` im `on the portal surface`-Block anhängen:

```php
    it('tells the screen which offers stand', function () {
        $gallery = Gallery::factory()->create([
            'downloads_enabled' => false,
            'lists_enabled' => true,
            'comments_enabled' => false,
        ]);

        $offers = props("/galerien/{$gallery->public_id}")['offers'];

        expect($offers['downloads'])->toBeFalse()
            ->and($offers['lists'])->toBeTrue()
            ->and($offers['comments'])->toBeFalse();
    });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='tells the screen which offers stand'`
Expected: FAIL — `Undefined array key "offers"`

- [ ] **Step 3: Hand the offers to the screen**

In `apps/studio/app/Gallery/Http/Controllers/GalleryPasswordController.php`, im `Inertia::render('Gallery/Show', [...])`-Array, direkt unter `'unlocked'`:

```php
            // A button for something the gallery does not offer is a 403 with
            // extra steps — the routes already refuse it (gallery.feature:),
            // this is so the screen never draws it.
            'offers' => [
                'downloads' => $gallery->downloads_enabled,
                'lists' => $gallery->lists_enabled,
                'comments' => $gallery->comments_enabled,
            ],
```

- [ ] **Step 4: Run the test**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='tells the screen which offers stand'`
Expected: PASS

- [ ] **Step 5: Declare the prop in Show.vue**

In `apps/studio/resources/js/portal/pages/Gallery/Show.vue`, im `defineProps`-Block direkt unter `unlocked: boolean`:

```ts
  offers: { downloads: boolean; lists: boolean; comments: boolean }
```

- [ ] **Step 6: Hide the download button**

Zeile 309, `v-if` ergänzen:

```html
        <Button v-if="offers.downloads" as="a" :href="`/galerien/${gallery.id}/download`">{{ $t('gallery.downloadLink') }}</Button>
```

- [ ] **Step 7: Hide the list and comment controls**

`GalleryLightbox.vue` bekommt die drei Werte als Prop durchgereicht. In seinem `defineProps`-Block ergänzen:

```ts
  offers: { downloads: boolean; lists: boolean; comments: boolean }
```

und in `Show.vue` am Aufruf (Zeile 370-379):

```html
      <GalleryLightbox
        …
        :offers="offers"
      />
```

Dann diese sechs Stellen binden — es sind alle, kein Suchauftrag:

| Datei:Zeile | Element | Bedingung |
|---|---|---|
| `Show.vue:309` | Download-Knopf über dem Raster | `offers.downloads` (Schritt 6) |
| `Show.vue:328` | Listenmenü auf der Kachel, `v-if="lists?.length"` | `offers.lists && lists?.length` |
| `Show.vue:381` | Abschnitt „Eure Auswahl" | `offers.lists && lists` |
| `GalleryLightbox.vue:197` | Listenmenü, `v-if="lists?.length"` | `offers.lists && lists?.length` |
| `GalleryLightbox.vue:220` | Herz-Knopf, das `v-else` dazu | in ein eigenes `v-else-if="offers.lists"` überführen |
| `GalleryLightbox.vue:234` | Download-Link `${base}/download` | `offers.downloads` |
| `GalleryLightbox.vue:236-256` | Kommentarblatt samt `<form @submit.prevent="sendComment">` | `offers.comments` |

Das `v-if`/`v-else`-Paar in der Lightbox braucht Sorgfalt: heute heißt `v-else` „kein Client, also Herz". Mit ausgeschalteten Listen darf weder das Menü noch das Herz erscheinen, also wird aus dem Paar ein `v-if="offers.lists && lists?.length"` und ein `v-else-if="offers.lists"`.

Kein Leertext an ihrer Stelle — eine Galerie ohne Listen ist eine ohne Listenknopf, nicht eine mit einem Satz darüber, dass es keine gibt.

- [ ] **Step 8: Typecheck**

Run: `pnpm lint`
Expected: keine Fehler.

- [ ] **Step 9: Commit**

```bash
git add apps/studio/app/Gallery/Http/Controllers/GalleryPasswordController.php \
        apps/studio/resources/js/portal/pages/Gallery/Show.vue \
        apps/studio/resources/js/portal/components/GalleryLightbox.vue \
        apps/studio/tests/Feature/Gallery/GalleryFeatureSwitchesTest.php
git commit -m "feat(gallery): Portal zeichnet nur vorhandene Angebote"
```

---

### Task 3: Die Schalter am Record bedienbar machen

**Files:**
- Modify: `apps/studio/app/Gallery/Http/Controllers/GalleryRecordController.php:52-70`, `:197-226`
- Modify: `apps/studio/resources/js/admin/pages/Galleries/Record.vue:42`, `:68-72`, `:314-343`
- Modify: `apps/studio/resources/js/locales/de.json`
- Test: `apps/studio/tests/Feature/Gallery/GalleryRecordTest.php`

**Interfaces:**
- Consumes: die drei Spalten aus Task 1.
- Produces: `gallery.offers` im Record-Prop; `PATCH /galerien/{gallery}` nimmt zusätzlich `downloads_enabled`, `lists_enabled`, `comments_enabled` entgegen.

- [ ] **Step 1: Write the failing test**

An `apps/studio/tests/Feature/Gallery/GalleryRecordTest.php` anhängen:

```php
it('sets the three offers from the permissions tab', function () {
    $gallery = managedGallery();

    expect(props("/galerien/{$gallery->public_id}")['gallery']['offers']['downloads'])->toBeTrue();

    test()->patch("/galerien/{$gallery->public_id}", ['downloads_enabled' => false])->assertRedirect();

    expect($gallery->fresh()->downloads_enabled)->toBeFalse()
        // Untouched keys stay untouched: the tab saves one row at a time.
        ->and($gallery->fresh()->lists_enabled)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='sets the three offers'`
Expected: FAIL — `Undefined array key "offers"`

- [ ] **Step 3: Hand the offers to the record screen**

In `GalleryRecordController::show`, im `'gallery' => [...]`-Array unter `'design'`:

```php
                'offers' => [
                    'downloads' => $gallery->downloads_enabled,
                    'lists' => $gallery->lists_enabled,
                    'comments' => $gallery->comments_enabled,
                ],
```

- [ ] **Step 4: Accept them in update()**

In `GalleryRecordController::update`, die Validierung erweitern:

```php
            'downloads_enabled' => ['sometimes', 'boolean'],
            'lists_enabled' => ['sometimes', 'boolean'],
            'comments_enabled' => ['sometimes', 'boolean'],
```

und nach dem `design`-Block:

```php
        $offers = array_intersect_key($validated, array_flip([
            'downloads_enabled', 'lists_enabled', 'comments_enabled',
        ]));

        if ($offers !== []) {
            $gallery->update($offers);
        }
```

- [ ] **Step 5: Run the test**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='sets the three offers'`
Expected: PASS

- [ ] **Step 6: Add the checkboxes to the permissions tab**

In `apps/studio/resources/js/admin/pages/Galleries/Record.vue`, den `gallery`-Prop-Typ um `offers: { downloads: boolean; lists: boolean; comments: boolean }` ergänzen, unter dem `design`-Formular ein zweites anlegen:

```ts
const offers = useForm({
  downloads_enabled: props.gallery.offers.downloads,
  lists_enabled: props.gallery.offers.lists,
  comments_enabled: props.gallery.offers.comments,
})

function saveOffers() {
  offers.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}
```

und im `freigaben`-Tab, unter der E-Mail-Tor-Zeile und über `<h2>{{ $t('gallery.listsTitle') }}</h2>`:

```html
        <div class="mt-7 flex flex-col gap-2.5">
          <div class="flex items-center gap-2.5">
            <Checkbox id="cb-offer-downloads" v-model="offers.downloads_enabled" @update:model-value="saveOffers" />
            <Label variant="inline" for="cb-offer-downloads">{{ $t('gallerySettings.downloadsLabel') }}</Label>
          </div>
          <div class="flex items-center gap-2.5">
            <Checkbox id="cb-offer-lists" v-model="offers.lists_enabled" @update:model-value="saveOffers" />
            <Label variant="inline" for="cb-offer-lists">{{ $t('gallerySettings.listsLabel') }}</Label>
          </div>
          <div class="flex items-center gap-2.5">
            <Checkbox id="cb-offer-comments" v-model="offers.comments_enabled" @update:model-value="saveOffers" />
            <Label variant="inline" for="cb-offer-comments">{{ $t('gallerySettings.commentsLabel') }}</Label>
          </div>
        </div>
```

Imports ergänzen: `import { Checkbox } from '@/ui/checkbox'` und `import { Label } from '@/ui/label'`.

Die drei Beschriftungen stehen bereits als `gallerySettings.downloadsLabel`, `listsLabel`, `commentsLabel` in `de.json` — wiederverwenden, nicht verdoppeln.

- [ ] **Step 7: Typecheck**

Run: `pnpm lint`
Expected: keine Fehler.

- [ ] **Step 8: Commit**

```bash
git add apps/studio/app/Gallery/Http/Controllers/GalleryRecordController.php \
        apps/studio/resources/js/admin/pages/Galleries/Record.vue \
        apps/studio/tests/Feature/Gallery/GalleryRecordTest.php
git commit -m "feat(gallery): Angebote im Freigaben-Tab schalten"
```

---

### Task 4: Eine Ablaufquelle statt zwei

**Files:**
- Delete: `apps/studio/config/gallery.php`
- Modify: `apps/studio/app/Gallery/Models/Gallery.php:123-149`
- Modify: `apps/studio/app/Gallery/Actions/ImportLegacyGallery.php:130-138`
- Test: `apps/studio/tests/Feature/Gallery/GalleryExpiryTest.php`

**Interfaces:**
- Consumes: `StudioSettingsResolver::galleryDefaults()['expiryDays']`.
- Produces: `Gallery::publish()` stempelt `expires_at` aus dem Studio-Setting. `ImportLegacyGallery::applyDefaultExpiry()` entfällt.

- [ ] **Step 1: Read the existing suite first**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryExpiryTest.php`
Expected: PASS. Lies die Datei, bevor du sie änderst — sie beschreibt das heutige Verhalten mit `config('gallery.retention_days')`, und dieselben Zusicherungen müssen danach mit dem Setting gelten.

- [ ] **Step 2: Write the failing test**

An `apps/studio/tests/Feature/Gallery/GalleryExpiryTest.php` anhängen:

```php
// Zwei Quellen für dieselbe Frist waren eine zu viel: config/gallery.php
// begründete, warum es kein Setting sein solle, während das Setting bereits
// existierte und in Settings/Galleries.vue editiert wurde.
it('stamps the expiry from the studio setting, not from a config value', function () {
    app(StudioSettingsResolver::class)->set('gallery_default_expiry_days', 30, null);

    $gallery = Gallery::factory()->create(['assets' => [
        ['key' => 'galleries/g1/a.jpg', 'width' => 200, 'height' => 100],
    ]]);
    $gallery->publish();

    expect($gallery->fresh()->expires_at->toDateString())
        ->toBe(now()->addDays(30)->toDateString());
});

it('leaves a gallery without a studio default standing indefinitely', function () {
    app(StudioSettingsResolver::class)->set('gallery_default_expiry_days', null, null);

    $gallery = Gallery::factory()->create(['assets' => [
        ['key' => 'galleries/g1/a.jpg', 'width' => 200, 'height' => 100],
    ]]);
    $gallery->publish();

    expect($gallery->fresh()->expires_at)->toBeNull();
});
```

Import oben in der Datei ergänzen: `use App\Settings\Support\StudioSettingsResolver;`

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='stamps the expiry from the studio setting'`
Expected: FAIL — `expires_at` ist null, weil `publish()` noch die Config liest.

- [ ] **Step 4: Read the setting in publish()**

In `apps/studio/app/Gallery/Models/Gallery.php::publish()` die ersten Zeilen der Transaktion ersetzen:

```php
        return DB::transaction(function () {
            $retention = app(StudioSettingsResolver::class)->galleryDefaults()['expiryDays'];

            if ($retention !== null && $this->expires_at === null) {
                // Stamped once, on first publish. A second version does not
                // restart the clock: the client has had the photographs since
                // version one, and a re-publish to fix a caption is not a new
                // delivery.
                $this->update(['expires_at' => now()->addDays((int) $retention)]);
            }
```

Import ergänzen: `use App\Settings\Support\StudioSettingsResolver;`

- [ ] **Step 5: Drop the now-duplicate helper in the import action**

In `apps/studio/app/Gallery/Actions/ImportLegacyGallery.php` die Methode `applyDefaultExpiry()` und ihre beiden Aufrufe löschen — `publish()` erledigt dieselbe Arbeit eine Zeile später, aus derselben Quelle.

- [ ] **Step 6: Delete the config file**

```bash
git rm apps/studio/config/gallery.php
```

- [ ] **Step 7: Run the suites**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery`
Expected: PASS. Ein Test, der `config(['gallery.retention_days' => …])` setzt, wird auf `StudioSettingsResolver::set()` umgestellt — die Zusicherung bleibt Wort für Wort.

- [ ] **Step 8: Commit**

```bash
git add apps/studio/app/Gallery/Models/Gallery.php \
        apps/studio/app/Gallery/Actions/ImportLegacyGallery.php \
        apps/studio/config/gallery.php \
        apps/studio/tests/Feature/Gallery/GalleryExpiryTest.php
git commit -m "refactor(gallery): Ablauffrist hat eine Quelle" -m "config/gallery.php begruendete, warum die Frist kein Studio-Setting sei,
waehrend gallery_default_expiry_days seit 2026_08_16 existierte und im
Einstellungsscreen editiert wurde."
```

---

### Task 5: Die Galerie-Migrationen zusammenführen, Legacy-Hashes streichen

**Files:**
- Modify: `apps/studio/database/migrations/2026_08_09_003100_create_galleries_table.php`
- Delete: `apps/studio/database/migrations/2026_08_09_003300_add_external_link_to_galleries_table.php`
- Delete: `apps/studio/database/migrations/2026_08_09_003700_add_access_gates_to_galleries_table.php`
- Delete: `apps/studio/database/migrations/2026_08_15_000200_add_expires_at_to_galleries_table.php`
- Delete: `apps/studio/database/migrations/2026_08_18_000300_store_gallery_secrets_recoverably.php`
- Delete: `apps/studio/database/migrations/2026_08_18_000600_give_a_gallery_sets_and_a_look.php`
- Modify: `apps/studio/database/migrations/2026_08_16_000300_create_gallery_lists_tables.php:58-70`
- Modify: `apps/studio/app/Gallery/Models/Gallery.php:60-98`, `:151-200`
- Test: `apps/studio/tests/Feature/Gallery/GalleryAccessGatesTest.php`

**Interfaces:**
- Consumes: nichts.
- Produces: `galleries` entsteht in einer Migration. `view_password_hash` und `download_pin_hash` existieren nicht mehr. `Gallery::check()` ist entfernt; `checkViewPassword(string): bool` und `checkDownloadPin(string): bool` bleiben mit unveränderter Signatur.

- [ ] **Step 1: Write the failing test**

An `apps/studio/tests/Feature/Gallery/GalleryAccessGatesTest.php` anhängen:

```php
// Ohne Bestandsdaten hält das Argument nicht mehr, das die Hash-Spalten
// gehalten hat — sie waren der Rückfallpfad für Galerien, die vor C-17 ein
// Tor bekamen.
it('carries no legacy hash columns', function () {
    expect(Schema::hasColumn('galleries', 'view_password_hash'))->toBeFalse()
        ->and(Schema::hasColumn('galleries', 'download_pin_hash'))->toBeFalse();
});
```

Import ergänzen: `use Illuminate\Support\Facades\Schema;`

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='carries no legacy hash columns'`
Expected: FAIL — beide Spalten sind da.

- [ ] **Step 3: Fold the five migrations into the create**

Öffne jede der fünf zu löschenden Migrationen, übernimm ihre Spalten **samt ihrer Doc-Kommentare** in `2026_08_09_003100_create_galleries_table.php`, in dieser Reihenfolge nach `assets`:

```php
            // X-02: eine Galerie, die anderswo liegt — die Verwaltung führt
            // sie, gehostet wird sie nicht hier.
            $table->boolean('is_external')->default(false);
            $table->string('external_url')->nullable();

            // G-06's three gates. A null secret means the gate is off — no
            // separate "required" flag to drift out of sync with it. Every
            // gate carries its own version, bumped on every rotate() call and
            // nothing else; that counter, not the secret itself, is what a
            // granted claim is checked against (App\Gallery\Support\
            // GalleryAccessSession), so rotating one gate invalidates only
            // the claims minted before it.
            //
            // C-17 (#253): stored recoverably and shown in the admin in
            // clear. Both values are handed to guests anyway — by mail, by
            // WhatsApp, over the phone. A studio that cannot look one up sets
            // a new one on every enquiry and locks out everybody holding the
            // old. What it costs, unvarnished: a database dump now contains
            // every gallery password and PIN. Encryption with APP_KEY defends
            // against the stolen dump, not against an attacker who can run
            // the application. Staff login passwords and
            // client_accesses.token_hash are not covered by this and stay
            // hashes — they are never shown.
            $table->text('view_password')->nullable();
            $table->unsignedInteger('view_password_version')->default(1);

            $table->text('download_pin')->nullable();
            $table->unsignedInteger('download_pin_version')->default(1);

            // The email gate has no secret to hash — it establishes identity,
            // not possession — so it keeps the boolean the other two don't
            // need. The version still exists: rotating it forces every
            // already-verified guest to reverify.
            $table->boolean('requires_verified_email')->default(false);
            $table->unsignedInteger('email_verification_version')->default(1);

            $table->timestampTz('expires_at')->nullable();

            // C-19 (#255): cover variant, typeface, colour and grid. Null
            // means "as the studio has it", so a studio changing its own
            // default still reaches every gallery that never overrode one.
            $table->jsonb('design')->nullable();

            // P-06's "Aufrufe". DownloadEvent has only ever counted
            // downloads, so nothing told a couple whether the link they
            // shared was opened at all. Counted once per visitor session
            // rather than per request — see GalleryPasswordController.
            $table->unsignedInteger('view_count')->default(0);
```

`view_password_hash` und `download_pin_hash` werden **nicht** übernommen. Prüfe die Originaldateien auf weitere Details (Indizes, `after()`-Positionen, Kommentare), bevor du sie löschst — `after()` entfällt in der Create-Migration, alles andere wandert mit.

- [ ] **Step 4: Delete the five migrations and trim the sixth**

```bash
git rm apps/studio/database/migrations/2026_08_09_003300_add_external_link_to_galleries_table.php \
       apps/studio/database/migrations/2026_08_09_003700_add_access_gates_to_galleries_table.php \
       apps/studio/database/migrations/2026_08_15_000200_add_expires_at_to_galleries_table.php \
       apps/studio/database/migrations/2026_08_18_000300_store_gallery_secrets_recoverably.php \
       apps/studio/database/migrations/2026_08_18_000600_give_a_gallery_sets_and_a_look.php
```

In `2026_08_16_000300_create_gallery_lists_tables.php` den `Schema::table('galleries', …)`-Block aus `up()` und die `dropColumn('view_count')`-Zeile aus `down()` entfernen. Die beiden Listen-Tabellen bleiben unangetastet.

- [ ] **Step 5: Drop the fallback from the model**

In `apps/studio/app/Gallery/Models/Gallery.php`:

- aus `casts()` die beiden Zeilen `'view_password_hash' => 'hashed'` und `'download_pin_hash' => 'hashed'` entfernen, und den Kommentar darüber auf den verbleibenden Fall kürzen
- `private function check()` löschen
- die beiden Prüfer auf einen Wert reduzieren:

```php
    public function checkViewPassword(string $password): bool
    {
        return $this->view_password !== null && hash_equals($this->view_password, $password);
    }

    public function checkDownloadPin(string $pin): bool
    {
        return $this->download_pin !== null && hash_equals($this->download_pin, $pin);
    }
```

- `requiresViewPassword()` und `requiresDownloadPin()` auf je einen Wert:

```php
    public function requiresViewPassword(): bool
    {
        return $this->view_password !== null;
    }

    public function requiresDownloadPin(): bool
    {
        return $this->download_pin !== null;
    }
```

- in `rotateViewPassword()` und `rotateDownloadPin()` das jeweilige `…_hash => null` aus dem `forceFill` entfernen, samt dessen Kommentar
- `use Illuminate\Support\Facades\Hash;` entfernen, falls nichts mehr darauf zeigt

- [ ] **Step 6: Find every other reader of the hash columns**

Run: `grep -rn "view_password_hash\|download_pin_hash" apps/studio`
Expected: keine Treffer außer in gelöschten Dateien. Was übrig bleibt, wird mit derselben Regel entfernt.

- [ ] **Step 7: Run the suites**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery tests/Feature/Migration`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add apps/studio/database/migrations/2026_08_09_003100_create_galleries_table.php \
        apps/studio/database/migrations/2026_08_16_000300_create_gallery_lists_tables.php \
        apps/studio/app/Gallery/Models/Gallery.php \
        apps/studio/tests/Feature/Gallery/GalleryAccessGatesTest.php
git commit -a -m "refactor(gallery): Migrationen zusammenfuehren" -m "Sechs Nachtraege wandern in die Create-Migration, ihre Begruendungen mit.
Die Legacy-Hash-Spalten entfallen: sie hielten Galerien am Leben, die vor
C-17 ein Tor bekamen, und die gibt es ohne Bestandsdaten nicht."
```

---

### Task 6: Galerie ohne Projekt

**Files:**
- Modify: `apps/studio/database/migrations/2026_08_09_003100_create_galleries_table.php:16`
- Modify: `apps/studio/app/Policies/GalleryPolicy.php`
- Modify: `apps/studio/app/Media/Data/MediaIngestTargetData.php:19-37`
- Modify: `apps/studio/app/Gallery/Actions/ReadAfterDeliveryColumn.php:80-92`
- Modify: `apps/studio/resources/js/admin/pages/Galleries/Ingest.vue`
- Test: `apps/studio/tests/Feature/Gallery/GalleryWithoutProjectTest.php`

**Interfaces:**
- Consumes: nichts.
- Produces: `galleries.project_id` nullable. `GalleryPolicy::viewAny(User): bool`, `GalleryPolicy::create(User): bool`, `GalleryPolicy::manageMedia(User, Gallery): bool` mit Null-Zweig. `MediaIngestTargetData::$projectId` und `$projectName` sind `?string`.

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Gallery/GalleryWithoutProjectTest.php`:

```php
<?php

use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('stands without a project', function () {
    $gallery = Gallery::factory()->create(['project_id' => null]);

    expect($gallery->fresh()->project_id)->toBeNull();
});

// Ohne Projekt gibt es nichts, woran ein Zugriff hängen könnte — die
// Capability allein entscheidet, und das heißt: jede Rolle mit ManageShoots
// sieht jede projektlose Galerie des Studios.
it('lets anyone with ManageShoots manage a gallery that has no project', function () {
    $gallery = Gallery::factory()->create(['project_id' => null]);

    expect($this->staff->can('manageMedia', $gallery))->toBeTrue();
});

it('refuses a role without ManageShoots', function () {
    $gallery = Gallery::factory()->create(['project_id' => null]);
    $viewer = User::factory()->withMfa()->create(['role' => StaffRole::Freelancer]);

    expect($viewer->can('manageMedia', $gallery))->toBeFalse();
});

it('serves the ingest screen for a gallery with no project', function () {
    $gallery = Gallery::factory()->create(['project_id' => null]);

    $props = props("/galerien/{$gallery->public_id}/medien");

    expect($props['target']['projectId'])->toBeNull()
        ->and($props['target']['projectName'])->toBeNull();
});
```

Prüfe vor dem Lauf, welche Rolle in `App\Enums\StaffRole` `ManageShoots` **nicht** hat, und setze sie in den dritten Test ein — `Freelancer` ist die Annahme, nicht die verifizierte Antwort.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='GalleryWithoutProject'`
Expected: FAIL — NOT NULL violation auf `project_id`.

- [ ] **Step 3: Make the column nullable**

In `apps/studio/database/migrations/2026_08_09_003100_create_galleries_table.php`:

```php
            // Nullable: nicht jede Galerie gehört zu einer Buchung. Eine ohne
            // Projekt ist studiointern und außerhalb des Portals —
            // GalleryAccessSession bindet den Kundenzugang an
            // visibleProjects(), was ohne Projekt nie greift. Sie ist über
            // Link und Passwort erreichbar, in keiner Kundenansicht.
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
```

- [ ] **Step 4: Give the policy a null branch**

`apps/studio/app/Policies/GalleryPolicy.php` vollständig:

```php
<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Gallery\Models\Gallery;
use App\Models\User;

/**
 * Everything with a project routes through ProjectPolicy, the same shape
 * ShootPolicy already uses for Shoot: a gallery can never be reachable when
 * the project holding it is not.
 *
 * Without one there is nothing to route through, so the capability decides
 * alone — a role whose project view is narrowed still sees every projectless
 * gallery in the studio. That is the price of a gallery outside the bookings,
 * and the answer to disliking it is to stop making them, not to invent a
 * filter with nothing to filter on.
 */
class GalleryPolicy
{
    public function __construct(private readonly ProjectPolicy $projects) {}

    public function viewAny(User $user): bool
    {
        return $user->role->allows(Capability::ManageShoots);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Capability::ManageShoots);
    }

    /**
     * Reach the ingest screen, and start, resume, upload into, complete or
     * abort a multipart upload (G-02). Reuses ManageShoots rather than a new
     * capability — media ingest is shoot logistics, the same reasoning
     * ShootPolicy::manageShotList already applies to a shoot's checklist.
     */
    public function manageMedia(User $user, Gallery $gallery): bool
    {
        return $gallery->project === null
            ? $user->role->allows(Capability::ManageShoots)
            : $this->projects->manageShoots($user, $gallery->project);
    }
}
```

- [ ] **Step 5: Make the ingest target nullable**

In `apps/studio/app/Media/Data/MediaIngestTargetData.php`:

```php
    public function __construct(
        public string $galleryId,
        public string $galleryTitle,
        // Nullable seit eine Galerie ohne Buchung stehen darf; die "Ziel"-Karte
        // lässt die Zeile dann weg.
        public ?string $projectId,
        public ?string $projectName,
        public ?string $shootId,
        public ?string $shootLabel,
        public int $partSize,
    ) {}
```

und in `fromGallery`:

```php
            projectId: $gallery->project?->public_id,
            projectName: $gallery->project?->name,
```

- [ ] **Step 6: Guard the dashboard column**

In `apps/studio/app/Gallery/Actions/ReadAfterDeliveryColumn.php` die Abfrage so einschränken, dass sie projektlose Galerien überspringt — sie gehören zu keiner Buchung und haben in einer Spalte über Buchungen nichts verloren:

```php
            ->whereNotNull('project_id')
```

vor dem `get()` der Galerie-Abfrage. Danach bleiben `$gallery->project->public_id` und `->name` in Zeile 86-87 korrekt.

- [ ] **Step 7: Hide the target row when there is no project**

In `apps/studio/resources/js/admin/pages/Galleries/Ingest.vue` die Projektzeile der „Ziel"-Karte mit `v-if="target.projectName"` versehen. Kein Ersatztext an ihrer Stelle.

- [ ] **Step 8: Regenerate types**

Run: `pnpm php php artisan typescript:transform`
Then: `pnpm lint`
Expected: keine Fehler.

- [ ] **Step 9: Run the tests**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery tests/Feature/Media`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add apps/studio/database/migrations/2026_08_09_003100_create_galleries_table.php \
        apps/studio/app/Policies/GalleryPolicy.php \
        apps/studio/app/Media/Data/MediaIngestTargetData.php \
        apps/studio/app/Gallery/Actions/ReadAfterDeliveryColumn.php \
        apps/studio/resources/js/admin/pages/Galleries/Ingest.vue \
        apps/studio/resources/js/types/generated.d.ts \
        apps/studio/tests/Feature/Gallery/GalleryWithoutProjectTest.php
git commit -m "feat(gallery): Galerie ohne Projekt zulassen"
```

---

### Task 7: Index und Nav-Eintrag

**Files:**
- Create: `apps/studio/app/Gallery/Data/GalleryRowData.php`
- Create: `apps/studio/app/Gallery/Http/Controllers/GalleryIndexController.php`
- Create: `apps/studio/resources/js/admin/pages/Galleries/Index.vue`
- Modify: `apps/studio/routes/admin.php:432-440`
- Modify: `apps/studio/resources/js/admin/layouts/AdminLayout.vue:35-80`
- Modify: `apps/studio/resources/js/locales/de.json`
- Test: `apps/studio/tests/Feature/Gallery/GalleryIndexTest.php`

**Interfaces:**
- Consumes: `GalleryPolicy::viewAny` aus Task 6.
- Produces: Route `GET /galerien` → `galleries.index`, Inertia-Seite `Galleries/Index` mit Prop `galleries: App.Gallery.Data.GalleryRowData[]`. `GalleryRowData(id, title, projectName, assetCount, version, expiresAt)`.

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Gallery/GalleryIndexTest.php`:

```php
<?php

use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('lists a gallery with its booking, its count and its version', function () {
    $project = Project::factory()->create(['name' => 'Basthorst 2026']);
    $gallery = Gallery::factory()->for($project)->create([
        'title' => 'Trauung',
        'assets' => [['key' => 'galleries/g1/a.jpg', 'width' => 200, 'height' => 100]],
    ]);
    $gallery->publish();

    $row = collect(props('/galerien')['galleries'])->firstWhere('id', $gallery->public_id);

    expect($row['title'])->toBe('Trauung')
        ->and($row['projectName'])->toBe('Basthorst 2026')
        ->and($row['assetCount'])->toBe(1)
        ->and($row['version'])->toBe(1);
});

it('puts the newest gallery first', function () {
    $older = Gallery::factory()->create(['created_at' => now()->subDay()]);
    $newer = Gallery::factory()->create();

    $ids = collect(props('/galerien')['galleries'])->pluck('id')->all();

    expect(array_search($newer->public_id, $ids, true))
        ->toBeLessThan(array_search($older->public_id, $ids, true));
});

// Die eine Ausnahme von der Projektsichtbarkeit, ausdrücklich: es gibt nichts,
// woran ein Filter greifen könnte.
it('shows a projectless gallery to every role that may manage media', function () {
    $orphan = Gallery::factory()->create(['project_id' => null]);

    expect(collect(props('/galerien')['galleries'])->pluck('id'))
        ->toContain($orphan->public_id);
});

it('hides a gallery whose booking this role may not see', function () {
    $hidden = Gallery::factory()->for(Project::factory())->create();
    $narrow = User::factory()->withMfa()->create(['role' => StaffRole::Assistant]);
    $this->actingAs($narrow, 'staff');

    expect(collect(props('/galerien')['galleries'])->pluck('id'))
        ->not->toContain($hidden->public_id);
});
```

Der letzte Test setzt eine Rolle mit `ManageShoots`, aber ohne `ViewAllProjects` voraus. Lies `App\Enums\StaffRole` und `App\Policies\ProjectPolicy::view()`, bevor du `Assistant` stehen lässt — trifft keine Rolle beides, entfällt dieser Test und der Kommentar dazu wandert in den Test darüber.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='GalleryIndex'`
Expected: FAIL — 404 auf `/galerien`.

- [ ] **Step 3: Write the row DTO**

Create `apps/studio/app/Gallery/Data/GalleryRowData.php`:

```php
<?php

namespace App\Gallery\Data;

use App\Gallery\Models\Gallery;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class GalleryRowData extends Data
{
    public function __construct(
        public string $id,
        public ?string $title,
        public ?string $projectName,
        public int $assetCount,
        public ?int $version,
        public ?string $expiresAt,
    ) {}

    public static function fromModel(Gallery $gallery): self
    {
        return new self(
            id: $gallery->public_id,
            title: $gallery->title,
            projectName: $gallery->project?->name,
            assetCount: count($gallery->assets ?? []),
            version: $gallery->currentVersion?->version,
            expiresAt: $gallery->expires_at?->format('Y-m-d'),
        );
    }
}
```

- [ ] **Step 4: Write the controller**

Create `apps/studio/app/Gallery/Http/Controllers/GalleryIndexController.php`:

```php
<?php

namespace App\Gallery\Http\Controllers;

use App\Crm\Models\Project;
use App\Gallery\Data\GalleryRowData;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Die Liste, die es bis #255 nicht gab: eine Galerie war nur über ihr Projekt
 * oder ihren Kontakt erreichbar, und in der Nav stand sie gar nicht.
 *
 * Heißt GalleryIndexController und nicht GalleryController, weil den Namen
 * bereits App\Portal\Http\Controllers\GalleryController trägt und, für
 * dieselbe Entität, GalleryRecordController.
 */
class GalleryIndexController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Gallery::class);

        $galleries = Gallery::query()
            ->with(['project', 'currentVersion'])
            // Die eine Ausnahme von der Projektsichtbarkeit: eine Galerie ohne
            // Buchung hat nichts, woran ein Filter greifen könnte.
            ->where(fn ($query) => $query
                ->whereNull('project_id')
                ->orWhereIn('project_id', Project::query()->visibleTo(auth('staff')->user())->select('id')))
            ->latest()
            ->get();

        return Inertia::render('Galleries/Index', [
            'galleries' => GalleryRowData::collect($galleries),
        ]);
    }
}
```

Prüfe die Signatur von `Project::visibleTo()` in `apps/studio/app/Crm/Models/Project.php`, bevor du das übernimmst — der Scope existiert (`HandleInertiaRequests:73` nutzt ihn), sein Parameter muss aber passen.

- [ ] **Step 5: Register the route**

In `apps/studio/routes/admin.php`, unmittelbar **über** `Route::get('/galerien/{gallery}', …)`:

```php
        // /galerien/neu bleibt über /galerien/{gallery}, aus demselben Grund
        // wie /pakete/neu über /pakete/{package}.
        Route::get('/galerien', [GalleryIndexController::class, 'index'])->name('galleries.index');
```

Import ergänzen.

- [ ] **Step 6: Write the index screen**

Create `apps/studio/resources/js/admin/pages/Galleries/Index.vue`:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'

defineProps<{ galleries: App.Gallery.Data.GalleryRowData[] }>()

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'
}
</script>

<template>
  <AdminLayout active="galerien">
    <div data-tokens>
      <PageHead :title="$t('galleries.title')">
        <Button :as="Link" variant="pill" class="ml-auto" href="/galerien/neu">{{ $t('galleries.create') }}</Button>
      </PageHead>

      <div class="max-w-[900px] px-8 pt-7 pb-8">
        <div
          v-for="gallery in galleries"
          :key="gallery.id"
          class="flex items-baseline gap-3.5 py-3 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
        >
          <span class="min-w-0 flex-1 text-[13.5px] leading-[1.47]">
            <Link :href="`/galerien/${gallery.id}`">{{ gallery.title ?? '—' }}</Link>
            <span v-if="gallery.projectName" class="text-[12px] text-ink-2"> · {{ gallery.projectName }}</span>
          </span>
          <span class="w-14 flex-none text-right text-[12.5px] tabular-nums text-ink-2">{{ gallery.assetCount }}</span>
          <span class="w-20 flex-none text-right text-[12.5px] tabular-nums text-ink-2">
            {{ gallery.version ? $t('galleryRecord.version', { version: gallery.version }) : '—' }}
          </span>
          <span class="w-20 flex-none text-right text-[12.5px] tabular-nums text-ink-2">{{ day(gallery.expiresAt) }}</span>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

Kein Leerzustandstext: bleibt die Liste leer, steht der Knopf im Kopf allein da, und das ist die Aussage.

- [ ] **Step 7: Add the nav entry**

In `apps/studio/resources/js/admin/layouts/AdminLayout.vue`, im `items`-Array direkt **nach** dem `projekte`-Eintrag:

```js
        {
            key: "galerien",
            label: "Galerien",
            href: "/galerien",
            needs: "manage_shoots",
            icon: '<rect x="3" y="3" width="13" height="13" rx="2"/><path d="M8 21h11a2 2 0 0 0 2-2V8"/>',
        },
```

Kein `count`: die vorhandenen Zähler zählen alle etwas Offenes, das Aufmerksamkeit verlangt.

Den überholten Absatz im Kommentar bei `AdminLayout.vue:41-43` streichen — „Galerien is in the draft's own sidebar and absent here … the admin gallery record is #254" beschreibt einen Zustand, den dieser Commit beendet.

- [ ] **Step 8: Add the copy**

In `apps/studio/resources/js/locales/de.json`, ein neuer Block `galleries` in alphabetischer Nachbarschaft zu `gallery`:

```json
  "galleries": {
    "title": "Galerien",
    "create": "Galerie anlegen"
  },
```

- [ ] **Step 9: Regenerate types and run everything**

Run: `pnpm php php artisan typescript:transform`
Then: `pnpm lint`
Then: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='GalleryIndex'`
Expected: alles PASS.

- [ ] **Step 10: Commit**

```bash
git add apps/studio/app/Gallery/Data/GalleryRowData.php \
        apps/studio/app/Gallery/Http/Controllers/GalleryIndexController.php \
        apps/studio/resources/js/admin/pages/Galleries/Index.vue \
        apps/studio/routes/admin.php \
        apps/studio/resources/js/admin/layouts/AdminLayout.vue \
        apps/studio/resources/js/locales/de.json \
        apps/studio/resources/js/types/generated.d.ts \
        apps/studio/tests/Feature/Gallery/GalleryIndexTest.php
git commit -m "feat(gallery): Galerienliste und Nav-Eintrag"
```

---

### Task 8: Benannte Vorlagen

**Files:**
- Create: `apps/studio/database/migrations/2026_08_22_000100_create_gallery_presets_table.php`
- Create: `apps/studio/app/Gallery/Models/GalleryPreset.php`
- Create: `apps/studio/app/Gallery/Data/GalleryPresetData.php`
- Create: `apps/studio/app/Gallery/Http/Controllers/GalleryPresetController.php`
- Create: `apps/studio/database/factories/Gallery/GalleryPresetFactory.php`
- Create: `apps/studio/resources/js/admin/pages/Settings/GalleryPresets.vue`
- Modify: `apps/studio/routes/admin.php:333-336`
- Modify: `apps/studio/resources/js/admin/components/SettingsNav.vue:31-37`
- Modify: `apps/studio/resources/js/locales/de.json`
- Test: `apps/studio/tests/Feature/Settings/GalleryPresetTest.php`

**Interfaces:**
- Consumes: nichts.
- Produces: Model `App\Gallery\Models\GalleryPreset` mit `name`, `design`, `expiry_mode`, `expiry_days`, `requires_settled_balance`, `downloads_enabled`, `lists_enabled`, `comments_enabled`. DTO `App\Gallery\Data\GalleryPresetData(id, name, design, expiryMode, expiryDays, requiresSettledBalance, downloadsEnabled, listsEnabled, commentsEnabled)`. Routen `gallery-presets.index|store|update|destroy`.

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Settings/GalleryPresetTest.php`:

```php
<?php

use App\Enums\StaffRole;
use App\Gallery\Models\GalleryPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create(['role' => StaffRole::Owner]);
    $this->actingAs($this->staff, 'staff');
});

it('creates a preset', function () {
    $this->post('/einstellungen/galerie-vorlagen', [
        'name' => 'Hochzeit',
        'expiry_mode' => 'days',
        'expiry_days' => 365,
        'requires_settled_balance' => true,
        'downloads_enabled' => true,
        'lists_enabled' => null,
        'comments_enabled' => false,
        'design' => ['cover' => 'full', 'type' => 'serif', 'colour' => 'light', 'grid' => 'wide'],
    ])->assertRedirect('/einstellungen/galerie-vorlagen');

    $preset = GalleryPreset::sole();

    expect($preset->name)->toBe('Hochzeit')
        ->and($preset->expiry_days)->toBe(365)
        // Dreiwertig: was die Vorlage offen lässt, überschreibt nichts.
        ->and($preset->lists_enabled)->toBeNull()
        ->and($preset->comments_enabled)->toBeFalse();
});

it('renames and deletes one', function () {
    $preset = GalleryPreset::factory()->create(['name' => 'Portrait']);

    $this->patch("/einstellungen/galerie-vorlagen/{$preset->public_id}", [
        'name' => 'Business',
        'expiry_mode' => 'inherit',
        'expiry_days' => null,
        'requires_settled_balance' => null,
        'downloads_enabled' => null,
        'lists_enabled' => null,
        'comments_enabled' => null,
        'design' => null,
    ])->assertRedirect();

    expect($preset->fresh()->name)->toBe('Business');

    $this->delete("/einstellungen/galerie-vorlagen/{$preset->public_id}")->assertRedirect();

    expect(GalleryPreset::count())->toBe(0);
});

it('refuses a mode the resolution does not know', function () {
    $this->post('/einstellungen/galerie-vorlagen', [
        'name' => 'Kaputt',
        'expiry_mode' => 'irgendwann',
    ])->assertSessionHasErrors('expiry_mode');
});

// Der 404-statt-403-Riegel, den AuthorizesStaffSettings für jeden
// Einstellungsscreen setzt.
it('answers 404 for a role without ManageSettings', function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Freelancer]), 'staff');

    $this->get('/einstellungen/galerie-vorlagen')->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='GalleryPreset'`
Expected: FAIL — Klasse `GalleryPreset` existiert nicht.

- [ ] **Step 3: Write the migration**

Create `apps/studio/database/migrations/2026_08_22_000100_create_gallery_presets_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eine benannte Startbelegung für neue Galerien. Kein Fremdschlüssel auf
     * galleries und keiner von dort hierher: die Vorlage füllt beim Anlegen
     * das Formular und ist danach nicht mehr verbunden. Eine geänderte
     * Vorlage erreicht bestehende Galerien nicht — sonst verfiele eine längst
     * ausgelieferte Galerie rückwirkend, weil jemand eine Frist korrigiert.
     */
    public function up(): void
    {
        Schema::create('gallery_presets', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('name');

            // C-19's vier Gestaltungsschlüssel, in derselben Form, in der die
            // Galerie sie trägt.
            $table->jsonb('design')->nullable();

            // Drei Zustände, und expires_at kann sie nicht tragen: dort
            // bedeutet null bereits "kein Ablauf", ein zweites null für
            // "erben" wäre nicht mehr lesbar.
            $table->string('expiry_mode')->default('inherit');
            $table->unsignedSmallInteger('expiry_days')->nullable();

            // Dreiwertig: null heißt erben. Eine Vorlage, die zu einem Wert
            // nichts sagt, überschreibt die Ebene darunter nicht.
            $table->boolean('requires_settled_balance')->nullable();
            $table->boolean('downloads_enabled')->nullable();
            $table->boolean('lists_enabled')->nullable();
            $table->boolean('comments_enabled')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_presets');
    }
};
```

- [ ] **Step 4: Write the model and the factory**

Create `apps/studio/app/Gallery/Models/GalleryPreset.php`:

```php
<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Gallery\GalleryPresetFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Womit eine neue Galerie geboren wird. Trägt keine Wirkung: gelesen wird die
 * Vorlage einmal, vom Anlegen-Formular, das ihre Werte in die Felder schreibt.
 */
class GalleryPreset extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    public const MODES = ['inherit', 'days', 'never'];

    protected $fillable = [
        'name', 'design', 'expiry_mode', 'expiry_days',
        'requires_settled_balance', 'downloads_enabled', 'lists_enabled', 'comments_enabled',
    ];

    protected function casts(): array
    {
        return [
            'design' => 'array',
            'requires_settled_balance' => 'boolean',
            'downloads_enabled' => 'boolean',
            'lists_enabled' => 'boolean',
            'comments_enabled' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return GalleryPresetFactory::new();
    }
}
```

Prüfe an `apps/studio/app/Gallery/Models/Gallery.php`, wie dort die Factory verdrahtet ist, und folge demselben Weg — `newFactory()` oder Namenskonvention, je nachdem was das Repo tut.

Create `apps/studio/database/factories/Gallery/GalleryPresetFactory.php`:

```php
<?php

namespace Database\Factories\Gallery;

use App\Gallery\Models\GalleryPreset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryPreset>
 */
class GalleryPresetFactory extends Factory
{
    protected $model = GalleryPreset::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'expiry_mode' => 'inherit',
        ];
    }
}
```

- [ ] **Step 5: Write the DTO**

Create `apps/studio/app/Gallery/Data/GalleryPresetData.php`:

```php
<?php

namespace App\Gallery\Data;

use App\Gallery\Models\GalleryPreset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class GalleryPresetData extends Data
{
    /** @param  array<string, string>|null  $design */
    public function __construct(
        public string $id,
        public string $name,
        public ?array $design,
        public string $expiryMode,
        public ?int $expiryDays,
        public ?bool $requiresSettledBalance,
        public ?bool $downloadsEnabled,
        public ?bool $listsEnabled,
        public ?bool $commentsEnabled,
    ) {}

    public static function fromModel(GalleryPreset $preset): self
    {
        return new self(
            id: $preset->public_id,
            name: $preset->name,
            design: $preset->design,
            expiryMode: $preset->expiry_mode,
            expiryDays: $preset->expiry_days,
            requiresSettledBalance: $preset->requires_settled_balance,
            downloadsEnabled: $preset->downloads_enabled,
            listsEnabled: $preset->lists_enabled,
            commentsEnabled: $preset->comments_enabled,
        );
    }
}
```

- [ ] **Step 6: Write the controller**

Create `apps/studio/app/Gallery/Http/Controllers/GalleryPresetController.php`:

```php
<?php

namespace App\Gallery\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Gallery\Data\GalleryPresetData;
use App\Gallery\Models\GalleryPreset;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Eigener Screen neben den Studio-Standardwerten, nicht ein zweiter Abschnitt
 * auf ihnen: eine Vorlage ist eine benannte Sache, von der es mehrere gibt,
 * und die Rail führt sie wie Aufgaben- und Fragebogenvorlagen.
 */
class GalleryPresetController extends Controller
{
    use AuthorizesStaffSettings;

    public function index(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/GalleryPresets', [
            'presets' => GalleryPresetData::collect(GalleryPreset::query()->orderBy('name')->get()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $preset = GalleryPreset::create($this->validated($request));

        AuditLogger::record(
            event: 'gallery_preset.created',
            actor: auth('staff')->user(),
            context: ['name' => $preset->name],
            subject: $preset->public_id,
        );

        return redirect('/einstellungen/galerie-vorlagen');
    }

    public function update(Request $request, GalleryPreset $galleryPreset): RedirectResponse
    {
        $this->authorizeSettings();

        $galleryPreset->update($this->validated($request));

        return redirect('/einstellungen/galerie-vorlagen');
    }

    public function destroy(GalleryPreset $galleryPreset): RedirectResponse
    {
        $this->authorizeSettings();

        AuditLogger::record(
            event: 'gallery_preset.deleted',
            actor: auth('staff')->user(),
            context: ['name' => $galleryPreset->name],
            subject: $galleryPreset->public_id,
        );

        $galleryPreset->delete();

        return redirect('/einstellungen/galerie-vorlagen');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'expiry_mode' => ['required', Rule::in(GalleryPreset::MODES)],
            'expiry_days' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_if:expiry_mode,days'],
            'requires_settled_balance' => ['nullable', 'boolean'],
            'downloads_enabled' => ['nullable', 'boolean'],
            'lists_enabled' => ['nullable', 'boolean'],
            'comments_enabled' => ['nullable', 'boolean'],
            'design' => ['nullable', 'array'],
            'design.cover' => ['nullable', 'string', 'max:32'],
            'design.type' => ['nullable', 'string', 'max:32'],
            'design.colour' => ['nullable', 'string', 'max:32'],
            'design.grid' => ['nullable', 'string', 'max:32'],
        ]);
    }
}
```

- [ ] **Step 7: Register the routes**

In `apps/studio/routes/admin.php`, direkt unter dem `settings.galleries.*`-Paar:

```php
        Route::get('/einstellungen/galerie-vorlagen', [GalleryPresetController::class, 'index'])
            ->name('gallery-presets.index');
        Route::post('/einstellungen/galerie-vorlagen', [GalleryPresetController::class, 'store'])
            ->name('gallery-presets.store');
        Route::patch('/einstellungen/galerie-vorlagen/{galleryPreset}', [GalleryPresetController::class, 'update'])
            ->name('gallery-presets.update');
        Route::delete('/einstellungen/galerie-vorlagen/{galleryPreset}', [GalleryPresetController::class, 'destroy'])
            ->name('gallery-presets.destroy');
```

- [ ] **Step 8: Write the settings screen**

Create `apps/studio/resources/js/admin/pages/Settings/GalleryPresets.vue`, nach dem Zeilenmuster von `Settings/TaskTemplates.vue` — ein `useForm` je Zeile, gehalten in einem nach Id geschlüsselten Objekt, plus ein `newForm`:

```vue
<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { ChoiceGroup } from '@/ui/choice-group'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { NativeSelect } from '@/ui/native-select'

const props = defineProps<{ presets: App.Gallery.Data.GalleryPresetData[] }>()

// Werte im Skript, Beschriftung im Template — dieselbe Aufteilung, die
// Record.vue:236 für COVERS, COLOURS und GRIDS anwendet.
const MODES = ['inherit', 'days', 'never']

// Drei Zustände brauchen drei Optionen: eine Checkbox kann "erben" nicht
// darstellen, und genau das ist der Punkt einer Vorlage, die nur einen Teil
// der Werte setzt.
const TRISTATE = [
  { value: '', key: 'inherit' },
  { value: '1', key: 'on' },
  { value: '0', key: 'off' },
]

function toTri(value: boolean | null) {
  return value === null ? '' : value ? '1' : '0'
}

function fromTri(value: string) {
  return value === '' ? null : value === '1'
}

const forms = Object.fromEntries(
  props.presets.map((preset) => [
    preset.id,
    useForm({
      name: preset.name,
      expiry_mode: preset.expiryMode,
      expiry_days: preset.expiryDays,
      requires_settled_balance: toTri(preset.requiresSettledBalance),
      downloads_enabled: toTri(preset.downloadsEnabled),
      lists_enabled: toTri(preset.listsEnabled),
      comments_enabled: toTri(preset.commentsEnabled),
      design: preset.design,
    }),
  ]),
)

function payload(data: Record<string, unknown>) {
  return {
    ...data,
    requires_settled_balance: fromTri(data.requires_settled_balance as string),
    downloads_enabled: fromTri(data.downloads_enabled as string),
    lists_enabled: fromTri(data.lists_enabled as string),
    comments_enabled: fromTri(data.comments_enabled as string),
  }
}

function save(id: string) {
  forms[id].transform(payload).patch(`/einstellungen/galerie-vorlagen/${id}`, { preserveScroll: true })
}

function remove(id: string) {
  router.delete(`/einstellungen/galerie-vorlagen/${id}`, { preserveScroll: true })
}

const newForm = useForm({
  name: '',
  expiry_mode: 'inherit',
  expiry_days: null as number | null,
  requires_settled_balance: '',
  downloads_enabled: '',
  lists_enabled: '',
  comments_enabled: '',
  design: null as Record<string, string> | null,
})

function create() {
  newForm.transform(payload).post('/einstellungen/galerie-vorlagen', {
    preserveScroll: true,
    onSuccess: () => newForm.reset(),
  })
}
</script>
```

Das Template dazu:

```vue
<template>
  <AdminLayout active="einstellungen">
    <div data-tokens>
      <PageHead :kicker="$t('settings.kicker')" :title="$t('galleryPresets.title')" />

      <div class="px-8 pt-7 pb-8">
        <div class="flex items-start gap-8">
          <SettingsNav active="galleryPresets" />

          <div class="min-w-0 max-w-[620px] flex-1">
            <div v-for="preset in presets" :key="preset.id" class="mt-8 first-of-type:mt-0">
              <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`name-${preset.id}`">{{ $t('galleryPresets.nameField') }}</Label>
                  <Input :id="`name-${preset.id}`" v-model="forms[preset.id].name" type="text" />
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`mode-${preset.id}`">{{ $t('galleryPresets.expiryField') }}</Label>
                  <NativeSelect :id="`mode-${preset.id}`" v-model="forms[preset.id].expiry_mode">
                    <option v-for="mode in MODES" :key="mode" :value="mode">{{ $t(`galleryPresets.mode_${mode}`) }}</option>
                  </NativeSelect>
                </div>

                <div v-if="forms[preset.id].expiry_mode === 'days'" class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`days-${preset.id}`">{{ $t('gallerySettings.expiryLabel') }}</Label>
                  <Input
                    :id="`days-${preset.id}`"
                    v-model="forms[preset.id].expiry_days"
                    class="tabular-nums"
                    type="number"
                    min="1"
                  />
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`balance-${preset.id}`">{{ $t('gallerySettings.balanceLabel') }}</Label>
                  <NativeSelect :id="`balance-${preset.id}`" v-model="forms[preset.id].requires_settled_balance">
                    <option v-for="state in TRISTATE" :key="state.value" :value="state.value">{{ $t(`galleryPresets.${state.key}`) }}</option>
                  </NativeSelect>
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`downloads-${preset.id}`">{{ $t('gallerySettings.downloadsLabel') }}</Label>
                  <NativeSelect :id="`downloads-${preset.id}`" v-model="forms[preset.id].downloads_enabled">
                    <option v-for="state in TRISTATE" :key="state.value" :value="state.value">{{ $t(`galleryPresets.${state.key}`) }}</option>
                  </NativeSelect>
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`lists-${preset.id}`">{{ $t('gallerySettings.listsLabel') }}</Label>
                  <NativeSelect :id="`lists-${preset.id}`" v-model="forms[preset.id].lists_enabled">
                    <option v-for="state in TRISTATE" :key="state.value" :value="state.value">{{ $t(`galleryPresets.${state.key}`) }}</option>
                  </NativeSelect>
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`comments-${preset.id}`">{{ $t('gallerySettings.commentsLabel') }}</Label>
                  <NativeSelect :id="`comments-${preset.id}`" v-model="forms[preset.id].comments_enabled">
                    <option v-for="state in TRISTATE" :key="state.value" :value="state.value">{{ $t(`galleryPresets.${state.key}`) }}</option>
                  </NativeSelect>
                </div>
              </div>

              <div class="mt-4 flex items-center gap-4">
                <Button type="button" @click="save(preset.id)">{{ $t('galleryPresets.save') }}</Button>
                <Button type="button" variant="pill-muted" @click="remove(preset.id)">{{ $t('galleryPresets.delete') }}</Button>
              </div>
            </div>

            <form class="mt-10 border-t border-border pt-8" @submit.prevent="create">
              <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label for="new-preset-name">{{ $t('galleryPresets.nameField') }}</Label>
                  <Input id="new-preset-name" v-model="newForm.name" type="text" />
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label for="new-preset-mode">{{ $t('galleryPresets.expiryField') }}</Label>
                  <NativeSelect id="new-preset-mode" v-model="newForm.expiry_mode">
                    <option v-for="mode in MODES" :key="mode" :value="mode">{{ $t(`galleryPresets.mode_${mode}`) }}</option>
                  </NativeSelect>
                </div>

                <div v-if="newForm.expiry_mode === 'days'" class="flex min-w-0 flex-col gap-1.5">
                  <Label for="new-preset-days">{{ $t('gallerySettings.expiryLabel') }}</Label>
                  <Input id="new-preset-days" v-model="newForm.expiry_days" class="tabular-nums" type="number" min="1" />
                </div>
              </div>

              <Button class="mt-4" type="submit" :disabled="newForm.name === ''">
                {{ $t('galleryPresets.add') }}
              </Button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

Die Neuanlage fragt nur Name und Ablauf ab; die drei Angebote stehen nach dem Speichern in der Zeile darüber und wären hier eine zweite, identische Feldgruppe.

- [ ] **Step 9: Add the settings nav group**

In `apps/studio/resources/js/admin/components/SettingsNav.vue` den `galleries`-Eintrag aus der Portal-Gruppe entfernen und ans Ende eine eigene Gruppe hängen:

```js
  { group: 'settings.groupGalleries' },
  { key: 'galleries', href: '/einstellungen/galerien', label: 'settings.navGalleryDefaults' },
  { key: 'galleryPresets', href: '/einstellungen/galerie-vorlagen', label: 'settings.navGalleryPresets' },
```

- [ ] **Step 10: Add the copy**

In `apps/studio/resources/js/locales/de.json`, im `settings`-Block:

```json
    "groupGalleries": "Galerien",
    "navGalleryDefaults": "Standardwerte",
    "navGalleryPresets": "Vorlagen",
```

`settings.navGalleries` entfällt — prüfe mit `grep -rn "navGalleries" apps/studio`, dass niemand mehr darauf zeigt.

Neuer Block `galleryPresets`:

```json
  "galleryPresets": {
    "title": "Vorlagen",
    "none": "Keine",
    "nameField": "Name",
    "expiryField": "Ablauf",
    "mode_inherit": "Studio-Wert",
    "mode_days": "Tage",
    "mode_never": "Kein Ablauf",
    "inherit": "Studio-Wert",
    "on": "An",
    "off": "Aus",
    "save": "Speichern",
    "delete": "Löschen",
    "add": "Vorlage hinzufügen"
  },
```

Die drei Angebotsbeschriftungen und „Gültigkeit in Tagen" kommen aus `gallerySettings.*` — nicht verdoppeln.

- [ ] **Step 11: Run everything**

Run: `pnpm php php artisan typescript:transform`
Then: `pnpm lint`
Then: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='GalleryPreset'`
Expected: alles PASS.

- [ ] **Step 12: Commit**

```bash
git add apps/studio/database/migrations/2026_08_22_000100_create_gallery_presets_table.php \
        apps/studio/app/Gallery/Models/GalleryPreset.php \
        apps/studio/app/Gallery/Data/GalleryPresetData.php \
        apps/studio/app/Gallery/Http/Controllers/GalleryPresetController.php \
        apps/studio/database/factories/Gallery/GalleryPresetFactory.php \
        apps/studio/resources/js/admin/pages/Settings/GalleryPresets.vue \
        apps/studio/resources/js/admin/components/SettingsNav.vue \
        apps/studio/routes/admin.php \
        apps/studio/resources/js/locales/de.json \
        apps/studio/resources/js/types/generated.d.ts \
        apps/studio/tests/Feature/Settings/GalleryPresetTest.php
git commit -m "feat(gallery): benannte Vorlagen fuer neue Galerien"
```

---

### Task 9: Projekt-Typeahead

**Files:**
- Create: `apps/studio/app/Crm/Data/ProjectSuggestionData.php`
- Create: `apps/studio/app/Crm/Http/Controllers/ProjectSearchController.php`
- Create: `apps/studio/resources/js/admin/components/ProjectTypeahead.vue`
- Modify: `apps/studio/routes/admin.php`
- Test: `apps/studio/tests/Feature/Crm/ProjectSearchTest.php`

**Interfaces:**
- Consumes: `Project::visibleTo()`.
- Produces: `GET /projekte/suche?q=` → `{"projects": ProjectSuggestionData[]}`. `ProjectSuggestionData(id, name, contactName, galleryDefaults)` mit `galleryDefaults: {downloads: ?bool, lists: ?bool, comments: ?bool}`. Komponente `ProjectTypeahead.vue` mit `defineModel<App.Crm.Data.ProjectSuggestionData | null>`.

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Crm/ProjectSearchTest.php`:

```php
<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

it('finds a project case-insensitively', function () {
    Project::factory()->create(['name' => 'Basthorst 2026']);

    $response = $this->getJson('/projekte/suche?q=basth');

    $response->assertOk();
    expect($response->json('projects.0.name'))->toBe('Basthorst 2026');
});

it('answers an empty list below two characters', function () {
    Project::factory()->create(['name' => 'Basthorst 2026']);

    expect($this->getJson('/projekte/suche?q=b')->json('projects'))->toBe([]);
});

// Die drei Werte reisen mit dem Vorschlag, damit die Geburtskette im Formular
// vollständig ablaufen kann, ohne dass die Projektwahl einen zweiten Weg zum
// Server braucht.
it('carries the primary contact gallery overrides', function () {
    $contact = Contact::factory()->create([
        'portal_overrides' => ['gallery_downloads' => false],
    ]);
    $project = Project::factory()->create(['name' => 'Basthorst 2026']);
    $project->contacts()->attach($contact, ['role' => ProjectContactRole::Primary->value]);

    $suggestion = $this->getJson('/projekte/suche?q=basth')->json('projects.0');

    expect($suggestion['contactName'])->toBe($contact->name)
        ->and($suggestion['galleryDefaults']['downloads'])->toBeFalse()
        ->and($suggestion['galleryDefaults']['lists'])->toBeNull();
});
```

Prüfe vor dem Lauf, wie `Project::contacts()` in `apps/studio/app/Crm/Models/Project.php` die Pivot-Rolle erwartet, und passe das `attach()` daran an.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='ProjectSearch'`
Expected: FAIL — 404 auf `/projekte/suche`.

- [ ] **Step 3: Write the DTO**

Create `apps/studio/app/Crm/Data/ProjectSuggestionData.php`:

```php
<?php

namespace App\Crm\Data;

use App\Crm\Models\Project;
use App\Portal\Support\PortalSettings;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ProjectSuggestionData extends Data
{
    /** @param  array{downloads: bool|null, lists: bool|null, comments: bool|null}  $galleryDefaults */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $contactName,
        public array $galleryDefaults,
    ) {}

    public static function fromModel(Project $project): self
    {
        $contact = $project->primaryContact();
        $overrides = $contact?->portal_overrides ?? [];

        return new self(
            id: $project->public_id,
            name: $project->name,
            contactName: $contact?->name,
            // Reisen mit dem Vorschlag, weil das Anlegen-Formular die
            // Geburtskette clientseitig zu Ende rechnet — ohne sie bräuchte
            // die Projektwahl einen zweiten Weg zum Server.
            galleryDefaults: [
                'downloads' => $overrides['gallery_downloads'] ?? null,
                'lists' => $overrides['gallery_lists'] ?? null,
                'comments' => $overrides['gallery_comments'] ?? null,
            ],
        );
    }
}
```

Prüfe an `PortalSettings::OVERRIDABLE`, dass die drei Schlüsselnamen unverändert stimmen. Der Import bleibt nur stehen, wenn du ihn benutzt — sonst entfernen.

- [ ] **Step 4: Write the controller**

Create `apps/studio/app/Crm/Http/Controllers/ProjectSearchController.php`:

```php
<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\ProjectSuggestionData;
use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feeds ProjectTypeahead on the gallery form. Same shape as
 * ContactSearchController, including the ilike: on Postgres like is
 * case-sensitive, and a search that misses "Basthorst" when someone types
 * "basth" sends the user off to create a second gallery in the wrong place.
 */
class ProjectSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['projects' => []]);
        }

        $projects = Project::query()
            ->visibleTo(auth('staff')->user())
            ->where('name', 'ilike', "%{$term}%")
            ->orderBy('name')
            ->limit(8)
            ->get();

        return response()->json([
            'projects' => ProjectSuggestionData::collect($projects),
        ]);
    }
}
```

- [ ] **Step 5: Register the route**

In `apps/studio/routes/admin.php`, **über** jeder `/projekte/{project}`-Route — sonst bindet Laravel „suche" als Projekt-Id und antwortet 404:

```php
        // /projekte/suche bleibt über /projekte/{project}, aus demselben
        // Grund wie /kontakte/suche über /kontakte/{contact}.
        Route::get('/projekte/suche', ProjectSearchController::class)->name('projects.search');
```

- [ ] **Step 6: Write the component**

Create `apps/studio/resources/js/admin/components/ProjectTypeahead.vue`, nach dem Aufbau von `ContactTypeahead.vue`: `defineModel`, ein `term`-Ref, ein 250-ms-Timer, `fetch('/projekte/suche?q=…')`, Trefferliste, `choose()`. Ohne die Initialen-Kachel und ohne `createNew` — ein Projekt wird hier nicht angelegt.

```ts
const model = defineModel<App.Crm.Data.ProjectSuggestionData | null>({ required: true })

const term = ref('')
const results = ref<App.Crm.Data.ProjectSuggestionData[]>([])

let timer: ReturnType<typeof setTimeout> | undefined

watch(term, (value) => {
  clearTimeout(timer)

  if (value.trim().length < 2) {
    results.value = []
    return
  }

  timer = setTimeout(async () => {
    const response = await fetch(`/projekte/suche?q=${encodeURIComponent(value)}`, {
      headers: { Accept: 'application/json' },
    })
    results.value = (await response.json()).projects
  }, 250)
})

function choose(project: App.Crm.Data.ProjectSuggestionData) {
  model.value = project
  results.value = []
}

function clear() {
  model.value = null
  term.value = ''
}
```

Das Template dazu — die Zeilenklassen sind die aus `ContactTypeahead.vue`, ohne dessen Initialen-Kachel:

```vue
<template>
  <div v-if="model" class="flex items-center gap-[11px] rounded-md border border-line-hover px-3 py-[9px]">
    <div class="min-w-0">
      <div class="truncate text-[13px] leading-[1.25] font-semibold">{{ model.name }}</div>
      <div v-if="model.contactName" class="mt-px text-[11.5px] leading-[1.3] font-normal text-muted-foreground">
        {{ model.contactName }}
      </div>
    </div>
    <Button class="ml-auto" type="button" variant="pill-muted" @click="clear">{{ $t('common.cancel') }}</Button>
  </div>

  <div v-else>
    <Input v-model="term" type="text" :placeholder="$t('projects.searchPlaceholder')" />

    <div v-if="results.length" class="mt-1.5">
      <button v-for="project in results" :key="project.id" :class="ROW" type="button" @click="choose(project)">
        <span class="min-w-0 flex-1 truncate text-[13px] leading-[1.25] font-semibold">{{ project.name }}</span>
        <span v-if="project.contactName" class="flex-none text-[11.5px] leading-[1.3] text-muted-foreground">
          {{ project.contactName }}
        </span>
      </button>
    </div>
  </div>
</template>
```

`ROW` ist dieselbe Konstante wie in `ContactTypeahead.vue:53-57`; kopiere sie mit. Neuer Schlüssel `projects.searchPlaceholder` mit dem Wert `Projekt suchen` — prüfe zuerst mit `grep -n '"projects"' -A 20 apps/studio/resources/js/locales/de.json`, ob es dort schon einen Platzhalter gibt, der passt.

Kein Knopf „Projekt anlegen": ein Projekt entsteht aus einer Anfrage, nicht aus einem Galerieformular.

- [ ] **Step 7: Run everything**

Run: `pnpm php php artisan typescript:transform`
Then: `pnpm lint`
Then: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='ProjectSearch'`
Expected: alles PASS.

- [ ] **Step 8: Commit**

```bash
git add apps/studio/app/Crm/Data/ProjectSuggestionData.php \
        apps/studio/app/Crm/Http/Controllers/ProjectSearchController.php \
        apps/studio/resources/js/admin/components/ProjectTypeahead.vue \
        apps/studio/routes/admin.php \
        apps/studio/resources/js/types/generated.d.ts \
        apps/studio/tests/Feature/Crm/ProjectSearchTest.php
git commit -m "feat(crm): Typeahead fuer Projekte"
```

---

### Task 10: Das Anlegen-Formular

**Files:**
- Modify: `apps/studio/app/Gallery/Http/Controllers/GalleryIndexController.php`
- Create: `apps/studio/resources/js/admin/pages/Galleries/Create.vue`
- Create: `apps/studio/resources/js/admin/lib/galleryBirth.ts`
- Create: `apps/studio/resources/js/admin/lib/galleryBirth.test.ts`
- Modify: `apps/studio/routes/admin.php`
- Modify: `apps/studio/resources/js/locales/de.json`
- Test: `apps/studio/tests/Feature/Gallery/GalleryCreateTest.php`

**Interfaces:**
- Consumes: `GalleryPreset` (Task 8), `ProjectTypeahead` und `ProjectSuggestionData` (Task 9), `GalleryPolicy::create` (Task 6).
- Produces: Routen `galleries.create` und `galleries.store`. Funktion `birthValues(preset, project, studio)` in `galleryBirth.ts`.

- [ ] **Step 1: Write the failing Vitest for the birth chain**

Create `apps/studio/resources/js/admin/lib/galleryBirth.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { birthValues } from './galleryBirth'

const STUDIO = { downloads: true, lists: true, comments: true }

describe('birthValues', () => {
  it('falls back to the studio default when nothing else says anything', () => {
    expect(birthValues(null, null, STUDIO)).toEqual(STUDIO)
  })

  it('lets the contact override the studio', () => {
    const project = { galleryDefaults: { downloads: false, lists: null, comments: null } }

    expect(birthValues(null, project, STUDIO)).toEqual({ downloads: false, lists: true, comments: true })
  })

  // Die Reihenfolge, die entschieden wurde: die Vorlage steht dem Fall näher
  // als der Kontakt und gewinnt gegen ihn.
  it('lets the preset override the contact', () => {
    const project = { galleryDefaults: { downloads: false, lists: null, comments: null } }
    const preset = { downloadsEnabled: true, listsEnabled: null, commentsEnabled: false }

    expect(birthValues(preset, project, STUDIO)).toEqual({ downloads: true, lists: true, comments: false })
  })

  it('leaves a value the preset does not speak to with the contact', () => {
    const project = { galleryDefaults: { downloads: false, lists: false, comments: null } }
    const preset = { downloadsEnabled: null, listsEnabled: null, commentsEnabled: null }

    expect(birthValues(preset, project, STUDIO)).toEqual({ downloads: false, lists: false, comments: true })
  })
})
```

- [ ] **Step 2: Run it to verify it fails**

Run: `pnpm --filter @studio/app test galleryBirth`
Expected: FAIL — `Cannot find module './galleryBirth'`

- [ ] **Step 3: Write the birth chain**

Create `apps/studio/resources/js/admin/lib/galleryBirth.ts`:

```ts
type Tri = boolean | null

type Preset = { downloadsEnabled: Tri; listsEnabled: Tri; commentsEnabled: Tri }
type Project = { galleryDefaults: { downloads: Tri; lists: Tri; comments: Tri } }
type Studio = { downloads: boolean; lists: boolean; comments: boolean }

/*
 * Womit eine neue Galerie geboren wird. Die Vorlage steht dem Fall näher als
 * der Kontakt und gewinnt gegen ihn; ein Wert, zu dem eine Ebene nichts sagt,
 * reicht nach unten durch. Läuft hier und nicht auf dem Server, damit vor dem
 * Absenden sichtbar ist, was die Vorlage getan hat — und jedes Feld einzeln
 * korrigierbar bleibt.
 */
export function birthValues(preset: Preset | null, project: Project | null, studio: Studio): Studio {
  const pick = (fromPreset: Tri, fromProject: Tri, fromStudio: boolean) =>
    fromPreset ?? fromProject ?? fromStudio

  return {
    downloads: pick(preset?.downloadsEnabled ?? null, project?.galleryDefaults.downloads ?? null, studio.downloads),
    lists: pick(preset?.listsEnabled ?? null, project?.galleryDefaults.lists ?? null, studio.lists),
    comments: pick(preset?.commentsEnabled ?? null, project?.galleryDefaults.comments ?? null, studio.comments),
  }
}
```

- [ ] **Step 4: Run the Vitest**

Run: `pnpm --filter @studio/app test galleryBirth`
Expected: PASS, vier Tests.

- [ ] **Step 5: Write the failing Pest test for the store**

Create `apps/studio/tests/Feature/Gallery/GalleryCreateTest.php`:

```php
<?php

use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

it('creates a gallery and sends the user to the ingest screen', function () {
    $project = Project::factory()->create();

    $response = $this->post('/galerien', [
        'project_id' => $project->public_id,
        'title' => 'Trauung',
        'is_external' => false,
        'expires_at' => '2027-01-31',
        'downloads_enabled' => true,
        'lists_enabled' => false,
        'comments_enabled' => true,
    ]);

    $gallery = Gallery::sole();

    $response->assertRedirect("/galerien/{$gallery->public_id}/medien");

    expect($gallery->title)->toBe('Trauung')
        ->and($gallery->project_id)->toBe($project->id)
        ->and($gallery->lists_enabled)->toBeFalse()
        ->and($gallery->expires_at->toDateString())->toBe('2027-01-31');
});

it('creates one without a project', function () {
    $this->post('/galerien', [
        'project_id' => null,
        'title' => 'Studioportraits',
        'is_external' => false,
        'downloads_enabled' => true,
        'lists_enabled' => true,
        'comments_enabled' => true,
    ])->assertRedirect();

    expect(Gallery::sole()->project_id)->toBeNull();
});

// Eine externe Galerie hostet nichts: publish() wirft bei ihr, und es gibt
// keinen Ingest-Screen, auf den man landen könnte.
it('sends an external gallery to its record instead', function () {
    $response = $this->post('/galerien', [
        'project_id' => null,
        'title' => 'Pixieset 2024',
        'is_external' => true,
        'external_url' => 'https://example.test/g/abc',
    ]);

    $gallery = Gallery::sole();

    $response->assertRedirect("/galerien/{$gallery->public_id}");

    expect($gallery->is_external)->toBeTrue()
        ->and($gallery->external_url)->toBe('https://example.test/g/abc');
});

it('refuses an external gallery without a url', function () {
    $this->post('/galerien', [
        'project_id' => null,
        'title' => 'Kaputt',
        'is_external' => true,
    ])->assertSessionHasErrors('external_url');
});
```

- [ ] **Step 6: Run it to verify it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='GalleryCreate'`
Expected: FAIL — 405 oder 404 auf `POST /galerien`.

- [ ] **Step 7: Add create() and store() to the controller**

In `apps/studio/app/Gallery/Http/Controllers/GalleryIndexController.php`:

```php
    public function create(StudioSettingsResolver $settings): Response
    {
        $this->authorize('create', Gallery::class);

        return Inertia::render('Galleries/Create', [
            'presets' => GalleryPresetData::collect(GalleryPreset::query()->orderBy('name')->get()),
            // Die unterste Stufe der Geburtskette; die beiden darüber reichen
            // Vorlage und Projektvorschlag nach.
            'studioDefaults' => [
                'downloads' => $settings->galleryDownloadsEnabled(),
                'lists' => $settings->galleryListsEnabled(),
                'comments' => $settings->galleryCommentsEnabled(),
                'expiryDays' => $settings->galleryDefaults()['expiryDays'],
                'requiresSettledBalance' => $settings->galleryDefaults()['requiresSettledBalance'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Gallery::class);

        $validated = $request->validate([
            'project_id' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:200'],
            'is_external' => ['required', 'boolean'],
            'external_url' => ['nullable', 'url', 'max:2000', 'required_if:is_external,true'],
            'expires_at' => ['nullable', 'date'],
            'requires_settled_balance' => ['nullable', 'boolean'],
            'downloads_enabled' => ['nullable', 'boolean'],
            'lists_enabled' => ['nullable', 'boolean'],
            'comments_enabled' => ['nullable', 'boolean'],
            'design' => ['nullable', 'array'],
        ]);

        $project = $validated['project_id'] === null
            ? null
            : Project::findByPublicId($validated['project_id']);

        $gallery = Gallery::create([
            ...Arr::except($validated, ['project_id']),
            'project_id' => $project?->id,
        ]);

        AuditLogger::record(
            event: 'gallery.created',
            actor: auth('staff')->user(),
            context: ['title' => $gallery->title],
            subject: $gallery->public_id,
        );

        // Eine externe Galerie hat keinen Ingest — dort liegt nichts, was man
        // hochladen könnte.
        return redirect($gallery->is_external
            ? "/galerien/{$gallery->public_id}"
            : "/galerien/{$gallery->public_id}/medien");
    }
```

Imports ergänzen: `Arr`, `Request`, `RedirectResponse`, `AuditLogger`, `GalleryPreset`, `GalleryPresetData`, `StudioSettingsResolver`. Prüfe, ob `Project::findByPublicId()` existiert (`HasPublicId`) — sonst `Project::query()->where('public_id', …)->firstOrFail()`.

- [ ] **Step 8: Register the routes**

In `apps/studio/routes/admin.php`, zwischen `galleries.index` und `/galerien/{gallery}`:

```php
        Route::get('/galerien/neu', [GalleryIndexController::class, 'create'])->name('galleries.create');
        Route::post('/galerien', [GalleryIndexController::class, 'store'])->name('galleries.store');
```

- [ ] **Step 9: Write the form screen**

Create `apps/studio/resources/js/admin/pages/Galleries/Create.vue`. Zwei Zweige über eine `ChoiceGroup`; die Vorlagenauswahl füllt die Felder darunter über `birthValues`:

```vue
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { ChoiceGroup } from '@/ui/choice-group'
import ProjectTypeahead from '@/admin/components/ProjectTypeahead.vue'
import { birthValues } from '@/admin/lib/galleryBirth'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { NativeSelect } from '@/ui/native-select'

const props = defineProps<{
  presets: App.Gallery.Data.GalleryPresetData[]
  studioDefaults: {
    downloads: boolean
    lists: boolean
    comments: boolean
    expiryDays: number | null
    requiresSettledBalance: boolean
  }
}>()

const MODES = ['internal', 'external']

const mode = ref('internal')
const project = ref<App.Crm.Data.ProjectSuggestionData | null>(null)
const presetId = ref('')

const preset = computed(() => props.presets.find((row) => row.id === presetId.value) ?? null)

const form = useForm({
  project_id: null as string | null,
  title: '',
  is_external: false,
  external_url: '',
  expires_at: null as string | null,
  requires_settled_balance: props.studioDefaults.requiresSettledBalance,
  downloads_enabled: props.studioDefaults.downloads,
  lists_enabled: props.studioDefaults.lists,
  comments_enabled: props.studioDefaults.comments,
  design: null as Record<string, string> | null,
})

watch([preset, project], () => {
  const born = birthValues(preset.value, project.value, props.studioDefaults)

  form.downloads_enabled = born.downloads
  form.lists_enabled = born.lists
  form.comments_enabled = born.comments
  form.requires_settled_balance =
    preset.value?.requiresSettledBalance ?? props.studioDefaults.requiresSettledBalance
  form.design = preset.value?.design ?? null
  form.expires_at = expiryFrom(preset.value)
})

function expiryFrom(chosen: App.Gallery.Data.GalleryPresetData | null) {
  const days =
    chosen === null || chosen.expiryMode === 'inherit'
      ? props.studioDefaults.expiryDays
      : chosen.expiryMode === 'never'
        ? null
        : chosen.expiryDays

  if (days === null) {
    return null
  }

  const date = new Date()
  date.setDate(date.getDate() + days)

  return date.toISOString().slice(0, 10)
}

function submit() {
  form
    .transform((data) => ({
      ...data,
      project_id: project.value?.id ?? null,
      is_external: mode.value === 'external',
    }))
    .post('/galerien')
}
</script>
```

Das Template dazu:

```vue
<template>
  <AdminLayout active="galerien">
    <div data-tokens>
      <PageHead :title="$t('galleries.create')" />

      <form class="max-w-[620px] px-8 pt-7 pb-8" @submit.prevent="submit">
        <ChoiceGroup
          v-model="mode"
          :options="MODES.map((option) => ({ value: option, label: $t(`galleries.mode_${option}`) }))"
        />

        <div class="mt-6 flex flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <Label for="gallery-project">{{ $t('projects.title') }}</Label>
            <ProjectTypeahead id="gallery-project" v-model="project" />
          </div>

          <div class="flex flex-col gap-1.5">
            <Label for="gallery-title">{{ $t('galleries.titleField') }}</Label>
            <Input id="gallery-title" v-model="form.title" type="text" />
          </div>

          <div v-if="mode === 'external'" class="flex flex-col gap-1.5">
            <Label for="gallery-url">{{ $t('galleries.urlField') }}</Label>
            <Input id="gallery-url" v-model="form.external_url" type="url" />
          </div>

          <template v-else>
            <div class="flex flex-col gap-1.5">
              <Label for="gallery-preset">{{ $t('galleryPresets.title') }}</Label>
              <NativeSelect id="gallery-preset" v-model="presetId">
                <option value="">{{ $t('galleryPresets.none') }}</option>
                <option v-for="row in presets" :key="row.id" :value="row.id">{{ row.name }}</option>
              </NativeSelect>
            </div>

            <div class="flex flex-col gap-1.5">
              <Label for="gallery-expiry">{{ $t('galleries.expiryField') }}</Label>
              <Input id="gallery-expiry" v-model="form.expires_at" type="date" />
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="cb-new-balance" v-model="form.requires_settled_balance" />
              <Label variant="inline" for="cb-new-balance">{{ $t('gallerySettings.balanceLabel') }}</Label>
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="cb-new-downloads" v-model="form.downloads_enabled" />
              <Label variant="inline" for="cb-new-downloads">{{ $t('gallerySettings.downloadsLabel') }}</Label>
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="cb-new-lists" v-model="form.lists_enabled" />
              <Label variant="inline" for="cb-new-lists">{{ $t('gallerySettings.listsLabel') }}</Label>
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="cb-new-comments" v-model="form.comments_enabled" />
              <Label variant="inline" for="cb-new-comments">{{ $t('gallerySettings.commentsLabel') }}</Label>
            </div>
          </template>
        </div>

        <Button class="mt-6" type="submit" :disabled="form.processing || form.title === ''">
          {{ $t('galleries.create') }}
        </Button>
      </form>
    </div>
  </AdminLayout>
</template>
```

Der externe Zweig zeigt kein Preset, kein Datum und keine Angebote: eine Galerie, die anderswo liegt, hat weder eine Version noch einen Download, den dieses System regeln könnte.

- [ ] **Step 10: Add the copy**

Im `galleries`-Block von `apps/studio/resources/js/locales/de.json`:

```json
    "titleField": "Titel",
    "urlField": "Adresse",
    "expiryField": "Gültig bis",
    "mode_internal": "Eigene Galerie",
    "mode_external": "Externer Link"
```

`galleryPresets.none` wurde in Task 8 angelegt. Die drei Angebotsbeschriftungen und die Zahlungssperre kommen aus `gallerySettings.*`, der Projekttitel aus `projects.title` — vor dem Anlegen eines Schlüssels danach greppen.

- [ ] **Step 11: Run everything**

Run: `pnpm --filter @studio/app test`
Then: `pnpm lint`
Then: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery tests/Feature/Settings tests/Feature/Crm`
Expected: alles PASS.

- [ ] **Step 12: Commit**

```bash
git add apps/studio/app/Gallery/Http/Controllers/GalleryIndexController.php \
        apps/studio/resources/js/admin/pages/Galleries/Create.vue \
        apps/studio/resources/js/admin/lib/galleryBirth.ts \
        apps/studio/resources/js/admin/lib/galleryBirth.test.ts \
        apps/studio/routes/admin.php \
        apps/studio/resources/js/locales/de.json \
        apps/studio/tests/Feature/Gallery/GalleryCreateTest.php
git commit -m "feat(gallery): Galerie im Admin anlegen"
```

---

## Nach dem letzten Task

- [ ] **Full suite:** `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest`
- [ ] **Typecheck:** `pnpm lint`
- [ ] **Vitest:** `pnpm test`
- [ ] **Build:** `pnpm build`
- [ ] **E2E-Baselines:** `pnpm --filter @studio/app exec playwright test`. `E2eSeeder` sät relativ zu `now()`, eine tagealte Baseline färbt alle Screenshots rot — vor dem Urteil neu säen.
- [ ] **PR gegen `dev`**, nicht gegen `main`. Die `Closes #…`-Zeilen müssen beim Anlegen im Body stehen; nachträglich eingefügt lösen sie nicht aus.
