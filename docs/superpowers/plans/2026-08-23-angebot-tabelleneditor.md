# S1 · Angebots-Tabelleneditor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Der Entwurf eines Angebots wird eine inline editierbare Tabelle mit Live-Summe, Autosave und verschiebbaren Zeilen; die Positionsliste in Lead- und Projektübersicht kommt aus einem geteilten Baustein.

**Architecture:** Reine Logik (Rabatt-Parser, Zeilen verschieben, größte Positionen) liegt in `resources/js/admin/lib/` und wird von Vitest geprüft — die Vitest-Umgebung ist `node`, kann also nichts mounten. Die Vue-Komponenten bleiben dünn und lesen diese Funktionen. Serverseitig kommt nur eine Drosselung im Audit dazu und eine Prop für ersetzte Angebotsfassungen; der bestehende `PATCH /angebote/{quote}` bleibt der einzige Schreibweg.

**Tech Stack:** Laravel 12 · Inertia · Vue 3 `<script setup>` · Tailwind (nur Utilities, siehe `refactor/tailwind-only`) · shadcn-vue-Wrapper unter `resources/js/ui` · Pest · Vitest

**Spec:** [docs/superpowers/specs/2026-08-23-angebotserstellung-design.md](../specs/2026-08-23-angebotserstellung-design.md)

## Global Constraints

- Formularelemente kommen aus `resources/js/ui`. Kein rohes `<input>`, `<select>` oder `<button>`.
- Deutsche Beschriftungen kurz und nativ. Kein Emoji, kein Ausrufezeichen, keine Beruhigungssätze, keine Systemerzählung. Vor dem Einchecken die drei Nachbarn in `de.json` lesen.
- Kommentare nur für das *Warum*. Kein Kommentar, der den Code nacherzählt.
- Kein Umformatieren, Umbenennen oder Aufräumen von Zeilen, die die Aufgabe nicht anfasst.
- Backend-Tests brauchen die Admin-Oberfläche: `-e APP_SURFACE=admin` am Container. Nicht in `phpunit.xml` festschreiben.
- Geld sind Minor Units (Cent, `int`). Prozente sind Basispunkte (0–10000). Die Grenze wird nur in `resources/js/admin/lib/money.ts` überschritten.
- `unit_price === null` heißt „Enthaltene Leistung", nicht null Euro. `0` ist eine echte Gratis-Zeile.
- Nichts hält eine lebende Verbindung von einem Angebot zurück auf ein Paket.
- Der Editor erscheint nur bei `status === 'draft'`. `Quote::booted()` bleibt der Wächter, nicht der Bildschirm.
- Arbeitsverzeichnis für alle Befehle: `apps/studio`.

---

### Task 1: Rabatt als eine Zelle — Parser

Statt Auswahlfeld plus Wertfeld ein Textfeld. Ein abschließendes `%` macht es zum Prozentsatz, alles andere zum Betrag.

**Files:**
- Modify: `apps/studio/resources/js/admin/lib/money.ts`
- Test: `apps/studio/resources/js/admin/lib/money.test.ts` (neu)

**Interfaces:**
- Consumes: `percentToBasisPoints()`, `eurosToCents()`, `basisPointsToPercent()`, `centsToEuros()` — alle bereits in `money.ts`
- Produces: `parseDiscount(input: string): DiscountField` und `formatDiscount(type: string | null, value: number | null): string`, wobei `type DiscountField = { type: '' | 'percentage' | 'amount'; value: number | null }`. Task 8 und Task 9 verwenden beide.

- [ ] **Step 1: Write the failing test**

`apps/studio/resources/js/admin/lib/money.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { formatDiscount, parseDiscount } from './money'

describe('parseDiscount', () => {
  it('reads a trailing percent sign as a percentage in basis points', () => {
    expect(parseDiscount('10%')).toEqual({ type: 'percentage', value: 1000 })
    expect(parseDiscount('10 %')).toEqual({ type: 'percentage', value: 1000 })
    expect(parseDiscount('10,5%')).toEqual({ type: 'percentage', value: 1050 })
  })

  it('reads everything else as an amount in cents', () => {
    expect(parseDiscount('50')).toEqual({ type: 'amount', value: 5000 })
    expect(parseDiscount('50,00')).toEqual({ type: 'amount', value: 5000 })
  })

  it('reads an empty cell as no discount', () => {
    expect(parseDiscount('')).toEqual({ type: '', value: null })
    expect(parseDiscount('   ')).toEqual({ type: '', value: null })
  })

  it('reads what is not a number as no discount rather than as NaN', () => {
    expect(parseDiscount('abc')).toEqual({ type: '', value: null })
    expect(parseDiscount('%')).toEqual({ type: '', value: null })
  })
})

describe('formatDiscount', () => {
  it('round-trips through parseDiscount', () => {
    expect(parseDiscount(formatDiscount('percentage', 1000))).toEqual({ type: 'percentage', value: 1000 })
    expect(parseDiscount(formatDiscount('amount', 5000))).toEqual({ type: 'amount', value: 5000 })
  })

  it('renders no discount as an empty cell', () => {
    expect(formatDiscount(null, null)).toBe('')
    expect(formatDiscount('percentage', null)).toBe('')
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- resources/js/admin/lib/money.test.ts`
Expected: FAIL — `parseDiscount is not a function`

- [ ] **Step 3: Write minimal implementation**

An `apps/studio/resources/js/admin/lib/money.ts` anhängen:

```ts
export type DiscountField = { type: '' | 'percentage' | 'amount'; value: number | null }

/**
 * Eine Zelle statt Auswahlfeld plus Wertfeld. Das Prozentzeichen ist die
 * Angabe der Einheit, nicht Zierde — deshalb entscheidet es über den Typ.
 */
export function parseDiscount(input: string): DiscountField {
  const trimmed = input.trim()

  if (trimmed === '') {
    return { type: '', value: null }
  }

  const percent = trimmed.endsWith('%')
  const number = percent ? trimmed.slice(0, -1) : trimmed
  const parsed = percent ? percentToBasisPoints(number) : eurosToCents(number)

  return parsed === null || Number.isNaN(parsed)
    ? { type: '', value: null }
    : { type: percent ? 'percentage' : 'amount', value: parsed }
}

export function formatDiscount(type: string | null, value: number | null): string {
  if (!type || value === null) {
    return ''
  }

  return type === 'percentage' ? `${basisPointsToPercent(value)} %` : centsToEuros(value)
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- resources/js/admin/lib/money.test.ts`
Expected: PASS, 6 Tests

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/lib/money.ts apps/studio/resources/js/admin/lib/money.test.ts
git commit -m "feat(quotes): Rabatt als eine Zelle lesen"
```

---

### Task 2: Zeilen verschieben

`sort_order` kommt aus der Array-Position — es fehlt nur die Bewegung.

**Files:**
- Create: `apps/studio/resources/js/admin/lib/rows.ts`
- Test: `apps/studio/resources/js/admin/lib/rows.test.ts`

**Interfaces:**
- Produces: `moveRow<T>(rows: T[], from: number, to: number): T[]` — gibt bei unmöglichem Ziel dasselbe Array zurück (Identität, nicht Kopie). Task 9 verdrahtet Griff und Pfeiltasten damit.

- [ ] **Step 1: Write the failing test**

`apps/studio/resources/js/admin/lib/rows.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { moveRow } from './rows'

