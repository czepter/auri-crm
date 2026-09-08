# Hilfe und Dokumentation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein Hilfesystem im Admin-Surface: Artikel als Markdown im Repo,
serverseitig gerendert mit Werten der laufenden Instanz, sichtbar in einer
auf- und zuklappbaren Spalte neben dem Screen, in einer Vollansicht unter
`/hilfe` und über die bestehende ⌘K-Palette.

**Architecture:** Markdown unter `apps/studio/resources/help`, Frontmatter
über `symfony/yaml`, Rendern über `Str::markdown()` — beide Abhängigkeiten
liegen bereits im Lock. Ein gecachter Index bildet Route-Namen auf Artikel ab
und wird in `HandleInertiaRequests::share()` gelesen; der Artikeltext kommt
per `fetch` aus einer JSON-Route, nicht über Inertia. Die Spalte ist
`Rail.vue` mit anderem Inhalt und sitzt im vierten Slot von `Page.vue`, das
dafür zur Seitenhülle aller Admin-Screens wird.

**Tech Stack:** Laravel 13 · Inertia 2 · Vue 3 · Tailwind 4 · Pest · Vitest ·
`league/commonmark` und `symfony/yaml` (beide vorhanden)

**Spec:** [docs/superpowers/specs/2026-09-08-hilfe-und-dokumentation-design.md](../specs/2026-09-08-hilfe-und-dokumentation-design.md)

## Global Constraints

- **Testlauf PHP:** `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest` — vom Repo-Wurzelverzeichnis. Ohne `APP_SURFACE=admin` sind die Admin-Routen nicht registriert und jeder Test darauf endet in 404. `APP_SURFACE` gehört **nicht** in `phpunit.xml`.
- **Testlauf TS:** `pnpm --filter @studio/app test`
- **Lint:** `pnpm lint`
- **Platzhalter-Trennzeichen:** `%api_url%`, `%inbound_address%`, `%mcp_url%`, `%studio_name%` — nie `{{ }}`, das kollidiert mit n8n-Ausdrücken.
- **Platzhalterwerte werden escaped eingesetzt, nach dem Markdown-Rendern.**
- **Höchstens zwei Artikel je Route, einer je `art`.**
- **`wide` auf allen 29 migrierten Screens** — `Page`s Deckel `--container-case` (1180px) darf kein Erscheinungsbild ändern.
- **Anrede in Artikeln: du.**
- **Keine Screenshots in Artikeln, keine E2E-Baselines für dieses Feature.**
- **Copy-Regeln aus CLAUDE.md gelten für jeden Artikelsatz.** Der Test vor jedem Satz: *Sagt er etwas, das der Leser auf seinem Bildschirm nicht sieht?*
- **Neue Abhängigkeiten: keine.** Weder Composer noch npm.
- **Commit-Format:** Conventional Commits, Betreff ≤ 50 Zeichen, deutscher Rumpf nur wenn das *Warum* nicht im Diff steht.

---

## File Structure

| Datei | Verantwortung |
|---|---|
| `apps/studio/resources/js/ui/page/Page.vue` | Vierter Slot `help`, Schwellenentscheidung anhand `$slots.right` |
| `apps/studio/app/Help/Data/HelpEntry.php` | Index-Eintrag: slug, titel, beschreibung, art, reihenfolge, screens, faehigkeit, suchtext |
| `apps/studio/app/Help/Data/HelpArticle.php` | Gerenderter Artikel: slug, titel, art, html |
| `apps/studio/app/Help/Support/HelpFrontmatter.php` | Eine Datei in Frontmatter-Array und Rumpf zerlegen |
| `apps/studio/app/Help/Support/HelpIndex.php` | Alle Dateien einlesen, cachen, nach Route und nach Fähigkeit auflösen |
| `apps/studio/app/Help/Support/HelpPlaceholders.php` | Die vier Werte, einmal, escaped |
| `apps/studio/app/Help/Actions/RenderHelpArticle.php` | Rumpf zu HTML, danach Platzhalter ersetzen |
| `apps/studio/app/Help/Http/Controllers/HelpController.php` | Vier Endpunkte |
| `apps/studio/resources/js/lib/helpPanel.ts` | Modulzustand: offen, überschriebener Slug, Artikel-Map |
| `apps/studio/resources/js/ui/page/HelpRail.vue` | Die Spalte: Umschalter, Artikel, Fehlerzeile, Link-Listener |
| `apps/studio/resources/js/ui/page/HelpButton.vue` | Knopf oder `<Link>`, je nach Schwelle |
| `apps/studio/resources/js/admin/pages/Help/Index.vue` | `/hilfe` |
| `apps/studio/resources/js/admin/pages/Help/Show.vue` | `/hilfe/{slug}` |
| `apps/studio/resources/help/*.md` | Der Korpus |

---

## Task 1: Vierter Slot in `Page.vue` und der Wächter, der die Migration erzwingt

**Files:**
- Modify: `apps/studio/resources/js/ui/page/Page.vue`
- Test: `apps/studio/tests/Feature/Help/PageShellTest.php`

**Interfaces:**
- Consumes: nichts
- Produces: `Page.vue` mit Slot `help`; jede Admin-Seite, die `AdminLayout` rendert, rendert auch `Page`

- [ ] **Step 1: Wächter-Test schreiben**

`apps/studio/tests/Feature/Help/PageShellTest.php`:

```php
<?php

// Die Hilfespalte lebt im vierten Slot von Page.vue. Ein Screen, der
// AdminLayout ohne Page rendert, hat den Slot nicht — und bekommt still
// keine Hilfe. Das ist der Test, der die Migration erzwingt und sie
// erzwungen hält.
it('rendert jede Admin-Seite durch Page.vue', function () {
    $pages = collect(
        \Symfony\Component\Finder\Finder::create()
            ->files()
            ->in(resource_path('js/admin/pages'))
            ->name('*.vue')
    );

    $ohnePage = $pages
        ->map(fn ($file) => [$file->getRelativePathname(), $file->getContents()])
        ->filter(fn ($pair) => str_contains($pair[1], 'AdminLayout'))
        ->reject(fn ($pair) => str_contains($pair[1], 'ui/page/Page.vue')
            || str_contains($pair[1], 'ProjectShell'))
        ->map(fn ($pair) => $pair[0])
        ->values();

    expect($ohnePage->all())->toBe([]);
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help/PageShellTest.php`
Expected: FAIL, die Liste nennt 29 Dateien.

- [ ] **Step 3: Slot in `Page.vue` ergänzen**

In `apps/studio/resources/js/ui/page/Page.vue`, nach `<slot name="right" />`:

```html
        <slot name="right" />

        <!-- Die Hilfespalte. Vorgabeinhalt kommt in Task 7; bis dahin ist der
             Slot leer und kostet nichts. -->
        <slot name="help" />
```

- [ ] **Step 4: Test bleibt rot, Slot ist unabhängig davon da**

Run: `pnpm lint`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/ui/page/Page.vue apps/studio/tests/Feature/Help/PageShellTest.php
git commit -m "test: Wächter für Page.vue als Seitenhülle"
```

---

## Task 2: 29 Screens auf `Page.vue` migrieren

**Files:**
- Modify: die 29 unten gelisteten `.vue`-Dateien
- Test: `apps/studio/tests/Feature/Help/PageShellTest.php` (aus Task 1)

**Interfaces:**
- Consumes: `Page.vue` mit Slot `help` aus Task 1
- Produces: jede Admin-Seite hat den `help`-Slot

- [ ] **Step 1: Rezept auf einer Datei anwenden**

`ProjectShell.vue:24` und `Posteingang.vue:132` führen die Zielform vor: der
Default-Slot nimmt **ein** Element auf, weil `Page`s Mitte eine Flex-Zeile
ist. Zwei Geschwister darin stünden nebeneinander statt untereinander.

`apps/studio/resources/js/admin/pages/Staff/Index.vue`, vorher:

```html
<template>
  <AdminLayout active="team">
    <div data-tokens>
      <PageHead :title="$t('team.title')">
```

nachher:

```html
<template>
  <AdminLayout active="team">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('team.title')">
