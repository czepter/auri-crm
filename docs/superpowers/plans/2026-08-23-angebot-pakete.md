# S3 · Angebot als Paket sichern, Paket duplizieren — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Der Rückweg vom Angebot in den Katalog und die Kopie eines Pakets neben sich.

**Architecture:** Zwei kurze Wege über bestehende Bausteine. `CreatePackageFromQuote` liegt in `Catalog`, weil sie ein `Package` erzeugt — spiegelbildlich zu `SeedQuoteFromPackage`, die in `Quotes` liegt, weil sie Angebotspositionen erzeugt. Duplizieren nutzt `replicate()` und `Package::copyItems()`, das bereits eine getrennte Kopie ohne `id` und ohne `package_id` liefert.

**Tech Stack:** Laravel 12 · Inertia · Vue 3 `<script setup>` · Pest

**Spec:** [docs/superpowers/specs/2026-08-23-angebot-pakete-design.md](../specs/2026-08-23-angebot-pakete-design.md)

**Setzt voraus:** S1 (`2026-08-23-angebot-tabelleneditor.md`) — der Dialog hängt in der Fußzeile von `QuoteTable.vue`. Von S2 ist dieser Plan unabhängig; beide können nebeneinander laufen, solange nicht dieselbe Datei angefasst wird (siehe Parallelisierung).

**Issue:** #263

## Global Constraints

- Formularelemente kommen aus `resources/js/ui`. Kein rohes `<input>`, `<select>` oder `<button>`.
- Deutsche Beschriftungen kurz und nativ. Kein Emoji, kein Ausrufezeichen, keine Beruhigungssätze.
- Kommentare nur für das *Warum*.
- Backend-Tests: `-e APP_SURFACE=admin` am Container.
- `unit_price === null` heißt „Enthaltene Leistung", nicht null Euro.
- Nichts hält eine lebende Verbindung von einem Angebot zurück auf ein Paket.
- Arbeitsverzeichnis für alle Befehle: `apps/studio`.

---

### Task 1: `CreatePackageFromQuote`

**Files:**
- Create: `apps/studio/app/Catalog/Actions/CreatePackageFromQuote.php`
- Test: `apps/studio/tests/Feature/Catalog/PackageFromQuoteTest.php`

**Interfaces:**
- Produces: `CreatePackageFromQuote::__invoke(Quote $quote, string $name): Package`. Task 2 ruft sie auf.

- [ ] **Step 1: Write the failing test**

`apps/studio/tests/Feature/Catalog/PackageFromQuoteTest.php`:

```php
<?php

use App\Catalog\Actions\CreatePackageFromQuote;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('copies title, description, quantity and unit price in order', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->create(['title' => 'Reportage', 'description' => 'Ganztags', 'quantity' => 1, 'unit_price' => 189000, 'sort_order' => 0]);
    QuoteItem::factory()->for($quote)->create(['title' => 'Zweitfotograf', 'description' => null, 'quantity' => 2, 'unit_price' => 49000, 'sort_order' => 1]);

    $package = app(CreatePackageFromQuote::class)($quote->fresh(), 'Hochzeit Basis');

    expect($package->name)->toBe('Hochzeit Basis')
        ->and($package->is_active)->toBeTrue()
        ->and($package->items->pluck('title')->all())->toBe(['Reportage', 'Zweitfotograf'])
        ->and($package->items->first()->description)->toBe('Ganztags')
        ->and($package->items->last()->quantity)->toBe(2)
        ->and($package->items->pluck('sort_order')->all())->toBe([0, 1]);
});

it('drops the line discount, because a package holds services and not negotiations', function () {
    $quote = Quote::factory()->create(['discount_type' => DiscountType::Percentage, 'discount_value' => 1000]);
    QuoteItem::factory()->for($quote)->create([
        'title' => 'Reportage',
        'quantity' => 1,
        'unit_price' => 189000,
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 500,
    ]);

    $package = app(CreatePackageFromQuote::class)($quote->fresh(), 'Hochzeit Basis');

    expect($package->items->first()->unit_price)->toBe(189000)
        ->and($package->items->first()->getAttributes())->not->toHaveKey('discount_type');
});

it('keeps a line without a price as an included service', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->included()->create(['title' => 'Anfahrt']);

    $package = app(CreatePackageFromQuote::class)($quote->fresh(), 'Hochzeit Basis');

    expect($package->items->first()->unit_price)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Catalog/PackageFromQuoteTest.php`