describe('moveRow', () => {
  it('moves a row down and closes the gap behind it', () => {
    expect(moveRow(['a', 'b', 'c'], 0, 1)).toEqual(['b', 'a', 'c'])
  })

  it('moves a row up', () => {
    expect(moveRow(['a', 'b', 'c'], 2, 0)).toEqual(['c', 'a', 'b'])
  })

  it('returns the same array when the target is out of range', () => {
    const rows = ['a', 'b']

    expect(moveRow(rows, 0, -1)).toBe(rows)
    expect(moveRow(rows, 1, 2)).toBe(rows)
  })

  it('returns the same array when nothing moves', () => {
    const rows = ['a', 'b']

    expect(moveRow(rows, 1, 1)).toBe(rows)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- resources/js/admin/lib/rows.test.ts`
Expected: FAIL — `Failed to resolve import "./rows"`

- [ ] **Step 3: Write minimal implementation**

`apps/studio/resources/js/admin/lib/rows.ts`:

```ts
/**
 * Identität statt Kopie, wenn nichts passiert: der Aufrufer schreibt das
 * Ergebnis in ein Model und würde sonst einen Autosave für eine Bewegung
 * auslösen, die es nicht gab.
 */
export function moveRow<T>(rows: T[], from: number, to: number): T[] {
  if (to < 0 || to >= rows.length || from === to) {
    return rows
  }

  const next = [...rows]
  next.splice(to, 0, ...next.splice(from, 1))

  return next
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- resources/js/admin/lib/rows.test.ts`
Expected: PASS, 4 Tests

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/lib/rows.ts apps/studio/resources/js/admin/lib/rows.test.ts
git commit -m "feat(quotes): Positionen verschieben"
```

---

### Task 3: Die größten Positionen für die Übersicht

Der Lead-Block zeigt die drei teuersten Positionen und zählt den Rest.

**Files:**
- Create: `apps/studio/resources/js/admin/lib/quoteSummary.ts`
- Test: `apps/studio/resources/js/admin/lib/quoteSummary.test.ts`

**Interfaces:**
- Produces: `topLines<T extends { netTotal: number }>(items: T[], limit?: number): { shown: T[]; rest: number }`, `limit` vorbelegt mit 3. Task 7 verwendet es.

- [ ] **Step 1: Write the failing test**

`apps/studio/resources/js/admin/lib/quoteSummary.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { topLines } from './quoteSummary'

const line = (title: string, netTotal: number) => ({ title, netTotal })

describe('topLines', () => {
  it('takes the three largest by net total and counts the rest', () => {
    const result = topLines([
      line('Anfahrt', 0),
      line('Reportage', 189000),
      line('Buch', 34200),
      line('Zweitfotograf', 49000),
      line('USB', 4900),
    ])

    expect(result.shown.map((item) => item.title)).toEqual(['Reportage', 'Zweitfotograf', 'Buch'])
    expect(result.rest).toBe(2)
  })

  it('counts no rest when everything fits', () => {
    const result = topLines([line('Reportage', 189000), line('Buch', 34200)])

    expect(result.shown).toHaveLength(2)
    expect(result.rest).toBe(0)
  })

  it('sorts an included line last rather than dropping it', () => {
    const result = topLines([line('Anfahrt', 0), line('Reportage', 189000)], 2)

    expect(result.shown.map((item) => item.title)).toEqual(['Reportage', 'Anfahrt'])
  })

  it('leaves the given array untouched', () => {
    const items = [line('a', 1), line('b', 2)]
    topLines(items)

    expect(items.map((item) => item.title)).toEqual(['a', 'b'])
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- resources/js/admin/lib/quoteSummary.test.ts`
Expected: FAIL — `Failed to resolve import "./quoteSummary"`

- [ ] **Step 3: Write minimal implementation**

`apps/studio/resources/js/admin/lib/quoteSummary.ts`:

```ts
/**
 * Was den Preis macht, steht oben — nicht was zuerst getippt wurde. Die
 * Übersicht beantwortet „wofür", das Angebot beantwortet „in welcher Reihenfolge".
 */
export function topLines<T extends { netTotal: number }>(items: T[], limit = 3): { shown: T[]; rest: number } {
  const shown = [...items].sort((a, b) => b.netTotal - a.netTotal).slice(0, limit)

  return { shown, rest: Math.max(0, items.length - shown.length) }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- resources/js/admin/lib/quoteSummary.test.ts`
Expected: PASS, 4 Tests

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/lib/quoteSummary.ts apps/studio/resources/js/admin/lib/quoteSummary.test.ts
git commit -m "feat(quotes): groesste Positionen fuer die Uebersicht"
```

---

### Task 4: Audit drosseln

Autosave schreibt bei jedem Zellwechsel. `AuditLogger::record('quote.updated')` würde mitfeuern und das Log vom Tippen dominieren lassen.

**Files:**
- Modify: `apps/studio/app/Support/AuditLogger.php`
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php:107-112`
- Test: `apps/studio/tests/Feature/Quotes/QuoteTest.php`

**Interfaces:**
- Consumes: `AuditLogger::record()` mit der bestehenden Signatur `(string $event, ?User $actor, array $context, ?string $subject, ?int $projectId)`
- Produces: `AuditLogger::recordThrottled(string $event, CarbonInterval $within, ?User $actor, array $context, ?string $subject, ?int $projectId): ?AuditLog` — gibt `null` zurück, wenn im Fenster schon ein Eintrag zu `event` und `subject` steht.

- [ ] **Step 1: Write the failing test**

An `apps/studio/tests/Feature/Quotes/QuoteTest.php` anhängen. `quoteOwner()` steht bereits oben in der Datei.

```php
it('writes one quote.updated for a burst of autosaves, and one again after the window', function () {
    $user = quoteOwner();
    $quote = Quote::factory()->create();

    $payload = ['items' => [['title' => 'Reportage', 'description' => null, 'quantity' => 1, 'unit_price' => 440000]]];

    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", $payload);
    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", $payload);

    expect(AuditLog::where('event', 'quote.updated')->where('subject', $quote->public_id)->count())->toBe(1);

    $this->travel(16)->minutes();
    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", $payload);

    expect(AuditLog::where('event', 'quote.updated')->where('subject', $quote->public_id)->count())->toBe(2);
});

it('does not let one quote\'s autosave suppress another quote\'s entry', function () {
    $user = quoteOwner();
    $first = Quote::factory()->create();
    $second = Quote::factory()->create();

    $payload = ['items' => [['title' => 'Reportage', 'description' => null, 'quantity' => 1, 'unit_price' => 440000]]];

    $this->actingAs($user, 'staff')->patch("/angebote/{$first->public_id}", $payload);
    $this->actingAs($user, 'staff')->patch("/angebote/{$second->public_id}", $payload);

    expect(AuditLog::where('event', 'quote.updated')->count())->toBe(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="burst of autosaves"`
Expected: FAIL — erwartet 1, bekommt 2

- [ ] **Step 3: Write minimal implementation**

In `apps/studio/app/Support/AuditLogger.php` nach `record()` einfügen, dazu `use Carbon\CarbonInterval;` im Kopf:

```php
/**
 * Für Schreibwege, die pro Bedienschritt feuern statt pro Entscheidung —
 * der Autosave eines Angebotsentwurfs ist einer davon. Ein Entwurf ist per
 * Definition unfertig; seine Zwischenstände sind keine Historie.
 */
public static function recordThrottled(
    string $event,
    CarbonInterval $within,
    ?User $actor = null,
    array $context = [],
    ?string $subject = null,
    ?int $projectId = null,
): ?AuditLog {
    $recent = AuditLog::query()
        ->where('event', $event)
        ->where('subject', $subject)
        ->where('created_at', '>=', now()->sub($within))
        ->exists();

    return $recent ? null : self::record($event, $actor, $context, $subject, $projectId);
}
```

In `apps/studio/app/Quotes/Http/Controllers/QuoteController.php` den Aufruf in `update()` ersetzen, `use Carbon\CarbonInterval;` ergänzen:

```php
AuditLogger::recordThrottled(
    event: 'quote.updated',
    within: CarbonInterval::minutes(15),
    actor: auth('staff')->user(),
    subject: $quote->public_id,
    projectId: $quote->project_id,
);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Quotes/QuoteTest.php`
Expected: PASS — auch der bestehende Test `replaces a draft quote's items wholesale on update`, der nur `exists()` prüft

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Support/AuditLogger.php apps/studio/app/Quotes/Http/Controllers/QuoteController.php apps/studio/tests/Feature/Quotes/QuoteTest.php
git commit -m "feat(quotes): Audit beim Autosave drosseln"
```

---

### Task 5: Ersetzte Fassungen für den Lead

`LeadCaseController` liefert heute nur `latestQuote`. Die Pills in Task 7 brauchen die ersetzten Fassungen dazu.

**Files:**
- Modify: `apps/studio/app/Crm/Http/Controllers/LeadCaseController.php:100`
- Test: `apps/studio/tests/Feature/Crm/LeadCaseTest.php`

**Interfaces:**
- Produces: Prop `supersededQuotes` auf `Leads/Show` — `list<array{id: string, number: ?string, net: int}>`, jüngste zuerst. Task 7 rendert sie.

- [ ] **Step 1: Write the failing test**

An `apps/studio/tests/Feature/Crm/LeadCaseTest.php` anhängen (Importe für `Quote` und `QuoteStatus` im Kopf ergänzen, falls sie fehlen):

```php
it('sends the superseded quote versions alongside the current one', function () {
    $project = Project::factory()->create();
    $old = Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Superseded,
        'number' => 'AN-2026-001',
        'net' => 298000,
    ]);
    Quote::factory()->for($project)->create(['status' => QuoteStatus::Sent, 'net' => 257200]);

    $props = $this->actingAs(staffMember(), 'staff')
        ->get("/anfragen/{$project->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['supersededQuotes'])->toHaveCount(1)
        ->and($props['supersededQuotes'][0]['id'])->toBe($old->public_id)
        ->and($props['supersededQuotes'][0]['number'])->toBe('AN-2026-001')
        ->and($props['supersededQuotes'][0]['net'])->toBe(298000);
});

it('sends an empty superseded list when a project has only one quote', function () {
    $project = Project::factory()->create();
    Quote::factory()->for($project)->create();

    $props = $this->actingAs(staffMember(), 'staff')
        ->get("/anfragen/{$project->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['supersededQuotes'])->toBe([]);
});
```

Die Route ist `routes/admin.php:372` — `/anfragen/{lead}`, gebunden über die `public_id` des Projekts. `staffMember()` steht global in `tests/Pest.php:188`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="superseded quote versions"`
Expected: FAIL — `Undefined array key "supersededQuotes"`

- [ ] **Step 3: Write minimal implementation**

In `apps/studio/app/Crm/Http/Controllers/LeadCaseController.php` direkt unter `'quote' => …` einfügen:

```php
// Die ersetzten Fassungen tragen in der Übersicht nur ihren Preis: was in
// ihnen stand, steht im Angebot selbst.
'supersededQuotes' => $project->quotes()
    ->where('status', QuoteStatus::Superseded)
    ->latest()
    ->get()
    ->map(fn (Quote $quote) => [
        'id' => $quote->public_id,
        'number' => $quote->number,
        'net' => $quote->net,
    ])->all(),
```

Importe `App\Quotes\Enums\QuoteStatus` und `App\Quotes\Models\Quote` im Kopf ergänzen, falls sie fehlen.

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Crm/LeadCaseTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Crm/Http/Controllers/LeadCaseController.php apps/studio/tests/Feature/Crm/LeadCaseTest.php
git commit -m "feat(crm): ersetzte Angebotsfassungen im Lead"
```

---

### Task 6: `LineItems.vue` — ein Baustein für vier Kopien

Dieselbe dreispaltige Positionsliste steht viermal im Repo, drei davon mit identischen Klassen.

**Files:**
- Create: `apps/studio/resources/js/admin/components/LineItems.vue`
- Modify: `apps/studio/resources/js/admin/pages/Packages/Index.vue:42-56`
- Modify: `apps/studio/resources/js/admin/pages/Projects/Show.vue:341-370`

**Interfaces:**
- Produces: Komponente mit Prop `items: LineItemRow[]`, wobei
  `type LineItemRow = { id: string; title: string; description?: string | null; quantity: number; unitPrice: number | null }`.
  Slots: `#note` je Zeile (scoped auf `{ item }`) für Zustandsmarkierungen, `#price` je Zeile (scoped auf `{ item }`) zum Überschreiben der Preisspalte. Task 7 verwendet sie.

Kein Vitest: die Umgebung ist `node` und kann nichts mounten. Die Absicherung ist, dass die drei Aufrufstellen danach dieselben Klassen rendern wie vorher — geprüft über die e2e-Aufnahmen in Task 11.

- [ ] **Step 1: Baustein anlegen**

`apps/studio/resources/js/admin/components/LineItems.vue`:

```vue
<script setup lang="ts">
import { formatEurosRounded } from '@/admin/lib/money'

export type LineItemRow = {
  id: string
  title: string
  description?: string | null
  quantity: number
  unitPrice: number | null
}

defineProps<{ items: LineItemRow[] }>()
</script>

<template>
  <div>
    <div
      v-for="item in items"
      :key="item.id"
      class="flex items-baseline gap-3.5 py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
    >
      <span class="min-w-0 flex-1 text-[13.5px] leading-[1.47]">
        {{ item.title }}
        <span v-if="item.description" class="text-[12px] text-ink-2"> · {{ item.description }}</span>
        <slot name="note" :item="item" />
      </span>
      <span class="w-14 flex-none text-right text-[12.5px] leading-[1.47] tabular-nums text-ink-2">
        {{ item.quantity }}×
      </span>
      <span class="w-28 flex-none text-right text-[13px] leading-[1.47] tabular-nums">
        <slot name="price" :item="item">
          <!-- Eine Position ohne Preis ist eine enthaltene Leistung, keine Null. -->
          {{ item.unitPrice === null ? $t('packages.included') : formatEurosRounded(item.unitPrice) }}
        </slot>
      </span>
    </div>
  </div>
</template>
```

- [ ] **Step 2: `Packages/Index.vue` umstellen**

Den `<div>`-Block der Positionen (heute `Packages/Index.vue:42-56`) ersetzen durch:

```vue
<LineItems :items="pkg.items" />
```

Import ergänzen: `import LineItems from '@/admin/components/LineItems.vue'`

- [ ] **Step 3: `Projects/Show.vue` umstellen**

Der Leistungsumfang trägt zwei Zusätze, die kein anderer Aufrufer hat — beide gehen in die Slots:

```vue
<LineItems :items="scope.items">
  <template #note="{ item }">
    <span v-if="item.state" class="ml-[3px] text-[11.5px] text-action">
      {{ $t(`project.scope_${item.state}`) }}
    </span>
  </template>
  <template #price="{ item }">
    {{ item.unitPrice === null ? $t('packages.included') : euro(item.unitPrice) }}
    <span v-if="item.state === 'changed'" class="mt-0.5 block text-[11.5px] font-normal text-action">
      {{ euro(item.wasUnitPrice) }}
    </span>
  </template>
</LineItems>
```

Die Absätze darunter (`scope.delta`, „Nachtrag senden") bleiben unverändert stehen, sie gehören zur Sektion und nicht zur Liste.

- [ ] **Step 4: Typprüfung und Test-Suite**

Run: `npm run lint && npm run test`
Expected: beide grün

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/components/LineItems.vue apps/studio/resources/js/admin/pages/Packages/Index.vue apps/studio/resources/js/admin/pages/Projects/Show.vue
git commit -m "refactor(admin): Positionsliste als ein Baustein"
```

---

### Task 7: `QuoteSummary.vue` — der Block im Lead

Fassungs-Pills, darunter die drei größten Positionen und die Summe. Der Zustand ohne Angebot bleibt exakt, wie er ist — die Paket-Pills sind der Leerzustand.

**Files:**
- Create: `apps/studio/resources/js/admin/components/QuoteSummary.vue`
- Modify: `apps/studio/resources/js/admin/pages/Leads/Show.vue:429-473`

**Interfaces:**
- Consumes: `topLines()` aus Task 3, `LineItems.vue` aus Task 6, Prop `supersededQuotes` aus Task 5
- Produces: Komponente mit Props `quote: App.Quotes.Data.QuoteData`, `superseded: { id: string; number: string | null; net: number }[]`

- [ ] **Step 1: Komponente anlegen**

`apps/studio/resources/js/admin/components/QuoteSummary.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue'
import LineItems from '@/admin/components/LineItems.vue'
import { formatEurosRounded } from '@/admin/lib/money'
import { topLines } from '@/admin/lib/quoteSummary'

const props = defineProps<{
  quote: App.Quotes.Data.QuoteData
  superseded: { id: string; number: string | null; net: number }[]
}>()

// Ein Entwurf trägt keine Nummer — SendQuote vergibt sie erst beim Versenden.
const label = computed(() => props.quote.number ?? '')

const top = computed(() => topLines(props.quote.items))
</script>

<template>
  <div>
    <div class="flex flex-wrap items-baseline gap-2">
      <span class="rounded-[var(--r-pill)] bg-sunk-2 px-[11px] py-[5px] text-[12px] leading-[1.4]">
        <b class="font-semibold">{{ label || $t('quotes.draft') }}</b>
        {{ quote.statusLabel }}
      </span>
      <span
        v-for="old in superseded"
        :key="old.id"
        class="rounded-[var(--r-pill)] bg-sunk px-[11px] py-[5px] text-[12px] leading-[1.4] text-ink-2 line-through"
      >
        {{ old.number || $t('quotes.draft') }} {{ formatEurosRounded(old.net) }}
      </span>
    </div>

    <div class="mt-2.5">
      <LineItems :items="top.shown" />
      <div v-if="top.rest" class="py-2 text-[12.5px] leading-[1.47] text-ink-2 shadow-[inset_0_1px_0_var(--rule)]">
        {{ $t('quotes.more', { count: top.rest }) }}
      </div>
    </div>

    <div class="flex items-baseline gap-3.5 pt-2.5 shadow-[inset_0_1px_0_#D8D8DC]">
      <span class="min-w-0 flex-1 text-[13px] leading-[1.47] font-semibold">{{ $t('quotes.total') }}</span>
      <span class="w-28 flex-none text-right text-[15px] leading-[1.47] font-semibold tracking-[-0.018em] tabular-nums">
        {{ formatEurosRounded(quote.net) }}
      </span>
    </div>

    <p v-if="quote.validUntil" class="mt-[7px] text-[12px] leading-[1.47] text-ink-2">
      {{ $t('lead.validUntil', { date: day(quote.validUntil) }) }}
    </p>
  </div>
</template>
```

`day()` ist in `Leads/Show.vue` lokal. Statt es zu importieren, das formatierte Datum als Prop hereingeben: Prop `validUntilLabel: string | null` ergänzen und den Absatz darauf umstellen, damit die Komponente keine Datumsformatierung mitschleppt.

- [ ] **Step 2: `Leads/Show.vue` umstellen**

Der `v-if="quote"`-Zweig der Sektion wird zu:

```vue
<QuoteSummary
  v-if="quote"
  :quote="quote"
  :superseded="supersededQuotes"
  :valid-until-label="quote.validUntil ? $t('lead.validUntil', { date: day(quote.validUntil) }) : null"
/>
```

Der `v-else`-Zweig mit den Paket-Pills bleibt Zeile für Zeile unverändert. Nur sein `:href` weicht: statt auf den Reiter zu zeigen, legt ein Klick das Angebot an. Dafür wird der `Link` ein `Button` aus `ui/button` mit `@click`, der auf `/auftraege/{projectId}/angebote` mit `{ package: pkg.id }` postet — der Zweig, den `QuoteController::store()` schon trägt.

Prop `supersededQuotes: { id: string; number: string | null; net: number }[]` in `defineProps` ergänzen.

- [ ] **Step 3: Typprüfung**

Run: `npm run lint`
Expected: grün

- [ ] **Step 4: Backend-Test für den Pill-Weg**

An `apps/studio/tests/Feature/Quotes/QuoteTest.php` anhängen:

```php
it('creates a quote seeded from a package when the lead pill posts one', function () {
    $project = Project::factory()->create();
    $package = Package::factory()->has(PackageItem::factory()->count(2), 'items')->create();

    $this->actingAs(quoteOwner(), 'staff')
        ->post("/auftraege/{$project->public_id}/angebote", ['package' => $package->public_id])
        ->assertRedirect();

    expect($project->quotes()->sole()->items)->toHaveCount(2);
});
```

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="lead pill posts one"`
Expected: PASS — der Weg besteht schon, der Test hält ihn fest, damit S2 ihn nicht wegräumt

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/components/QuoteSummary.vue apps/studio/resources/js/admin/pages/Leads/Show.vue apps/studio/tests/Feature/Quotes/QuoteTest.php
git commit -m "feat(crm): Angebotsblock im Lead als Fassungen"
```

---

### Task 8: `QuoteTable.vue` — Zeilen, Zellen, Live-Summe

Ersetzt den Kartenstapel aus `QuoteItemsEditor.vue`. Noch ohne Autosave und ohne Verschieben — die kommen in Task 9, damit ein Reviewer die Tabelle für sich beurteilen kann.

**Files:**
- Create: `apps/studio/resources/js/admin/components/QuoteTable.vue`
- Modify: `apps/studio/resources/js/admin/pages/Projects/Quotes.vue`
- Delete: `apps/studio/resources/js/admin/components/QuoteItemsEditor.vue`

**Interfaces:**
- Consumes: `parseDiscount()`, `formatDiscount()`, `centsToEuros()`, `eurosToCents()`, `formatEuros()` aus `money.ts`
- Produces: Komponente mit `v-model` auf `QuoteRow[]`, wobei
  `type QuoteRow = { title: string; description: string; quantity: number; unitPrice: string; discount: string }`.
  Task 9 hängt Autosave und Griff daran.

`ui/table` wird **nicht** verwendet: seine Zellen sind für Admin-Listen gestylt (`px-8`, Trennschatten) und passen nicht auf ein Eingaberaster. Die Tabelle bringt ihre eigenen Klassen mit, die Eingabefelder kommen weiter aus `ui/input` und `ui/input-group`.

- [ ] **Step 1: Komponente anlegen**

`apps/studio/resources/js/admin/components/QuoteTable.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { eurosToCents, formatEuros, parseDiscount } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'

export type QuoteRow = {
  title: string
  description: string
  quantity: number
  unitPrice: string
  discount: string
}

const rows = defineModel<QuoteRow[]>({ required: true })

/**
 * Dieselbe Rechnung, die Quote::recalculateTotal() serverseitig führt — hier
 * nur, damit die Summe nicht erst nach dem Speichern erscheint. Verbindlich
 * bleibt die vom Server zurückgegebene Zahl.
 */
function netTotal(row: QuoteRow): number {
  const unit = eurosToCents(row.unitPrice)

  if (unit === null || Number.isNaN(unit)) {
    return 0
  }

  const line = unit * row.quantity
  const { type, value } = parseDiscount(row.discount)

  if (type === 'percentage' && value !== null) {
    return line - Math.round((line * value) / 10000)
  }

  return type === 'amount' && value !== null ? Math.max(0, line - value) : line
}

const subtotal = computed(() => rows.value.reduce((sum, row) => sum + netTotal(row), 0))

function addRow() {
  rows.value = [...rows.value, { title: '', description: '', quantity: 1, unitPrice: '', discount: '' }]
}

function removeRow(index: number) {
  rows.value = rows.value.filter((_, i) => i !== index)
}

defineExpose({ subtotal })
</script>

<template>
  <div>
    <div class="grid grid-cols-[1fr_64px_112px_96px_104px_32px] items-center gap-x-3 border-b border-border pb-1.5 text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">
      <span>{{ $t('quotes.itemTitle') }}</span>
      <span class="text-right">{{ $t('quotes.itemQuantity') }}</span>
      <span class="text-right">{{ $t('quotes.itemPrice') }}</span>
      <span class="text-right">{{ $t('quotes.discount') }}</span>
      <span class="text-right">{{ $t('quotes.total') }}</span>
      <span />
    </div>

    <div
      v-for="(row, index) in rows"
      :key="index"
      class="grid grid-cols-[1fr_64px_112px_96px_104px_32px] items-start gap-x-3 border-b border-border/60 py-2"
    >
      <div class="flex min-w-0 flex-col gap-1">
        <Input v-model="row.title" type="text" :aria-label="$t('quotes.itemTitle')" required />
        <Input
          v-model="row.description"
          type="text"
          class="text-[12px]"
          :aria-label="$t('quotes.itemDescription')"
          :placeholder="$t('quotes.itemDescription')"
        />
      </div>

      <Input
        v-model.number="row.quantity"
        class="tabular-nums"
        type="number"
        min="1"
        :aria-label="$t('quotes.itemQuantity')"
        required
      />

      <InputGroup>
        <InputGroupInput
          v-model="row.unitPrice"
          class="tabular-nums"
          type="text"
          inputmode="decimal"
          :aria-label="$t('quotes.itemPrice')"
          :placeholder="$t('quotes.itemPriceHint')"
        />
        <InputGroupAddon align="inline-end">€</InputGroupAddon>
      </InputGroup>

      <Input
        v-model="row.discount"
        class="tabular-nums"
        type="text"
        inputmode="decimal"
        :aria-label="$t('quotes.discount')"
      />

      <span class="pt-2 text-right text-[13px] tabular-nums">{{ formatEuros(netTotal(row)) }}</span>

      <Button variant="ghost" tone="accent" type="button" :aria-label="$t('common.remove')" @click="removeRow(index)">
        ×
      </Button>
    </div>

    <div class="mt-3 flex items-baseline gap-3">
      <Button variant="outline" type="button" @click="addRow">{{ $t('quotes.addItem') }}</Button>
      <span class="ml-auto text-[17px] font-semibold tabular-nums">{{ formatEuros(subtotal) }}</span>
    </div>
  </div>
</template>
```

Die leere Preiszelle bleibt „Enthaltene Leistung": `eurosToCents('')` gibt `null`, und `netTotal` zählt sie mit 0.

- [ ] **Step 2: `Projects/Quotes.vue` auf die Tabelle umstellen**

- `QuoteItemsEditor` durch `QuoteTable` ersetzen.
- Die Zeilenabbildung anpassen: statt `discountType`/`discountValue` je Zeile jetzt ein `discount`-String, gefüllt über `formatDiscount(item.discountType, item.discountValue)`.
- Im `transform()` vor dem `patch` zurückübersetzen:

```ts
items: data.items.map((row: QuoteRow) => {
  const discount = parseDiscount(row.discount)

  return {
    title: row.title,
    description: row.description || null,
    quantity: row.quantity,
    unit_price: eurosToCents(row.unitPrice),
    discount_type: discount.type || null,
    discount_value: discount.value,
  }
}),
```

- Den Angebotsrabatt in der Fußzeile auf dieselbe eine Zelle umstellen; `setDiscountType()` und die beiden `Select`-Blöcke entfallen.
- Die Lesetabelle bleibt für `status !== 'draft'` stehen; der `v-if`-Zweig, der sie auch für Entwürfe rendert, entfällt.

- [ ] **Step 3: `QuoteItemsEditor.vue` löschen**

```bash
git rm apps/studio/resources/js/admin/components/QuoteItemsEditor.vue
```

- [ ] **Step 4: Prüfen**

Run: `npm run lint && npm run test`
Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Quotes/QuoteTest.php`
Expected: beide grün — die Payload-Form hat sich nicht geändert, nur ihre Herkunft im Client

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/components/QuoteTable.vue apps/studio/resources/js/admin/pages/Projects/Quotes.vue
git commit -m "feat(quotes): Positionen als Tabelle statt Kartenstapel"
```

---

### Task 9: Autosave und Verschieben

**Files:**
- Create: `apps/studio/resources/js/admin/lib/autosave.ts`
- Modify: `apps/studio/resources/js/admin/components/QuoteTable.vue`
- Modify: `apps/studio/resources/js/admin/pages/Projects/Quotes.vue`
- Test: `apps/studio/resources/js/admin/lib/autosave.test.ts`

**Interfaces:**
- Consumes: `moveRow()` aus Task 2
- Produces: `debounced<T extends unknown[]>(fn: (...args: T) => void, delay: number): (...args: T) => void`

- [ ] **Step 1: Write the failing test**

`apps/studio/resources/js/admin/lib/autosave.test.ts`:

```ts
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { debounced } from './autosave'

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

describe('debounced', () => {
  it('collapses a burst into one call with the last arguments', () => {
    const spy = vi.fn()
    const save = debounced(spy, 600)

    save('a')
    save('b')
    save('c')
    vi.advanceTimersByTime(600)

    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('c')
  })

  it('calls again after the window has passed', () => {
    const spy = vi.fn()
    const save = debounced(spy, 600)

    save('a')
    vi.advanceTimersByTime(600)
    save('b')
    vi.advanceTimersByTime(600)

    expect(spy).toHaveBeenCalledTimes(2)
  })

  it('does not call before the window is up', () => {
    const spy = vi.fn()
    debounced(spy, 600)('a')

    vi.advanceTimersByTime(599)

    expect(spy).not.toHaveBeenCalled()
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- resources/js/admin/lib/autosave.test.ts`
Expected: FAIL — `Failed to resolve import "./autosave"`

- [ ] **Step 3: Write minimal implementation**

`apps/studio/resources/js/admin/lib/autosave.ts`:

```ts
export function debounced<T extends unknown[]>(fn: (...args: T) => void, delay: number): (...args: T) => void {
  let timer: ReturnType<typeof setTimeout> | undefined

  return (...args: T) => {
    clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- resources/js/admin/lib/autosave.test.ts`
Expected: PASS, 3 Tests

- [ ] **Step 5: In `Projects/Quotes.vue` verdrahten**

```ts
const save = debounced((quoteId: string) => {
  editForms[quoteId]
    .transform(/* wie in Task 8 */)
    .patch(`/angebote/${quoteId}`, { preserveScroll: true, preserveState: true, only: ['quotes'] })
}, 600)
```

`preserveState: true` hält die getippte Zeile beim Zurückkommen; `only: ['quotes']` holt nur die serverseitig gerechneten Summen nach. Der Speichern-Knopf entfällt, „Versenden" bleibt.

An `QuoteTable.vue` ein Emit `change` ergänzen, das jede Zelle bei `@blur` auslöst, und in `Quotes.vue` daran `save(quote.id)` hängen.

- [ ] **Step 6: Griff ergänzen**

In `QuoteTable.vue` je Zeile als erste Spalte (Rasterspalte `24px` vorn anfügen):

```vue
<button
  type="button"
  class="cursor-grab pt-2 text-ink-2"
  :aria-label="$t('quotes.move')"
  draggable="true"
  @dragstart="dragFrom = index"
  @dragover.prevent
  @drop="drop(index)"
  @keydown.up.prevent="rows = moveRow(rows, index, index - 1)"
  @keydown.down.prevent="rows = moveRow(rows, index, index + 1)"
>⠿</button>
```

```ts
const dragFrom = ref<number | null>(null)

function drop(to: number) {
  if (dragFrom.value !== null) {
    rows.value = moveRow(rows.value, dragFrom.value, to)
    dragFrom.value = null
  }
}
```

Der Griff ist ein `<button>`, damit die Tastatur ihn erreicht — Ziehen allein wäre nicht bedienbar.

- [ ] **Step 7: Prüfen und committen**

Run: `npm run lint && npm run test`

```bash
git add apps/studio/resources/js/admin/lib/autosave.ts apps/studio/resources/js/admin/lib/autosave.test.ts apps/studio/resources/js/admin/components/QuoteTable.vue apps/studio/resources/js/admin/pages/Projects/Quotes.vue
git commit -m "feat(quotes): Autosave und Positionen verschieben"
```

---

### Task 10: Anlege-Dialog entfernen

Zwei Fragen, bevor irgendetwas sichtbar ist — und die Paketauswahl zeigt nur Namen. Beides fällt weg.

**Files:**
- Modify: `apps/studio/resources/js/admin/pages/Projects/Quotes.vue:118-166`
- Modify: `apps/studio/app/Crm/Http/Controllers/ProjectController.php:263-265`
- Test: `apps/studio/tests/Feature/Quotes/QuoteTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('creates a blank draft straight from the button, taking validity from the studio default', function () {
    $project = Project::factory()->create();

    $this->actingAs(quoteOwner(), 'staff')
        ->post("/auftraege/{$project->public_id}/angebote")
        ->assertRedirect();

    $quote = $project->quotes()->sole();

    expect($quote->items)->toHaveCount(0)
        ->and($quote->valid_until)->not->toBeNull();
});

it('stops sending the package list to the quotes screen', function () {
    $project = Project::factory()->create();

    $props = $this->actingAs(quoteOwner(), 'staff')
        ->get("/auftraege/{$project->public_id}/angebote")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props)->not->toHaveKey('packages');
});
```

- [ ] **Step 2: Run tests to verify the second one fails**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="package list to the quotes screen"`
Expected: FAIL — `packages` ist noch da

- [ ] **Step 3: Implementieren**

In `ProjectController::quotes()` den `'packages' => …`-Eintrag streichen; den `Package`-Import entfernen, falls die Klasse in der Datei sonst nicht mehr vorkommt.

In `Projects/Quotes.vue`: `Dialog`-Block, `creating`, `createForm`, `ValidUntilField` im Dialog und die `packages`-Prop entfernen. Der Knopf wird:

```vue
<Button v-if="header.canManageQuotes" type="button" @click="router.post(`/auftraege/${header.id}/angebote`)">
  {{ $t('quotes.create') }}
</Button>
```

`ValidUntilField` bleibt im Einsatz — als Zelle in der Fußzeile des Entwurfs, wo es in Task 8 hingezogen ist.

- [ ] **Step 4: Prüfen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Quotes/QuoteTest.php`
Run: `npm run lint && npm run test`
Expected: grün. Der bestehende Test `creates a quote seeded from a package via the store action` bleibt gültig — der Weg lebt über die Lead-Pill weiter.

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Crm/Http/Controllers/ProjectController.php apps/studio/resources/js/admin/pages/Projects/Quotes.vue apps/studio/tests/Feature/Quotes/QuoteTest.php
git commit -m "feat(quotes): Angebot ohne Dialog anlegen"
```

---

### Task 11: Beschriftungen und e2e-Aufnahmen

**Files:**
- Modify: `apps/studio/resources/js/locales/de.json`
- Modify: e2e-Baselines der betroffenen Seiten

- [ ] **Step 1: Schlüssel ergänzen**

Unter `quotes` einfügen:

```json
"draft": "Entwurf",
"more": "+ {count} weitere",
"move": "Verschieben"
```

- [ ] **Step 2: Schlüssel entfernen**

Mit dem Auswahlfeld entfallen: `quotes.discountType`, `quotes.discountNone`, `quotes.discountPercentage`, `quotes.discountAmount`, `quotes.discountValue`, `quotes.discountValuePercent`, `quotes.discountValueEuros`, `quotes.fromPackage`, `quotes.blank`.

Vor dem Löschen prüfen, dass keiner mehr vorkommt:

```bash
cd apps/studio && for key in discountType discountNone discountPercentage discountAmount discountValue fromPackage blank; do
  echo "== $key"; grep -rn "quotes\.$key" resources/js || true
done
```

Erwartet: keine Treffer. `quotes.discount` bleibt — es beschriftet jetzt die Spalte.

- [ ] **Step 3: Kein Slop**

Die drei Nachbarn jedes neuen Schlüssels in `de.json` lesen. Länger oder erklärend statt benennend heißt umschreiben.

- [ ] **Step 4: e2e neu aufnehmen**

Betroffen sind Angebotsseite, Lead-Fall, Projektübersicht und Paketliste. Die Baselines rotten ohnehin täglich, weil `E2eSeeder` relativ zu `now()` sät — also frisch säen, dann aufnehmen.

Run: `npm run test:e2e`
Erwartet: rote Schnappschüsse auf genau diesen Seiten, danach Baselines erneuern und den Lauf grün sehen.

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/locales/de.json
git commit -m "chore(i18n): Beschriftungen fuer den Tabelleneditor"
```

---

## Self-Review

**Spec-Abdeckung.** Aufbau → Task 8. Anlegen → Task 10. Autosave → Task 9 und Task 4. Rabatt als eine Zelle → Task 1 und Task 8. Reihenfolge → Task 2 und Task 9. Übersichts-Block → Task 3, 5, 6, 7. Audit → Task 4. `LineItems` → Task 6. Beschriftungen → Task 11.

**Zwei Punkte, die beim Umsetzen auffallen werden.**

`QuoteSummary.vue` bekommt in Task 7 zuerst `day()` aus `Leads/Show.vue` untergeschoben und im selben Schritt auf eine Prop `validUntilLabel` umgestellt. Der zweite Weg ist der richtige; der erste steht nur da, weil der Code so entsteht. Wer Task 7 ausführt, schreibt gleich die Prop-Fassung.

`QuoteTable.vue` rechnet die Summe im Client nach, was dieselbe Rechnung an zwei Orten bedeutet — `Quote::recalculateTotal()` und `netTotal()`. Das ist der Preis für eine Summe, die beim Tippen mitläuft; verbindlich bleibt der Server, dessen Zahl nach jedem Autosave zurückkommt. Weicht die Anzeige ab, gewinnt der Server, nicht die Zelle.

**Was dieser Plan nicht enthält.** Suchzeile, Positionsbibliothek und Paket nachschieben stehen in S2; Sichern und Duplizieren in S3.