```

Schließendes `</div>` bleibt, ein `</Page>` kommt davor bzw. danach —
Einrückung um eine Ebene tiefer. Import ergänzen:

```ts
import Page from '@/ui/page/Page.vue'
```

`wide` steht auf **allen** 29: `AdminLayout`s `<main>` hat heute keine
Breitengrenze, ohne `wide` würden zwölf Screens auf großen Bildschirmen
sichtbar schmaler.

- [ ] **Step 2: Auf die übrigen 28 anwenden**

```
pages/Uebersicht.vue          pages/Kalender.vue           pages/Auswertungen.vue
Settings/Index.vue            Settings/Numbering.vue       Settings/Availability.vue
Settings/Calendar.vue         Settings/AutomationRule.vue  Settings/Payments.vue
Settings/Portal.vue           Settings/Automations.vue     Settings/Notifications.vue
Settings/FormTemplates/Create.vue                          Settings/FormTemplates/Edit.vue
Tasks/Index.vue               Leads/Create.vue             Projects/Index.vue
DocumentTemplates/Create.vue  DocumentTemplates/Edit.vue   Contacts/Index.vue
Profile/Index.vue             Packages/Index.vue           Packages/Create.vue
Packages/Edit.vue             Templates/Index.vue          Staff/Index.vue
Staff/Absences.vue            Galleries/Create.vue         Galleries/Index.vue
Notifications/Index.vue
```

Alle liegen unter `apps/studio/resources/js/admin/pages/`.

- [ ] **Step 3: Wächter-Test laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help/PageShellTest.php`
Expected: PASS

- [ ] **Step 4: Bestehende Render-Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/InertiaRenderingTest.php`
Expected: PASS — `InertiaRenderingTest` rendert jede Seite und fängt einen kaputten Wrap.

- [ ] **Step 5: Lint**

Run: `pnpm lint`
Expected: PASS

- [ ] **Step 6: Commit — allein, ohne jede Hilfe-Zeile**

```bash
git add apps/studio/resources/js/admin/pages
git commit -m "refactor: Page.vue als Seitenhülle aller Screens"
```

29 Templates und ein neues Feature in einem Commit hieße, dass ein
verrutschter Screen nicht zuzuordnen ist.

---

## Task 3: Korpus lesen — Frontmatter, Index, Wächter

**Files:**
- Create: `apps/studio/app/Help/Data/HelpEntry.php`
- Create: `apps/studio/app/Help/Support/HelpFrontmatter.php`
- Create: `apps/studio/app/Help/Support/HelpIndex.php`
- Create: `apps/studio/resources/help/lead-anlegen.md`
- Test: `apps/studio/tests/Feature/Help/HelpIndexTest.php`
- Test: `apps/studio/tests/Feature/Help/HelpKorpusTest.php`

**Interfaces:**
- Consumes: nichts
- Produces:
  - `HelpEntry` mit `readonly` Feldern `slug, titel, beschreibung, art, reihenfolge, screens, faehigkeit, suchtext` (`art` ist `string`, `reihenfolge` `?int`, `screens` `array<string>`, `faehigkeit` `?string`)
  - `HelpIndex::all(): Collection<HelpEntry>`
  - `HelpIndex::forRoute(string $routeName, array $capabilities): array` — 0 bis 2 Einträge, Aufgabe vor Referenz
  - `HelpIndex::find(string $slug, array $capabilities): ?HelpEntry`
  - `HelpFrontmatter::parse(string $raw): array{0: array, 1: string}`

- [ ] **Step 1: Ersten Artikel anlegen, damit der Index etwas zu lesen hat**

`apps/studio/resources/help/lead-anlegen.md`:

```markdown
---
titel: Lead anlegen
beschreibung: Eine Anfrage von Hand erfassen und durch die Stufen führen.
art: aufgabe
reihenfolge: 20
screens: [leads.index, leads.show]
faehigkeit: manage_leads
---

## Von Hand erfassen

Die meisten Anfragen kommen über das Formular auf deiner Website herein. Was
am Telefon oder auf einer Messe entsteht, trägst du über **Neue Anfrage**
selbst ein.

Pflicht sind Name und eine Kontaktmöglichkeit. Alles andere — Datum, Anlass,
Ort — kannst du nachtragen, sobald es feststeht.

## Die Stufen

Eine Anfrage wandert über das Board nach rechts. Jede Stufe hält fest, was
als Nächstes von dir erwartet wird, nicht was der Kunde tut. Eine Stufe
zurückzunehmen ist erlaubt und ändert nichts an dem, was schon geschrieben
wurde.
```

- [ ] **Step 2: Wächter-Test für den Korpus schreiben**

`apps/studio/tests/Feature/Help/HelpKorpusTest.php`:

```php
<?php

use App\Help\Support\HelpIndex;
use Illuminate\Support\Facades\Route;

// CLAUDE.md: "Eine Route, die keiner geprüft hat." Ein screens-Eintrag, den
// es nicht gibt, macht einen Artikel unerreichbar und fällt sonst nirgends
// auf — hier fällt er auf.
it('nennt nur Routen, die es gibt', function () {
    $bekannt = collect(Route::getRoutes())->map->getName()->filter()->all();

    $unbekannt = app(HelpIndex::class)->all()
        ->flatMap(fn ($entry) => array_map(
            fn ($screen) => "{$entry->slug}: {$screen}",
            array_diff($entry->screens, $bekannt),
        ))
        ->values();

    expect($unbekannt->all())->toBe([]);
});

it('hält höchstens zwei Artikel je Route, einen je Art', function () {
    $doppelt = app(HelpIndex::class)->all()
        ->flatMap(fn ($entry) => array_map(
            fn ($screen) => ['screen' => $screen, 'art' => $entry->art, 'slug' => $entry->slug],
            $entry->screens,
        ))
        ->groupBy(fn ($row) => $row['screen'].'/'.$row['art'])
        ->filter(fn ($rows) => $rows->count() > 1)
        ->keys();

    expect($doppelt->all())->toBe([]);
});

it('kennt nur die zwei Arten', function () {
    $fremd = app(HelpIndex::class)->all()
        ->reject(fn ($entry) => in_array($entry->art, ['aufgabe', 'referenz'], true))
        ->map->slug;

    expect($fremd->all())->toBe([]);
});
```

- [ ] **Step 3: Index-Test schreiben**

`apps/studio/tests/Feature/Help/HelpIndexTest.php`:

```php
<?php

use App\Help\Support\HelpIndex;

it('findet den Artikel zu einer Route', function () {
    $treffer = app(HelpIndex::class)->forRoute('leads.index', ['manage_leads']);

    expect($treffer)->toHaveCount(1)
        ->and($treffer[0]->slug)->toBe('lead-anlegen')
        ->and($treffer[0]->titel)->toBe('Lead anlegen');
});

it('verschweigt einen Artikel, dessen Fähigkeit fehlt', function () {
    expect(app(HelpIndex::class)->forRoute('leads.index', []))->toBe([])
        ->and(app(HelpIndex::class)->find('lead-anlegen', []))->toBeNull();
});

it('gibt für eine Route ohne Artikel nichts zurück', function () {
    expect(app(HelpIndex::class)->forRoute('dashboard', ['manage_leads']))->toBe([]);
});
```

- [ ] **Step 4: Tests laufen lassen, Fehlschlag bestätigen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help`
Expected: FAIL mit "Class App\Help\Support\HelpIndex not found"

- [ ] **Step 5: `HelpEntry` schreiben**

`apps/studio/app/Help/Data/HelpEntry.php`:

```php
<?php

namespace App\Help\Data;

/**
 * Ein Artikel ohne seinen Text. Was der Index hält, damit die geteilte Prop
 * auf jedem Request beantwortbar ist, ohne eine Datei zu lesen.
 */
final readonly class HelpEntry
{
    /** @param  array<string>  $screens */
    public function __construct(
        public string $slug,
        public string $titel,
        public string $beschreibung,
        public string $art,
        public ?int $reihenfolge,
        public array $screens,
        public ?string $faehigkeit,
        public string $suchtext,
    ) {}
}
```

- [ ] **Step 6: `HelpFrontmatter` schreiben**

`apps/studio/app/Help/Support/HelpFrontmatter.php`:

```php
<?php

namespace App\Help\Support;

use Symfony\Component\Yaml\Yaml;

final class HelpFrontmatter
{
    /**
     * @return array{0: array<string, mixed>, 1: string}  Kopf und Rumpf.
     */
    public static function parse(string $raw): array
    {
        // Ohne Kopf ist die ganze Datei Rumpf. Der Wächter-Test schlägt dann
        // an der fehlenden `art` an und benennt die Datei.
        if (! str_starts_with($raw, "---\n")) {
            return [[], $raw];
        }

        [, $kopf, $rumpf] = explode("---\n", $raw, 3);

        return [Yaml::parse($kopf) ?? [], $rumpf];
    }
}
```

- [ ] **Step 7: `HelpIndex` schreiben**

`apps/studio/app/Help/Support/HelpIndex.php`:

```php
<?php

namespace App\Help\Support;

use App\Help\Data\HelpEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Finder\Finder;

final class HelpIndex
{
    /**
     * Der Schlüssel trägt den Hash über alle mtimes: entrypoint.sh räumt
     * bewusst keine Caches und der Store ist Redis, ein Index aus dem
     * vorigen Release stünde sonst nach dem Deploy noch da.
     */
    public function all(): Collection
    {
        $dateien = collect(Finder::create()->files()->in($this->pfad())->name('*.md'))
            ->map(fn ($file) => [$file->getPathname(), $file->getMTime()])
            ->sortBy(fn ($paar) => $paar[0])
            ->values();

        $schluessel = 'help.index.'.md5($dateien->toJson());

        return Cache::rememberForever(
            $schluessel,
            fn () => $dateien->map(fn ($paar) => $this->lesen($paar[0]))->values(),
        );
    }

    /**
     * @param  array<string>  $capabilities
     * @return array<HelpEntry>  Null bis zwei, Aufgabe vor Referenz.
     */
    public function forRoute(string $routeName, array $capabilities): array
    {
        return $this->all()
            ->filter(fn (HelpEntry $entry) => in_array($routeName, $entry->screens, true))
            ->filter(fn (HelpEntry $entry) => $this->darf($entry, $capabilities))
            ->sortBy(fn (HelpEntry $entry) => $entry->art === 'aufgabe' ? 0 : 1)
            ->values()
            ->all();
    }

    /** @param  array<string>  $capabilities */
    public function find(string $slug, array $capabilities): ?HelpEntry
    {
        return $this->all()
            ->first(fn (HelpEntry $entry) => $entry->slug === $slug
                && $this->darf($entry, $capabilities));
    }

    /** @param  array<string>  $capabilities */
    private function darf(HelpEntry $entry, array $capabilities): bool
    {
        return $entry->faehigkeit === null
            || in_array($entry->faehigkeit, $capabilities, true);
    }

    private function lesen(string $pfad): HelpEntry
    {
        [$kopf, $rumpf] = HelpFrontmatter::parse(file_get_contents($pfad));

        return new HelpEntry(
            slug: basename($pfad, '.md'),
            titel: $kopf['titel'] ?? '',
            beschreibung: $kopf['beschreibung'] ?? '',
            art: $kopf['art'] ?? '',
            reihenfolge: $kopf['reihenfolge'] ?? null,
            screens: $kopf['screens'] ?? [],
            faehigkeit: $kopf['faehigkeit'] ?? null,
            // Reintext für die Suche: Markdown-Zeichen weg, Zeilenumbrüche zu
            // Leerzeichen, damit ein Substring über Zeilengrenzen trifft.
            suchtext: trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(
                ['#', '*', '`', '>', '_'], '', $rumpf,
            )))),
        );
    }

    private function pfad(): string
    {
        return resource_path('help');
    }
}
```

- [ ] **Step 8: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add apps/studio/app/Help apps/studio/resources/help apps/studio/tests/Feature/Help
git commit -m "feat: Hilfe-Index über Markdown-Korpus"
```

---

## Task 4: Die zwei Umgebungswerte, die der Admin-Prozess nicht kennt

**Files:**
- Modify: `apps/studio/config/services.php`
- Modify: `docker-compose.coolify.yml:5-24` (Block `admin.environment`)
- Modify: `apps/studio/.env.example`
- Test: `apps/studio/tests/Feature/Help/HelpUrlsTest.php`

**Interfaces:**
- Consumes: nichts
- Produces: `config('services.api.url')` und `config('services.mcp.url')`

- [ ] **Step 1: Test schreiben**

`apps/studio/tests/Feature/Help/HelpUrlsTest.php`:

```php
<?php

it('liest die API- und MCP-Domain aus der Umgebung', function () {
    expect(config('services.api.url'))->not->toBeNull()
        ->and(config('services.mcp.url'))->not->toBeNull();
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help/HelpUrlsTest.php`
Expected: FAIL — beide `null`.

- [ ] **Step 3: `config/services.php` ergänzen**

Direkt nach dem `gallery`-Block:

```php
    // Die anderen zwei Surfaces, wie sie von außen erreichbar sind. Der
    // Admin-Container kennt nur seine eigene APP_URL; für die Hilfeartikel
    // zum Anfrageformular und zur MCP-Verbindung ist das der falsche Host.
    // Fallback auf APP_URL für den Einzelhost-Betrieb, den die Testsuite
    // fährt — dieselbe Form wie config/portal.php.
    'api' => [
        'url' => rtrim((string) env('API_URL', env('APP_URL', 'http://localhost')), '/'),
    ],

    'mcp' => [
        'url' => rtrim((string) env('MCP_URL', env('APP_URL', 'http://localhost')), '/'),
    ],
```

- [ ] **Step 4: `docker-compose.coolify.yml` ergänzen**

Im `admin`-Service, neben `PORTAL_URL` und `GALLERY_URL`:

```yaml
      API_URL: ${SERVICE_FQDN_API}
      MCP_URL: ${SERVICE_FQDN_MCP}
```

- [ ] **Step 5: `.env.example` ergänzen**

```
API_URL=
MCP_URL=
```

- [ ] **Step 6: Test und Compose-Prüfung laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help/HelpUrlsTest.php`
Expected: PASS

Run: `docker compose run --rm php php docker/app/check-coolify-compose.php`
Expected: PASS — die Datei hat eigene Regeln (keine Anker, keine Merge-Keys), an denen eine Ergänzung scheitern kann.

- [ ] **Step 7: Commit**

```bash
git add apps/studio/config/services.php docker-compose.coolify.yml apps/studio/.env.example apps/studio/tests/Feature/Help/HelpUrlsTest.php
git commit -m "feat: API- und MCP-Domain im Admin-Prozess"
```

---

## Task 5: Artikel rendern und Platzhalter escaped einsetzen

**Files:**
- Create: `apps/studio/app/Help/Data/HelpArticle.php`
- Create: `apps/studio/app/Help/Support/HelpPlaceholders.php`
- Create: `apps/studio/app/Help/Actions/RenderHelpArticle.php`
- Test: `apps/studio/tests/Feature/Help/RenderHelpArticleTest.php`

**Interfaces:**
- Consumes: `HelpEntry`, `HelpFrontmatter`, `HelpIndex` aus Task 3; `config('services.api.url')` aus Task 4
- Produces:
  - `HelpArticle` mit `readonly` Feldern `slug, titel, art, html` (alle `string`)
  - `RenderHelpArticle::__invoke(HelpEntry $entry): HelpArticle`
  - `HelpPlaceholders::werte(): array<string, string>` — Schlüssel ohne Prozentzeichen

- [ ] **Step 1: Test schreiben**

`apps/studio/tests/Feature/Help/RenderHelpArticleTest.php`:

```php
<?php

use App\Help\Actions\RenderHelpArticle;
use App\Help\Support\HelpIndex;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rendert Markdown zu HTML', function () {
    $entry = app(HelpIndex::class)->find('lead-anlegen', ['manage_leads']);

    expect(app(RenderHelpArticle::class)($entry)->html)
        ->toContain('<h2>Von Hand erfassen</h2>');
});

it('setzt die API-Domain ein', function () {
    config(['services.api.url' => 'https://api.beispiel.test']);
    file_put_contents(resource_path('help/platzhalter-probe.md'), <<<'MD'
        ---
        titel: Probe
        beschreibung: Probe
        art: aufgabe
        ---

        Endpunkt: %api_url%/anfragen
        MD);

    $entry = app(HelpIndex::class)->find('platzhalter-probe', []);

    expect(app(RenderHelpArticle::class)($entry)->html)
        ->toContain('https://api.beispiel.test/anfragen');

    unlink(resource_path('help/platzhalter-probe.md'));
});