Expected: FAIL — `Target class [App\Catalog\Actions\CreatePackageFromQuote] does not exist`

- [ ] **Step 3: Write minimal implementation**

`apps/studio/app/Catalog/Actions/CreatePackageFromQuote.php`:

```php
<?php

namespace App\Catalog\Actions;

use App\Catalog\Models\Package;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;

/**
 * Der Rückweg zu SeedQuoteFromPackage. Sie liegt hier und nicht in Quotes,
 * weil sie ein Package schreibt — eine Action wohnt bei dem, was sie
 * erzeugt, nicht bei dem, was sie liest.
 *
 * Rabatte gehen nicht mit: package_items hat die Spalten nicht, und das ist
 * kein Versehen. Ein Rabatt gehört zum Verhandlungsfall, eine Leistung zum
 * Katalog.
 */
class CreatePackageFromQuote
{
    public function __invoke(Quote $quote, string $name): Package
    {
        $package = Package::create(['name' => $name]);

        $package->items()->createMany(
            $quote->items->values()->map(fn (QuoteItem $item, int $index) => [
                'title' => $item->title,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'sort_order' => $index,
            ])->all()
        );

        return $package->load('items');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Catalog/PackageFromQuoteTest.php`
Expected: PASS, 3 Tests

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Catalog/Actions/CreatePackageFromQuote.php apps/studio/tests/Feature/Catalog/PackageFromQuoteTest.php
git commit -m "feat(catalog): Paket aus einem Angebot bauen"
```

---

### Task 2: Route und Controller fürs Sichern

**Files:**
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php`
- Modify: `apps/studio/routes/admin.php:416-419`
- Test: `apps/studio/tests/Feature/Catalog/PackageFromQuoteTest.php`

**Interfaces:**
- Consumes: `CreatePackageFromQuote` aus Task 1
- Produces: `POST /angebote/{quote}/als-paket`, Name `quotes.saveAsPackage`

- [ ] **Step 1: Write the failing test**

An `PackageFromQuoteTest.php` anhängen; `Package`, `AuditLog`, `QuoteStatus` und `User` im Kopf importieren.

```php
it('saves a quote as a package over the route, and logs where it came from', function () {
    $user = User::factory()->withMfa()->create();
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->create(['title' => 'Reportage', 'quantity' => 1, 'unit_price' => 189000]);

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$quote->public_id}/als-paket", ['name' => 'Hochzeit Basis'])
        ->assertRedirect();

    $package = Package::sole();

    expect($package->name)->toBe('Hochzeit Basis')
        ->and($package->items)->toHaveCount(1);

    $row = AuditLog::where('event', 'package.created')->sole();

    expect($row->subject)->toBe($package->public_id)
        ->and($row->context['source'])->toBe('quote');
});

it('saves a quote that has already been sent, because the useful package is the accepted one', function () {
    $quote = Quote::factory()->create(['status' => QuoteStatus::Sent, 'number' => 'AN-2026-001']);
    QuoteItem::factory()->for($quote)->create(['title' => 'Reportage', 'quantity' => 1, 'unit_price' => 189000]);

    $this->actingAs(User::factory()->withMfa()->create(), 'staff')
        ->post("/angebote/{$quote->public_id}/als-paket", ['name' => 'Hochzeit Basis'])
        ->assertRedirect();

    expect(Package::count())->toBe(1);
});

it('refuses an empty name and a quote without lines', function () {
    $user = User::factory()->withMfa()->create();
    $empty = Quote::factory()->create();
    $filled = Quote::factory()->create();
    QuoteItem::factory()->for($filled)->create(['title' => 'Reportage', 'quantity' => 1, 'unit_price' => 189000]);

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$filled->public_id}/als-paket", ['name' => ''])
        ->assertInvalid(['name']);

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$empty->public_id}/als-paket", ['name' => 'Leer'])
        ->assertInvalid(['name']);

    expect(Package::count())->toBe(0);
});
```