it('escaped einen Wert, der aus einer Eingabe des Inhabers stammt', function () {
    // Der Studioname steckt geslugged in der Inbound-Adresse. Der Artikeltext
    // ist unser eigener und darf HTML sein; ein Wert aus der Datenbank nicht.
    StudioSettings::query()->first()?->update(['display_name' => '<script>alert(1)</script>']);

    file_put_contents(resource_path('help/escape-probe.md'), <<<'MD'
        ---
        titel: Probe
        beschreibung: Probe
        art: aufgabe
        ---

        Studio: %studio_name%
        MD);

    $entry = app(HelpIndex::class)->find('escape-probe', []);
    $html = app(RenderHelpArticle::class)($entry)->html;

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');

    unlink(resource_path('help/escape-probe.md'));
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help/RenderHelpArticleTest.php`
Expected: FAIL mit "Class App\Help\Actions\RenderHelpArticle not found"

- [ ] **Step 3: `HelpArticle` schreiben**

`apps/studio/app/Help/Data/HelpArticle.php`:

```php
<?php

namespace App\Help\Data;

final readonly class HelpArticle
{
    public function __construct(
        public string $slug,
        public string $titel,
        public string $art,
        public string $html,
    ) {}
}
```

- [ ] **Step 4: `HelpPlaceholders` schreiben**

`apps/studio/app/Help/Support/HelpPlaceholders.php`:

```php
<?php

namespace App\Help\Support;

use App\Settings\Support\StudioSettingsResolver;

final class HelpPlaceholders
{
    public function __construct(private readonly StudioSettingsResolver $settings) {}

    /** @return array<string, string> */
    public function werte(): array
    {
        return [
            'api_url' => (string) config('services.api.url'),
            'mcp_url' => (string) config('services.mcp.url'),
            'studio_name' => (string) ($this->settings->displayName() ?? ''),
            // Derselbe Weg wie StudioOnboardingController:56 — die Adresse
            // wird gemintet, nicht gespeichert, und InboundAddress ist die
            // einzige Stelle, die ihre Form kennt.
            'inbound_address' => (string) (
                $this->settings->inboundSecret() === null
                    ? ''
                    : InboundAddress::catchAll(
                        $this->settings->displayName() ?? '',
                        $this->settings->inboundSecret(),
                    )
            ),
        ];
    }
}
```

Import: `use App\Engagement\Support\InboundAddress;`

- [ ] **Step 5: `RenderHelpArticle` schreiben**

`apps/studio/app/Help/Actions/RenderHelpArticle.php`:

```php
<?php

namespace App\Help\Actions;

use App\Help\Data\HelpArticle;
use App\Help\Data\HelpEntry;
use App\Help\Support\HelpFrontmatter;
use App\Help\Support\HelpPlaceholders;
use Illuminate\Support\Str;

final class RenderHelpArticle
{
    public function __construct(private readonly HelpPlaceholders $platzhalter) {}

    public function __invoke(HelpEntry $entry): HelpArticle
    {
        [, $rumpf] = HelpFrontmatter::parse(
            file_get_contents(resource_path("help/{$entry->slug}.md")),
        );

        // Erst rendern, dann ersetzen: ein Wert, der vor dem Rendern
        // eingesetzt wird, kann die Struktur brechen. Und escaped, weil ein
        // Teil der Werte aus Eingaben des Inhabers stammt — der Artikeltext
        // selbst ist unserer und darf HTML sein.
        $html = Str::markdown($rumpf);

        foreach ($this->platzhalter->werte() as $name => $wert) {
            $html = str_replace("%{$name}%", e($wert), $html);
        }

        return new HelpArticle($entry->slug, $entry->titel, $entry->art, $html);
    }
}
```

- [ ] **Step 6: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add apps/studio/app/Help apps/studio/tests/Feature/Help/RenderHelpArticleTest.php
git commit -m "feat: Hilfeartikel rendern mit Instanzwerten"
```

---

## Task 6: Routen, Controller, geteilte Prop

**Files:**
- Create: `apps/studio/app/Help/Http/Controllers/HelpController.php`
- Modify: `apps/studio/routes/admin.php`
- Modify: `apps/studio/app/Http/Middleware/HandleInertiaRequests.php:78` (nach `recent`)
- Test: `apps/studio/tests/Feature/Help/HelpRoutesTest.php`

**Interfaces:**
- Consumes: `HelpIndex`, `RenderHelpArticle`
- Produces:
  - Routen `help.index`, `help.show`, `help.article`, `help.search`
  - Geteilte Prop `help`: `array<array{slug: string, titel: string, art: string}>`, null bei nicht angemeldeten Nutzern
  - `/hilfe/artikel/{slug}` liefert `{slug, titel, art, html}`
  - `/hilfe/suche` liefert `array<{slug, titel, beschreibung, art, suchtext}>`

- [ ] **Step 1: Test schreiben**

`apps/studio/tests/Feature/Help/HelpRoutesTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('teilt den Artikel zum aktuellen Screen mit', function () {
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff')
        ->get('/anfragen')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('help.0.slug', 'lead-anlegen')
            ->where('help.0.art', 'aufgabe'));
});

it('liefert den Artikel als JSON', function () {
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff')
        ->getJson('/hilfe/artikel/lead-anlegen')
        ->assertOk()
        ->assertJsonPath('slug', 'lead-anlegen')
        ->assertJsonStructure(['slug', 'titel', 'art', 'html']);
});

it('antwortet 404 statt 403, wenn die Fähigkeit fehlt', function () {
    // Dieselbe Regel, die AdminLayout auf seine Navigation anwendet: ein
    // Eintrag, hinter dem für diesen Nutzer nichts steht, existiert für ihn
    // nicht.
    //
    // Gegen manage_settings geprüft, nicht gegen manage_leads: das hat ein
    // Fotograf laut StaffRole::capabilities() ebenfalls, der Test wäre grün,
    // ohne etwas zu prüfen.
    gesperrterArtikel();

    $user = User::factory()->withMfa()->create(['role' => StaffRole::Photographer]);

    $this->actingAs($user, 'staff')
        ->get('/hilfe/gesperrte-probe')
        ->assertNotFound();
});

it('lässt einen Artikel ohne Fähigkeit aus der Suche weg', function () {
    gesperrterArtikel();

    $user = User::factory()->withMfa()->create(['role' => StaffRole::Photographer]);

    $treffer = $this->actingAs($user, 'staff')->getJson('/hilfe/suche')->json();

    expect(collect($treffer)->pluck('slug'))->not->toContain('gesperrte-probe');
});

afterEach(fn () => @unlink(resource_path('help/gesperrte-probe.md')));

it('weist Gäste ab', function () {
    $this->get('/hilfe')->assertRedirect('/login');
});
```

Oben in der Datei, neben den Imports:

```php
use App\Enums\StaffRole;

// Ein Artikel, den nur der Inhaber sehen darf. Als Datei statt als Fixture,
// weil HelpIndex das Verzeichnis liest und nichts anderes.
function gesperrterArtikel(): void
{
    file_put_contents(resource_path('help/gesperrte-probe.md'), <<<'MD'
        ---
        titel: Probe
        beschreibung: Probe
        art: aufgabe
        faehigkeit: manage_settings
        ---

        Rumpf.
        MD);
}
```

`UserFactory` kennt keine Rollen-States; die Rolle wird direkt gesetzt.

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help/HelpRoutesTest.php`
Expected: FAIL — 404 auf `/hilfe/artikel/lead-anlegen`, `help` nicht in den Props.

- [ ] **Step 3: Controller schreiben**

`apps/studio/app/Help/Http/Controllers/HelpController.php`:

```php
<?php

namespace App\Help\Http\Controllers;

use App\Help\Actions\RenderHelpArticle;
use App\Help\Data\HelpEntry;
use App\Help\Support\HelpIndex;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class HelpController
{
    public function __construct(
        private readonly HelpIndex $index,
        private readonly RenderHelpArticle $render,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('Help/Index', [
            'aufgaben' => $this->liste($request, 'aufgabe'),
            'referenz' => $this->liste($request, 'referenz'),
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $entry = $this->index->find($slug, $this->capabilities($request))
            ?? throw new NotFoundHttpException;

        return Inertia::render('Help/Show', [
            'artikel' => ($this->render)($entry),
            'aufgaben' => $this->liste($request, 'aufgabe'),
            'referenz' => $this->liste($request, 'referenz'),
        ]);
    }

    public function article(Request $request, string $slug)
    {
        $entry = $this->index->find($slug, $this->capabilities($request))
            ?? throw new NotFoundHttpException;

        return response()->json(($this->render)($entry));
    }

    public function search(Request $request)
    {
        return response()->json(
            $this->index->all()
                ->filter(fn (HelpEntry $e) => $e->faehigkeit === null
                    || in_array($e->faehigkeit, $this->capabilities($request), true))
                ->map(fn (HelpEntry $e) => [
                    'slug' => $e->slug,
                    'titel' => $e->titel,
                    'beschreibung' => $e->beschreibung,
                    'art' => $e->art,
                    'suchtext' => $e->suchtext,
                ])
                ->values(),
        );
    }

    /** @return array<array{slug: string, titel: string, beschreibung: string}> */
    private function liste(Request $request, string $art): array
    {
        return $this->index->all()
            ->filter(fn (HelpEntry $e) => $e->art === $art)
            ->filter(fn (HelpEntry $e) => $e->faehigkeit === null
                || in_array($e->faehigkeit, $this->capabilities($request), true))
            // Aufgaben sind ein Weg und stehen kuratiert; die Referenz ist ein
            // Nachschlagewerk und steht alphabetisch.
            ->sortBy(fn (HelpEntry $e) => $art === 'aufgabe' ? $e->reihenfolge ?? PHP_INT_MAX : $e->titel)
            ->map(fn (HelpEntry $e) => [
                'slug' => $e->slug,
                'titel' => $e->titel,
                'beschreibung' => $e->beschreibung,
            ])
            ->values()
            ->all();
    }

    /** @return array<string> */
    private function capabilities(Request $request): array
    {
        return array_map(
            fn ($capability) => $capability->value,
            $request->user('staff')->role->capabilities(),
        );
    }
}
```

- [ ] **Step 4: Routen ergänzen**

In `apps/studio/routes/admin.php`, in der Staff-Auth-Gruppe (dieselbe Gruppe,
in der `/posteingang` bei Zeile 277 steht), mit dem Import oben:

```php
use App\Help\Http\Controllers\HelpController;
```

```php
        Route::get('/hilfe', [HelpController::class, 'index'])->name('help.index');
        Route::get('/hilfe/suche', [HelpController::class, 'search'])->name('help.search');
        Route::get('/hilfe/artikel/{slug}', [HelpController::class, 'article'])->name('help.article');
        Route::get('/hilfe/{slug}', [HelpController::class, 'show'])->name('help.show');
```

`/hilfe/suche` und `/hilfe/artikel/{slug}` stehen **vor** `/hilfe/{slug}`,
sonst schluckt der Platzhalter beide.

- [ ] **Step 5: Geteilte Prop ergänzen**

In `apps/studio/app/Http/Middleware/HandleInertiaRequests::share()`, nach
`'recent'`:

```php
            // Welcher Hilfeartikel zu diesem Screen gehört — null bis zwei,
            // Aufgabe zuerst. Aufgelöst aus dem gecachten Index, also ohne
            // Dateizugriff; der Artikeltext kommt erst beim Aufklappen und
            // dann über /hilfe/artikel/{slug}, nicht über Inertia.
            'help' => $user === null ? null : fn () => array_map(
                fn ($entry) => [
                    'slug' => $entry->slug,
                    'titel' => $entry->titel,
                    'art' => $entry->art,
                ],
                app(HelpIndex::class)->forRoute(
                    (string) $request->route()?->getName(),
                    array_map(fn ($c) => $c->value, $user->role->capabilities()),
                ),
            ),
```

Import ergänzen: `use App\Help\Support\HelpIndex;`

- [ ] **Step 6: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help`
Expected: PASS

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/AudienceMatrixTest.php`
Expected: PASS — neue Routen dürfen auf keiner anderen Surface auftauchen.

- [ ] **Step 7: Commit**

```bash
git add apps/studio/app/Help apps/studio/routes/admin.php apps/studio/app/Http/Middleware/HandleInertiaRequests.php apps/studio/tests/Feature/Help/HelpRoutesTest.php
git commit -m "feat: Hilfe-Routen und geteilte Screen-Zuordnung"
```

---

## Task 7: Modulzustand und die Spalte

**Files:**
- Create: `apps/studio/resources/js/lib/helpPanel.ts`
- Create: `apps/studio/resources/js/ui/page/HelpRail.vue`
- Modify: `apps/studio/resources/js/ui/page/Page.vue`
- Test: `apps/studio/resources/js/lib/helpPanel.test.ts`

**Interfaces:**
- Consumes: Prop `help` aus Task 6; Route `/hilfe/artikel/{slug}`
- Produces:
  - `helpPanel.offen: Ref<boolean>`
  - `helpPanel.ueberschrieben: Ref<string | null>`
  - `helpPanel.oeffnen(slug?: string): void`
  - `helpPanel.schliessen(): void`
  - `helpPanel.gesehen(): void` — setzt `help.seen`
  - `helpPanel.artikel(slug: string): Promise<{slug, titel, art, html}>` — mit Modul-Map als Zwischenspeicher

- [ ] **Step 1: Test schreiben**

`apps/studio/resources/js/lib/helpPanel.test.ts`:

```ts
import { beforeEach, describe, expect, it } from 'vitest'
import { helpPanel } from './helpPanel'

beforeEach(() => {
  localStorage.clear()
  helpPanel.zuruecksetzen()
})

describe('helpPanel', () => {
  it('startet offen, solange niemand zugeklappt hat', () => {
    expect(helpPanel.offen.value).toBe(true)
  })

  it('bleibt zu, nachdem einmal zugeklappt wurde', () => {
    helpPanel.schliessen()
    helpPanel.zuruecksetzen()

    expect(helpPanel.offen.value).toBe(false)
  })

  it('vergisst den überschriebenen Artikel bei Navigation', () => {
    // Sonst klebt der in der Palette gewählte Artikel über allen folgenden
    // Screens.
    helpPanel.oeffnen('mcp-claude-verbinden')
    expect(helpPanel.ueberschrieben.value).toBe('mcp-claude-verbinden')

    helpPanel.navigiert()

    expect(helpPanel.ueberschrieben.value).toBeNull()
  })

  it('verbraucht die Erstöffnung erst, wenn die Spalte sichtbar war', () => {
    // Unter der Schwelle gibt es keine Spalte zum Zuklappen. Ein schmales
    // Fenster darf die einzige Entdeckungschance nicht verschlucken.
    helpPanel.zuruecksetzen()
    expect(localStorage.getItem('help.seen')).toBeNull()

    helpPanel.gesehen()

    expect(localStorage.getItem('help.seen')).toBe('1')
  })
})
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `pnpm --filter @studio/app test helpPanel`
Expected: FAIL, Modul nicht gefunden.

- [ ] **Step 3: `helpPanel.ts` schreiben**

```ts
import { ref } from 'vue'

type Artikel = { slug: string; titel: string; art: string; html: string }

// Modulebene, nicht Komponentenzustand: main.ts vergibt keine persistenten
// Inertia-Layouts, jede Seite bringt AdminLayout selbst mit und mountet es
// neu. Was die Navigation überleben soll, lebt hier.
const geladen = new Map<string, Artikel>()

const offen = ref(false)
const ueberschrieben = ref<string | null>(null)

function zuruecksetzen() {
  geladen.clear()
  ueberschrieben.value = null
  // Beim ersten Besuch offen, bis einmal zugeklappt wurde. Ein Hilfesystem,
  // das zugeklappt startet, wird nicht entdeckt.
  offen.value = localStorage.getItem('help.seen') === null
    ? true
    : localStorage.getItem('help.open') === '1'
}

zuruecksetzen()

export const helpPanel = {
  offen,
  ueberschrieben,

  oeffnen(slug?: string) {
    offen.value = true
    localStorage.setItem('help.open', '1')
    if (slug) ueberschrieben.value = slug
  },

  schliessen() {
    offen.value = false
    localStorage.setItem('help.open', '0')
    localStorage.setItem('help.seen', '1')
  },

  // Erst wenn die Spalte tatsächlich gezeigt wurde, ist die Erstöffnung
  // verbraucht.
  gesehen() {
    localStorage.setItem('help.seen', '1')
  },

  navigiert() {
    ueberschrieben.value = null
  },

  async artikel(slug: string): Promise<Artikel> {
    const bekannt = geladen.get(slug)
    if (bekannt) return bekannt

    const antwort = await fetch(`/hilfe/artikel/${slug}`, {
      headers: { Accept: 'application/json' },
    })

    if (!antwort.ok) throw new Error(String(antwort.status))

    const artikel = (await antwort.json()) as Artikel
    geladen.set(slug, artikel)

    return artikel
  },

  zuruecksetzen,
}
```

- [ ] **Step 4: Test laufen lassen**

Run: `pnpm --filter @studio/app test helpPanel`
Expected: PASS

- [ ] **Step 5: `HelpRail.vue` schreiben**

```vue
<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Rail from '@/ui/page/Rail.vue'
import { helpPanel } from '@/lib/helpPanel'

type Eintrag = { slug: string; titel: string; art: string }

const page = usePage()
const eintraege = computed(() => (page.props.help as Eintrag[] | null) ?? [])

const gewaehlt = ref<string | null>(null)
const html = ref('')
const fehler = ref(false)
const laedt = ref(false)

const slug = computed(() => helpPanel.ueberschrieben.value ?? gewaehlt.value ?? eintraege.value[0]?.slug ?? null)

async function laden() {
  if (!slug.value) return

  laedt.value = true
  fehler.value = false

  try {
    html.value = (await helpPanel.artikel(slug.value)).html
  } catch {
    fehler.value = true
  } finally {
    laedt.value = false
  }
}

watch(slug, laden, { immediate: true })
onMounted(() => helpPanel.gesehen())

// Ein rohes <a href> in einem v-html-Block macht einen vollen
// Seitenladevorgang — die offene Fallakte daneben wäre weg, also genau das,
// wogegen die Spalte gebaut ist.
function aufLink(event: MouseEvent) {
  const ziel = (event.target as HTMLElement).closest('a')
  const href = ziel?.getAttribute('href')

  if (!href || !href.startsWith('/')) return

  event.preventDefault()

  if (href.startsWith('/hilfe/')) {
    helpPanel.oeffnen(href.slice('/hilfe/'.length))
    return
  }

  router.visit(href)
}
</script>

<template>
  <Rail v-if="helpPanel.offen.value && eintraege.length">
    <div class="mb-3 flex items-center gap-2">
      <button
        v-for="eintrag in eintraege"
        :key="eintrag.slug"
        class="cursor-pointer rounded-[7px] border-0 bg-transparent px-2 py-1 text-[12px] leading-none"
        :class="eintrag.slug === slug ? 'bg-paper font-semibold text-foreground' : 'text-ink-2'"
        type="button"
        @click="gewaehlt = eintrag.slug; helpPanel.ueberschrieben.value = null"
      >{{ $t(`help.art.${eintrag.art}`) }}</button>

      <button
        class="ml-auto cursor-pointer border-0 bg-transparent text-[12px] text-ink-2"
        type="button"
        @click="helpPanel.schliessen()"
      >{{ $t('help.close') }}</button>
    </div>

    <p v-if="fehler" class="text-[13px] text-foreground">
      {{ $t('help.loadFailed') }}
      <button class="cursor-pointer border-0 bg-transparent p-0 underline" type="button" @click="laden">
        {{ $t('help.retry') }}
      </button>
    </p>

    <div v-else-if="!laedt" class="help-prose" @click="aufLink" v-html="html" />
  </Rail>
</template>
```

- [ ] **Step 6: Vier Schlüssel in `de.json` ergänzen**

Kurz, benennend, keine Beruhigung:

```json
  "help.art.aufgabe": "Aufgabe",
  "help.art.referenz": "Referenz",
  "help.close": "Schließen",
  "help.loadFailed": "Artikel nicht geladen.",
  "help.retry": "Erneut versuchen"
```

- [ ] **Step 7: `HelpRail` als Vorgabeinhalt des Slots setzen**

In `apps/studio/resources/js/ui/page/Page.vue`:

```html
        <slot name="help"><HelpRail /></slot>
```

```ts
import HelpRail from './HelpRail.vue'
```

`HelpRail.vue` liegt in `ui/page/`, `helpPanel.ts` in `js/lib/` — nicht unter
`admin/`. `Page.vue` ist eine `ui/`-Komponente, ein Import von dort nach
`admin/` kehrte die Abhängigkeitsrichtung um.

- [ ] **Step 8: Lint und Tests**

Run: `pnpm lint`
Expected: PASS

Run: `pnpm --filter @studio/app test`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add apps/studio/resources/js/lib/helpPanel.ts apps/studio/resources/js/lib/helpPanel.test.ts apps/studio/resources/js/ui/page/HelpRail.vue apps/studio/resources/js/ui/page/Page.vue apps/studio/resources/js/locales/de.json
git commit -m "feat: Hilfespalte im vierten Slot"
```

---

## Task 8: Schwellen und Knopf

**Files:**
- Create: `apps/studio/resources/js/ui/page/HelpButton.vue`
- Modify: `apps/studio/resources/js/ui/page/Page.vue`
- Modify: `apps/studio/resources/js/ui/page/HelpRail.vue`
- Test: `apps/studio/resources/js/ui/page/HelpButton.test.ts`

**Interfaces:**
- Consumes: `helpPanel`, Prop `help`
- Produces: `HelpButton` mit Prop `mitRail: boolean`

- [ ] **Step 1: Test schreiben**

`apps/studio/resources/js/ui/page/HelpButton.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import HelpButton from './HelpButton.vue'

function render(props: Record<string, unknown>) {
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(HelpButton, props)
  app.use(i18n)
  return renderToString(app)
}

describe('HelpButton', () => {
  it('zeigt nichts, wenn es für den Screen keinen Artikel gibt', async () => {
    expect(await render({ eintraege: [], mitRail: false })).not.toContain('button')
  })
})
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `pnpm --filter @studio/app test HelpButton`
Expected: FAIL, Komponente fehlt.

- [ ] **Step 3: `HelpButton.vue` schreiben**

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useMediaQuery } from '@vueuse/core'
import { helpPanel } from '@/lib/helpPanel'

type Eintrag = { slug: string; titel: string; art: string }

const props = defineProps<{ eintraege: Eintrag[]; mitRail: boolean }>()

// Rail.vue verschwindet unter 1080px. Zwei Rails nebeneinander brauchen mehr:
// bei 1280px blieben 458px Fallakte.
const breitGenug = useMediaQuery(computed(() => props.mitRail
  ? '(min-width: 1536px)'
  : '(min-width: 1080px)'))
</script>

<template>
  <button
    v-if="eintraege.length && breitGenug"
    class="cursor-pointer rounded-[7px] border-0 bg-transparent px-2 py-1 text-[12px] leading-none text-ink-2 hover:bg-sunk"
    type="button"
    @click="helpPanel.oeffnen()"
  >{{ $t('help.open') }}</button>

  <Link
    v-else-if="eintraege.length"
    class="text-[12px] leading-none text-ink-2 no-underline"
    :href="`/hilfe/${eintraege[0].slug}`"
  >{{ $t('help.open') }}</Link>
</template>
```

`de.json`: `"help.open": "Hilfe"`

- [ ] **Step 4: Schwelle auch auf die Spalte anwenden**

In `Page.vue` weiß nur diese Komponente, ob eine Seiten-Rail steht:

```html
<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useMediaQuery } from '@vueuse/core'
import { helpPanel } from '@/lib/helpPanel'
import HelpButton from './HelpButton.vue'

type Eintrag = { slug: string; titel: string; art: string }

const slots = useSlots()
const page = usePage()

const eintraege = computed(() => (page.props.help as Eintrag[] | null) ?? [])

// Nur diese Komponente weiß, ob unter ihr eine Seiten-Rail steht.
const mitRail = computed(() => Boolean(slots.right))
const hilfeSichtbar = useMediaQuery(computed(() => mitRail.value
  ? '(min-width: 1536px)'
  : '(min-width: 1080px)'))
</script>
```

```html
        <slot name="help">
          <HelpRail v-if="hilfeSichtbar" />
        </slot>
```

- [ ] **Step 5: Knopf einsetzen**

`PageHead.vue` nimmt Aktionen in seinem Slot auf; der Knopf gehört dorthin,
wo er auf jedem Screen ohne 29 Änderungen erscheint — also in `Page.vue`
oberhalb des Default-Slots ist er falsch platziert. **Entscheidung: der Knopf
steht in `HelpRail`s Kopf, wenn die Spalte offen ist, und als eigenständiger
Knopf am oberen Rand der Mitte, wenn sie zu ist** — `Page.vue` rendert ihn
absolut positioniert im Mitten-Wrapper:

```html
        <div class="relative flex min-w-0 flex-1 items-stretch max-[640px]:basis-full" :class="wide ? undefined : 'max-w-case'">
          <HelpButton
            v-if="!helpPanel.offen.value"
            class="absolute top-4 right-5 z-10"
            :eintraege="eintraege"
            :mit-rail="mitRail"
          />
          <slot />
        </div>
```

- [ ] **Step 6: Tests und Lint**

Run: `pnpm --filter @studio/app test`
Expected: PASS

Run: `pnpm lint`
Expected: PASS

- [ ] **Step 7: Von Hand ansehen, was die Rechnung nicht zeigt**

Anfragen-Board, `QuoteTable` und Auswertungen bei 1280px und bei 1536px mit
offener Spalte. Befund als `ponytail:`-Kommentar mit Obergrenze in `Page.vue`
festhalten, nicht still mitreparieren.

- [ ] **Step 8: Commit**

```bash
git add apps/studio/resources/js/ui/page apps/studio/resources/js/locales/de.json
git commit -m "feat: Schwellen und Einstieg in die Hilfespalte"
```

---

## Task 9: Vollansicht

**Files:**
- Create: `apps/studio/resources/js/admin/pages/Help/Index.vue`
- Create: `apps/studio/resources/js/admin/pages/Help/Show.vue`
- Test: `apps/studio/tests/Feature/Help/HelpRoutesTest.php` (erweitern)

**Interfaces:**
- Consumes: Props `aufgaben`, `referenz`, `artikel` aus Task 6
- Produces: Inertia-Komponenten `Help/Index` und `Help/Show`

- [ ] **Step 1: Test erweitern**

In `HelpRoutesTest.php`:

```php
it('rendert die Vollansicht mit beiden Ebenen', function () {
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff')
        ->get('/hilfe')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Help/Index')
            ->has('aufgaben')
            ->has('referenz'));
});

it('zeigt auf der Hilfeseite selbst keine Hilfespalte', function () {
    // Ein Artikel, der erklärt, was der Leser gerade offensichtlich bedient.
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff')
        ->get('/hilfe')
        ->assertInertia(fn (Assert $page) => $page->where('help', []));
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help/HelpRoutesTest.php`
Expected: FAIL — Komponente fehlt.

- [ ] **Step 3: `Help/Index.vue` schreiben**

```vue
<script setup lang="ts">
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Link } from '@inertiajs/vue3'

type Eintrag = { slug: string; titel: string; beschreibung: string }

defineProps<{ aufgaben: Eintrag[]; referenz: Eintrag[] }>()
</script>

<template>
  <AdminLayout active="hilfe">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('help.title')" />

        <div class="px-8 pt-7 pb-8">
          <section v-for="gruppe in [{ key: 'aufgaben', rows: aufgaben }, { key: 'referenz', rows: referenz }]" :key="gruppe.key" class="mb-8">
            <h2 class="mb-2 text-[10.5px] tracking-[0.06em] uppercase text-ink-2">
              {{ $t(`help.group.${gruppe.key}`) }}
            </h2>

            <Link
              v-for="eintrag in gruppe.rows"
              :key="eintrag.slug"
              class="block border-b border-[var(--rule)] py-2.5 text-foreground no-underline last:border-0 hover:bg-sunk"
              :href="`/hilfe/${eintrag.slug}`"
            >
              <span class="text-[14px] font-medium">{{ eintrag.titel }}</span>
              <span class="ml-2 text-[13px] text-ink-2">{{ eintrag.beschreibung }}</span>
            </Link>
          </section>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
```

`de.json`: `"help.title": "Hilfe"`, `"help.group.aufgaben": "Aufgaben"`,
`"help.group.referenz": "Referenz"`

- [ ] **Step 4: `Help/Show.vue` schreiben**

```vue
<script setup lang="ts">
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Link } from '@inertiajs/vue3'

type Eintrag = { slug: string; titel: string; beschreibung: string }

defineProps<{
  artikel: { slug: string; titel: string; art: string; html: string }
  aufgaben: Eintrag[]
  referenz: Eintrag[]
}>()
</script>

<template>
  <AdminLayout active="hilfe">
    <Page data-tokens>
      <template #left>
        <nav class="flex w-[196px] flex-none flex-col gap-0.5 p-4">
          <Link
            v-for="eintrag in [...aufgaben, ...referenz]"
            :key="eintrag.slug"
            class="rounded-[7px] px-2.5 py-[9px] text-[14px] leading-none no-underline hover:bg-muted"
            :class="eintrag.slug === artikel.slug ? 'font-semibold text-foreground' : 'text-muted-foreground'"
            :href="`/hilfe/${eintrag.slug}`"
          >{{ eintrag.titel }}</Link>
        </nav>
      </template>

      <div class="min-w-0 flex-1">
        <PageHead :title="artikel.titel" />
        <div class="help-prose max-w-[68ch] px-8 pt-7 pb-8" v-html="artikel.html" />
      </div>
    </Page>
  </AdminLayout>
</template>
```

- [ ] **Step 5: Prosa-Styles schreiben**

In `apps/studio/resources/css/admin.css`. Kein `@tailwindcss/typography` —
eine Abhängigkeit für sieben Elementtypen ist der falsche Tausch.

```css
/* Der einzige Ort in dieser Anwendung, an dem längerer Fließtext steht.
   Werte aus tokens.css; eigene Farben gibt es hier keine. */
.help-prose { font-size: 13.5px; line-height: 1.6; }
.help-prose h2 { margin: 1.5em 0 .4em; font-size: 14px; font-weight: 600; }
.help-prose h2:first-child { margin-top: 0; }
.help-prose h3 { margin: 1.2em 0 .3em; font-size: 13.5px; font-weight: 600; }
.help-prose p { margin: 0 0 .8em; }
.help-prose ul, .help-prose ol { margin: 0 0 .8em; padding-left: 1.2em; }
.help-prose li { margin-bottom: .25em; }
.help-prose a { text-decoration: underline; }
.help-prose code {
  padding: .1em .3em;
  border-radius: var(--r-sm);
  background: var(--sunk);
  font-size: 12.5px;
}
.help-prose pre {
  margin: 0 0 .8em;
  padding: .7em .8em;
  overflow-x: auto;
  border-radius: var(--r-sm);
  background: var(--sunk);
}
.help-prose pre code { padding: 0; background: none; }
```

- [ ] **Step 6: Einstiege ergänzen**

`AdminLayout.vue`, im Benutzermenü neben Profil:

```html
                        <DropdownMenuItem as-child>
                            <Link href="/hilfe">{{ $t("help.title") }}</Link>
                        </DropdownMenuItem>
```

`Settings/Index.vue`, unterhalb der Panes: ein `<Link href="/hilfe">`. **Kein
Eintrag in `SettingsNav`** — die Schiene führt Dinge, die man einstellt.

- [ ] **Step 7: Tests und Lint**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help`
Expected: PASS

Run: `pnpm lint`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add apps/studio/resources/js/admin/pages/Help apps/studio/resources/css/admin.css apps/studio/resources/js/admin/layouts/AdminLayout.vue apps/studio/resources/js/admin/pages/Settings/Index.vue apps/studio/resources/js/locales/de.json apps/studio/tests/Feature/Help/HelpRoutesTest.php
git commit -m "feat: Hilfe-Vollansicht und Einstiege"
```

---

## Task 10: Suche in der ⌘K-Palette

**Files:**
- Modify: `apps/studio/resources/js/admin/components/CommandPalette.vue`
- Test: `apps/studio/resources/js/admin/components/CommandPalette.test.ts`

**Interfaces:**
- Consumes: `/hilfe/suche` aus Task 6; `helpPanel.oeffnen(slug)`
- Produces: nichts für spätere Tasks

- [ ] **Step 1: Test schreiben**

```ts
import { describe, expect, it, vi } from 'vitest'
import { treffer } from './CommandPalette.vue'

describe('Palettensuche', () => {
  it('findet einen Artikel über seinen Volltext, nicht nur den Titel', () => {
    const index = [
      { slug: 'mcp-claude-verbinden', titel: 'Claude verbinden', beschreibung: '', art: 'aufgabe', suchtext: 'Über MCP spricht Claude mit deinem Studio.' },
    ]

    expect(treffer(index, 'MCP').map((t) => t.slug)).toEqual(['mcp-claude-verbinden'])
  })

  it('gibt bei leerer Eingabe nichts zurück', () => {
    expect(treffer([], '')).toEqual([])
  })
})
```

Dafür exportiert `CommandPalette.vue` die reine Funktion:

```ts
export function treffer(index: Eintrag[], frage: string): Eintrag[] {
  const q = frage.trim().toLowerCase()
  if (!q) return []

  return index.filter((e) => e.titel.toLowerCase().includes(q) || e.suchtext.toLowerCase().includes(q))
}
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `pnpm --filter @studio/app test CommandPalette`
Expected: FAIL, `treffer` nicht exportiert.

- [ ] **Step 3: Palette erweitern**

Das `<Input>` hat heute kein `v-model` und keinen Filter — der Doc-Kommentar
sagt es offen. Die Hilfe-Suche gibt ihm seine erste Funktion.

```ts
const frage = ref('')
const index = ref<Eintrag[]>([])

// Einmal beim ersten Öffnen, danach aus dem Modulzustand. Als geteilte
// Inertia-Prop wären es sechzig Kilobyte an jeder Antwort, damit jemand
// vielleicht ⌘K drückt.
let geholt: Promise<Eintrag[]> | null = null

async function oeffnen() {
  dialog.value?.showModal()
  geholt ??= fetch('/hilfe/suche', { headers: { Accept: 'application/json' } }).then((r) => r.json())
  index.value = await geholt
}
```

Im Template: `v-model="frage"` auf dem `<Input>`, die bestehende
`recent()`-Liste nach Titel filtern wenn `frage` gesetzt ist, und darunter
eine zweite Gruppe:

```html
      <template v-if="frage">
        <div class="px-5.5 pt-3 pb-1 text-[10.5px] tracking-[0.07em] uppercase text-ink-2">
          {{ $t('help.title') }}
        </div>

        <button
          v-for="eintrag in treffer(index, frage)"
          :key="eintrag.slug"
          class="flex w-full cursor-pointer items-baseline gap-3 border-0 bg-transparent px-5.5 py-2 text-left text-foreground hover:bg-sunk"
          type="button"
          @click="waehlen(eintrag.slug)"
        >
          <span class="w-[62px] flex-none text-[10.5px] tracking-[0.05em] uppercase text-ink-2">
            {{ $t(`help.art.${eintrag.art}`) }}
          </span>
          <span class="text-[14px] font-medium tracking-[-0.014em]">{{ eintrag.titel }}</span>
        </button>
      </template>
```

```ts
// Dieselbe Schwelle wie der Knopf: darüber klappt die Spalte auf und die
// Arbeit bleibt stehen, darunter wird navigiert.
function waehlen(slug: string) {
  dialog.value?.close()

  if (matchMedia('(min-width: 1080px)').matches) {
    helpPanel.oeffnen(slug)
    return
  }

  router.visit(`/hilfe/${slug}`)
}
```

- [ ] **Step 4: Tests und Lint**

Run: `pnpm --filter @studio/app test`
Expected: PASS

Run: `pnpm lint`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/components/CommandPalette.vue apps/studio/resources/js/admin/components/CommandPalette.test.ts
git commit -m "feat: Hilfe-Suche in der Palette"
```

---

## Task 11: Phase 1 — fünf Artikel, Ton-Gate

**Files:**
- Create: `apps/studio/resources/help/erste-schritte.md`
- Create: `apps/studio/resources/help/projekttypen-und-felder.md`
- Create: `apps/studio/resources/help/mcp-claude-verbinden.md`
- Create: `apps/studio/resources/help/mail-weiterleitung.md`
- Modify: `apps/studio/resources/help/lead-anlegen.md` (aus Task 3, Ton angleichen)

**Interfaces:**
- Consumes: Frontmatter-Format aus Task 3, Platzhalter aus Task 5
- Produces: fünf Artikel; `erste-schritte` verlinkt die beiden Artikel ohne Screen

**Vor dem Schreiben jedes Satzes:** Sagt er etwas, das der Leser auf seinem
Bildschirm nicht sieht? Wenn nein, streichen. Anrede: du. Keine Beruhigung,
keine Systemnarration, keine erfundenen Zahlen, keine Route ohne
`grep routes/`.

- [ ] **Step 1: `erste-schritte.md`**

```yaml
titel: Erste Schritte
beschreibung: Was nach der Einrichtung zuerst dran ist.
art: aufgabe
reihenfolge: 10
screens: [dashboard]
```

Inhalt: die Reihenfolge, in der ein neues Studio sinnvoll anfängt, mit Links
auf `/hilfe/anfrageformular-einbinden` und `/hilfe/mcp-claude-verbinden` —
die beiden Artikel ohne Screen, die sonst nur über die Suche zu finden sind.

- [ ] **Step 2: `projekttypen-und-felder.md`**

```yaml
titel: Projekttypen und Felder
beschreibung: Welche Angaben ein Auftrag trägt, und wie du sie erweiterst.
art: aufgabe
reihenfolge: 30
screens: [projects.index]
faehigkeit: manage_settings
```

Hängt an `projects.index` und **nicht** an `leads.index`, obwohl
`ProjectSettings.vue` auf beiden steht: dort sitzt schon `lead-anlegen`, und
zwei Aufgaben je Route verbietet der Wächter aus Task 3.

- [ ] **Step 3: `mcp-claude-verbinden.md`**

```yaml
titel: Claude verbinden
beschreibung: Dein Studio als Werkzeug in Claude.
art: aufgabe
reihenfolge: 80
faehigkeit: manage_settings
```

Kein `screens` — es gibt keinen MCP-Screen. Nutzt `%mcp_url%`. Der Ablauf ist
Dynamic Client Registration plus Zustimmung auf `/oauth/authorize`: der Leser
kopiert **keinen** Token.

- [ ] **Step 4: `mail-weiterleitung.md`**

```yaml
titel: E-Mail weiterleiten
beschreibung: Post von deinen eigenen Adressen ins Studio holen.
art: aufgabe
reihenfolge: 60
screens: [inbox.index]
```

Nutzt `%inbound_address%`. Beschreibt Weiterleitung — **nicht**
Postfach-Anbindung: das Studio übergibt nie Zugangsdaten
(`docs/inbound-mail.md`).

- [ ] **Step 5: Wächter und Lint**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help`
Expected: PASS — jeder `screens`-Eintrag existiert, keine Route trägt zwei
Aufgaben.

- [ ] **Step 6: Ton beurteilen lassen**

**Halt hier an.** Die fünf Artikel gehen zur Beurteilung, bevor achtzehn
weitere an demselben Ton hängen.

- [ ] **Step 7: Commit**

```bash
git add apps/studio/resources/help
git commit -m "docs: fünf Hilfeartikel"
```

---

## Task 12: Phase 2 — vier Aufgabenartikel

**Files:**
- Create: `apps/studio/resources/help/anfrageformular-einbinden.md` (`%api_url%`, kein `screens`, Snippet aus `docs/inquiry-form.md`)
- Create: `apps/studio/resources/help/angebot-erstellen.md` (`screens: [projects.quotes]`)
- Create: `apps/studio/resources/help/galerie-veroeffentlichen.md` (`screens: [galleries.index]`)
- Create: `apps/studio/resources/help/team-einladen.md` (`screens: [staff.index]`, `faehigkeit: manage_staff`)

**Interfaces:**
- Consumes: Format und Ton aus Task 11
- Produces: neun Aufgabenartikel gesamt

- [ ] **Step 1: Die vier schreiben**

`team-einladen` beschreibt den Code, nicht die Mail: `StaffInvited` kündigt
einen 2FA-Schritt an, den `InvitationController` optional gemacht hat. Der
Widerspruch wird ein Issue, keine stille Mitreparatur.

- [ ] **Step 2: Wächter laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add apps/studio/resources/help
git commit -m "docs: vier weitere Aufgabenartikel"
```

---

## Task 13: Phase 3 — vierzehn Referenzartikel

**Files:**
- Create: `apps/studio/resources/help/{uebersicht,anfragen,projekte,galerien,kontakte,kalender,abwesenheiten,benachrichtigungen,posteingang,pakete,vorlagen,team,auswertungen,einstellungen}-referenz.md`

**Interfaces:**
- Consumes: Format und Ton aus Task 11
- Produces: 23 Artikel gesamt

- [ ] **Step 1: Die vierzehn schreiben**

Je einer pro Navigationseintrag, `art: referenz`, kein `reihenfolge`.
Referenz erklärt **Vokabular und Rechenwege**, nicht Knöpfe. `uebersicht-referenz`
erklärt, wie eine Kennzahl gerechnet wird — ab wann eine Anfrage offen zählt,
was in die Quote eingeht.

- [ ] **Step 2: Wächter laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Help`
Expected: PASS

- [ ] **Step 3: Volle Suite**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest`
Expected: PASS

Run: `pnpm test && pnpm lint`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add apps/studio/resources/help
git commit -m "docs: vierzehn Referenzartikel"
```

---

## Abschluss

- [ ] Drei Issues anlegen — vorhandene Labels und Board-Felder vorher lesen; ein Single-Select ohne bekannte Options-IDs zu beschreiben löscht die Optionen aller Board-Einträge:
  - Inhaber-Screen für API-Token (heute nur `CreateApiTokenCommand`), Voraussetzung für einen n8n-Artikel
  - Multi-Domain-Mail über die API (`MAIL_INBOUND_SIGNING_SECRET` ist Deployment-Konfiguration, kein Inhaber-Feature)
  - `StaffInvited` kündigt einen 2FA-Schritt an, den `InvitationController` optional gemacht hat
- [ ] Branch auf `<type>/<slug>` umbenennen — `feat/hilfe-und-dokumentation`. CI prüft Basis (`dev`) und Namensform.
- [ ] PR gegen `dev` öffnen, `Closes` für die zugehörigen Issues **bei Erstellung** in den Rumpf — später ergänzt lösen sie nicht aus.