Der zweite Fall meldet den Fehler auf `name`, weil das Formular kein anderes Feld hat, an dem eine Meldung stehen könnte.

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Catalog/PackageFromQuoteTest.php`
Expected: FAIL — 404 auf die Route

- [ ] **Step 3: Write minimal implementation**

In `routes/admin.php` neben `versenden`:

```php
Route::post('/angebote/{quote}/als-paket', [QuoteController::class, 'saveAsPackage'])->name('quotes.saveAsPackage');
```

In `QuoteController`, mit den Importen für `CreatePackageFromQuote`, `Package` und `ValidationException`:

```php
/**
 * Zwei Prüfungen, weil zwei Dinge berührt werden. Heute hält jede Rolle
 * beides — getrennt geprüft wird aus dem Grund, den PackagePolicy nennt:
 * P6/I-01 gibt Token gegen einzelne Fähigkeiten aus.
 */
public function saveAsPackage(Request $request, Quote $quote, CreatePackageFromQuote $create): RedirectResponse
{
    $this->authorize('manageQuotes', $quote->project);
    $this->authorize('create', Package::class);

    $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

    if ($quote->items->isEmpty()) {
        throw ValidationException::withMessages(['name' => __('quotes.emptyPackage')]);
    }

    $package = $create($quote, $validated['name']);

    AuditLogger::record(
        event: 'package.created',
        actor: auth('staff')->user(),
        context: ['name' => $package->name, 'source' => 'quote'],
        subject: $package->public_id,
    );

    return back();
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Catalog/PackageFromQuoteTest.php`
Expected: PASS, 6 Tests

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Quotes/Http/Controllers/QuoteController.php apps/studio/routes/admin.php apps/studio/tests/Feature/Catalog/PackageFromQuoteTest.php
git commit -m "feat(quotes): Angebot als Paket sichern"
```

---

### Task 3: Der Dialog

**Files:**
- Modify: `apps/studio/resources/js/admin/pages/Projects/Quotes.vue`

**Interfaces:**
- Consumes: Route aus Task 2

- [ ] **Step 1: Formular und Dialog anlegen**

In `Projects/Quotes.vue`, je Angebot ein eigenes Formular nach demselben Muster, das `sendForms` schon verwendet:

```ts
const packageForms = Object.fromEntries(props.quotes.map((quote) => [quote.id, useForm({ name: '' })]))

function saveAsPackage(quoteId: string) {
  packageForms[quoteId].post(`/angebote/${quoteId}/als-paket`, {
    preserveScroll: true,
    onSuccess: () => packageForms[quoteId].reset(),
  })
}
```

- [ ] **Step 2: Knopf und Dialog rendern**

In der Fußzeile jedes Angebots — **außerhalb** der `status === 'draft'`-Bedingung, weil die Action nur liest und das brauchbarste Paket das angenommene Angebot ist:

```vue
<Dialog v-if="header.canManageQuotes">
  <DialogTrigger as-child>
    <Button variant="outline" type="button">{{ $t('quotes.saveAsPackage') }}</Button>
  </DialogTrigger>
  <DialogContent class="max-w-[420px]">
    <DialogHeader>
      <DialogTitle>{{ $t('quotes.saveAsPackage') }}</DialogTitle>
    </DialogHeader>
    <DialogBody>
      <form :id="`as-package-${quote.id}`" class="fgrid" @submit.prevent="saveAsPackage(quote.id)">
        <div class="fld">
          <Label :for="`package-name-${quote.id}`">{{ $t('quotes.packageName') }}</Label>
          <Input :id="`package-name-${quote.id}`" v-model="packageForms[quote.id].name" type="text" required />
          <p v-if="packageForms[quote.id].errors.name" class="text-[12px] text-destructive">
            {{ packageForms[quote.id].errors.name }}
          </p>
        </div>
      </form>
    </DialogBody>
    <DialogFooter>
      <Button type="submit" :form="`as-package-${quote.id}`" :disabled="packageForms[quote.id].processing">
        {{ $t('common.save') }}
      </Button>
      <DialogClose as-child>
        <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
      </DialogClose>
    </DialogFooter>
  </DialogContent>
</Dialog>
```

Vorbelegt wird der Name nicht: ein Paketname beschreibt das Angebot, nicht den Kunden.

- [ ] **Step 3: Prüfen**

Run: `npm run lint`
Expected: grün

- [ ] **Step 4: Commit**

```bash
git add apps/studio/resources/js/admin/pages/Projects/Quotes.vue
git commit -m "feat(quotes): Dialog zum Sichern als Paket"
```

---

### Task 4: Paket duplizieren

**Files:**
- Modify: `apps/studio/app/Catalog/Http/Controllers/PackageController.php`
- Modify: `apps/studio/routes/admin.php:180-185`
- Test: `apps/studio/tests/Feature/Catalog/PackageTest.php`

**Interfaces:**
- Produces: `POST /pakete/{package}/duplizieren`, Name `packages.duplicate`

- [ ] **Step 1: Write the failing test**

An `apps/studio/tests/Feature/Catalog/PackageTest.php` anhängen:

```php
it('duplicates a package with independent items and a marked name', function () {
    $original = Package::factory()
        ->has(PackageItem::factory()->count(2), 'items')
        ->create(['name' => 'Hochzeit Basis', 'description' => 'Ganztags']);

    $this->actingAs(staffMember(), 'staff')
        ->post("/pakete/{$original->public_id}/duplizieren")
        ->assertRedirect();

    $copy = Package::where('name', 'Hochzeit Basis (Kopie)')->sole();

    expect($copy->description)->toBe('Ganztags')
        ->and($copy->items)->toHaveCount(2)
        ->and($copy->items->pluck('id')->intersect($original->items->pluck('id')))->toBeEmpty();

    $copy->items->first()->update(['title' => 'Anders']);

    expect($original->items()->first()->title)->not->toBe('Anders');
});

it('keeps a deactivated package deactivated in its copy', function () {
    $original = Package::factory()->inactive()->create(['name' => 'Alt']);

    $this->actingAs(staffMember(), 'staff')->post("/pakete/{$original->public_id}/duplizieren");

    expect(Package::where('name', 'Alt (Kopie)')->sole()->is_active)->toBeFalse();
});

it('logs a duplicate as a package creation carrying its source', function () {
    $original = Package::factory()->create(['name' => 'Hochzeit Basis']);

    $this->actingAs(staffMember(), 'staff')->post("/pakete/{$original->public_id}/duplizieren");

    $row = AuditLog::where('event', 'package.created')->sole();

    expect($row->context['source'])->toBe('duplicate');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Catalog/PackageTest.php`
Expected: FAIL — 404 auf die Route

- [ ] **Step 3: Write minimal implementation**

In `routes/admin.php` bei den übrigen `/pakete`-Routen:

```php
Route::post('/pakete/{package}/duplizieren', [PackageController::class, 'duplicate'])->name('packages.duplicate');
```

In `PackageController`:

```php
/**
 * is_active wird übernommen: dupliziert wird, um zu ändern, und die
 * Entscheidung über die Sichtbarkeit trifft man danach. Der Name kollidiert
 * mit nichts — packages trägt keinen Unique-Index darauf —, und mehrfaches
 * Duplizieren zeigt sich selbst an.
 */
public function duplicate(Package $package): RedirectResponse
{
    $this->authorize('create', Package::class);

    $copy = $package->replicate(['public_id']);
    $copy->name = "{$package->name} (Kopie)";
    $copy->save();

    $copy->items()->createMany(
        collect($package->copyItems())
            ->values()
            ->map(fn (array $item, int $index) => [...$item, 'sort_order' => $index])
            ->all()
    );

    AuditLogger::record(
        event: 'package.created',
        actor: auth('staff')->user(),
        context: ['name' => $copy->name, 'source' => 'duplicate'],
        subject: $copy->public_id,
    );

    return redirect("/pakete/{$copy->public_id}");
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Catalog/PackageTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Catalog/Http/Controllers/PackageController.php apps/studio/routes/admin.php apps/studio/tests/Feature/Catalog/PackageTest.php
git commit -m "feat(catalog): Paket duplizieren"
```

---

### Task 5: Der Knopf in der Paketliste

**Files:**
- Modify: `apps/studio/resources/js/admin/pages/Packages/Index.vue`

- [ ] **Step 1: Aktion ergänzen**

```ts
function duplicate(pkg: App.Catalog.Data.PackageData) {
  router.post(`/pakete/${pkg.id}/duplizieren`)
}
```

Kein `preserveScroll`: der Weg endet auf der Bearbeiten-Seite des Duplikats.

- [ ] **Step 2: Knopf rendern**

Neben „Aktivieren", gleiche Form:

```vue
<Button variant="pill-muted" class="mt-3 ml-2" type="button" @click="duplicate(pkg)">
  {{ $t('packages.duplicate') }}
</Button>
```

- [ ] **Step 3: Prüfen und committen**

Run: `npm run lint`

```bash
git add apps/studio/resources/js/admin/pages/Packages/Index.vue
git commit -m "feat(catalog): Duplizieren in der Paketliste"
```

---

### Task 6: Beschriftungen

**Files:**
- Modify: `apps/studio/resources/js/locales/de.json`

- [ ] **Step 1: Schlüssel ergänzen**

Unter `quotes`:

```json
"saveAsPackage": "Als Paket sichern",
"packageName": "Name",
"emptyPackage": "Angebot ohne Positionen"
```

Unter `packages`:

```json
"duplicate": "Duplizieren"
```

`(Kopie)` steht serverseitig im Controller und braucht keinen Schlüssel — es ist Teil eines Datenwerts, keine Beschriftung.

- [ ] **Step 2: Kein Slop**

Die drei Nachbarn lesen. `emptyPackage` benennt den Zustand und erklärt ihn nicht — kein „Bitte füge zuerst Positionen hinzu".

- [ ] **Step 3: e2e**

Run: `npm run test:e2e`
Betroffen sind Angebotsseite und Paketliste. Frisch säen, dann Baselines erneuern.

- [ ] **Step 4: Commit**

```bash
git add apps/studio/resources/js/locales/de.json
git commit -m "chore(i18n): Beschriftungen fuer Sichern und Duplizieren"
```

---

## Parallelisierung

| Welle | Tasks | Grund |
|---|---|---|
| A | 1, 4 | Action und Duplizieren teilen keine Datei; 4 fasst `PackageTest.php` an, 1 legt `PackageFromQuoteTest.php` neu an. Beide schreiben `routes/admin.php` — **Task 4 wartet auf 1**, oder beide gehen an einen Agenten |
| B | 2, 5 | 2 braucht 1; 5 braucht 4. Verschiedene Dateien |
| C | 3 | braucht 2, schreibt `Projects/Quotes.vue` |
| D | 6 | braucht alles |

`routes/admin.php` ist die einzige echte Kollision: Task 2 und Task 4 tragen beide eine Zeile ein. Entweder nacheinander oder gebündelt.

**Gegenüber S2:** dieser Plan fasst `Projects/Quotes.vue` an (Task 3) — S2 tut das ebenfalls. Laufen beide Scheiben gleichzeitig, ist das die Datei, an der sie kollidieren.

## Self-Review

**Spec-Abdeckung.** Action und Namensraum → Task 1. Route, Berechtigung, Validierung, Audit → Task 2. Dialog → Task 3. Duplizieren mit `is_active`, Namenszusatz und Redirect → Task 4. Knopf → Task 5. Beschriftungen → Task 6.

**Eine Stelle, die beim Umsetzen auffällt.** Der Test „refuses an empty name and a quote without lines" prüft zwei Dinge in einem Fall, weil beide dieselbe Meldung an dasselbe Feld hängen — das Formular hat kein zweites Feld. Wer sie trennen will, trennt sie; der Plan verlangt es nicht.

**Was dieser Plan nicht enthält.** Kein Überschreiben eines vorhandenen Pakets. Keine Kategorien über Paketen. Kein Rückweg vom Paket in ein versendetes Angebot — das ist Q-07.
