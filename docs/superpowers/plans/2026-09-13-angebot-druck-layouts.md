# Angebot-Drucklayouts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a studio pick one of five print layouts for its Angebote (Klassisch/Minimal/Brief/Kopfband/Struktur), see a real HTML preview with mock data in Einstellungen → Belege & Fristen, and have `RenderQuotePdf` actually render the chosen layout — including the quote number and Gültig-bis date, which today print nowhere at all.

**Architecture:** One `quote_layout` column on `studio_settings` (plain string, mirrors `vat_mode`'s convention). `App\Quotes\Enums\QuoteLayout` gives the five values a closed domain. `RenderQuotePdf` dispatches to one of five `QuoteLayoutRenderer` implementations under `app/Quotes/Support/Layouts/`, each producing the full body (recipient, meta, items, summary) — the slider-priced path (`sliderRows()`) stays untouched and outside this dispatch. A new `GET /einstellungen/dokumente/angebot-vorschau` route renders a fixed, never-persisted sample quote through the chosen (or saved) layout for the settings-page iframe.

**Tech Stack:** Laravel (PHP 8.3, Pest), Inertia/Vue 3 + TypeScript, shadcn-vue.

**Spec:** [docs/superpowers/specs/2026-09-13-angebot-druck-layouts-design.md](../specs/2026-09-13-angebot-druck-layouts-design.md)

## Global Constraints

- Money is always minor units (int), never float — `DiscountCalculator::resolve()` is the one place a percentage becomes an amount.
- `sliderRows()` in `RenderQuotePdf` is out of scope — every layout renderer only handles the classic (non-slider) item path.
- No new dependency — five layout classes and a CSS addition, nothing external.
- Conventional Commits, subject ≤ 50 chars; `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>` on every commit.
- Reference markup for all five layouts already exists and must be matched exactly: `apps/studio/resources/js/admin/pages/Settings/previews/QuoteLayoutSample.vue` (Storybook: "Dokumente/Angebot-Layouts").

---

## Task 1: `QuoteLayout` enum, migration, resolver

**Files:**
- Create: `apps/studio/app/Quotes/Enums/QuoteLayout.php`
- Create: `apps/studio/database/migrations/2026_09_13_120000_add_quote_layout_to_studio_settings_table.php`
- Modify: `apps/studio/app/Settings/Models/StudioSettings.php` (`$fillable`)
- Modify: `apps/studio/app/Settings/Support/StudioSettingsResolver.php`
- Test: `apps/studio/tests/Feature/Settings/StudioSettingsQuoteLayoutTest.php`

**Interfaces:**
- Produces: `App\Quotes\Enums\QuoteLayout` (string-backed enum, cases `Classic`/`Minimal`/`Letter`/`HeaderBand`/`Structured`, values `classic`/`minimal`/`letter`/`header_band`/`structured`), `StudioSettingsResolver::quoteLayout(): string`.

- [ ] **Step 1: Write the enum**

```php
<?php

namespace App\Quotes\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The five print layouts RenderQuotePdf can render an Angebot in — one
 * studio-wide choice (App\Settings\Support\StudioSettingsResolver::
 * quoteLayout()), not per template. Reference markup for each case:
 * apps/studio/resources/js/admin/pages/Settings/previews/
 * QuoteLayoutSample.vue.
 */
#[TypeScript]
enum QuoteLayout: string
{
    case Classic = 'classic';
    case Minimal = 'minimal';
    case Letter = 'letter';
    case HeaderBand = 'header_band';
    case Structured = 'structured';
}
```

- [ ] **Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->string('quote_layout')->default('classic');
        });

        DB::statement("alter table studio_settings add constraint studio_settings_quote_layout_check check (quote_layout in ('classic', 'minimal', 'letter', 'header_band', 'structured'))");
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('quote_layout');
        });
    }
};
```

- [ ] **Step 3: Add the column to `StudioSettings::$fillable`**

In `apps/studio/app/Settings/Models/StudioSettings.php`, in the `$fillable` array, right after the `'vat_mode', 'vat_rate', 'vat_note',` line, add:

```php
        'quote_layout',
```

- [ ] **Step 4: Write the failing test**

```php
<?php

use App\Settings\Support\StudioSettingsResolver;

it('defaults quote_layout to classic', function () {
    [$user, $project] = studioWithProject();

    expect(app(StudioSettingsResolver::class)->quoteLayout())->toBe('classic');
});

it('reads back a saved quote_layout', function () {
    [$user, $project] = studioWithProject();

    app(StudioSettingsResolver::class)->set('quote_layout', 'header_band');

    expect(app(StudioSettingsResolver::class)->quoteLayout())->toBe('header_band');
});
```

- [ ] **Step 5: Run test to verify it fails**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Settings/StudioSettingsQuoteLayoutTest.php`
Expected: FAIL — `quoteLayout()` does not exist, and the migration hasn't run in the test database.

- [ ] **Step 6: Add `quoteLayout()` to `StudioSettingsResolver`**

In `apps/studio/app/Settings/Support/StudioSettingsResolver.php`, directly after `vatNote()` (around line 66), add:

```php
    /** One of QuoteLayout's five values — see that enum for the closed domain the check constraint also enforces. */
    public function quoteLayout(): string
    {
        return $this->row()->quote_layout;
    }
```

- [ ] **Step 7: Run test to verify it passes**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Settings/StudioSettingsQuoteLayoutTest.php`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add apps/studio/app/Quotes/Enums/QuoteLayout.php \
  apps/studio/database/migrations/2026_09_13_120000_add_quote_layout_to_studio_settings_table.php \
  apps/studio/app/Settings/Models/StudioSettings.php \
  apps/studio/app/Settings/Support/StudioSettingsResolver.php \
  apps/studio/tests/Feature/Settings/StudioSettingsQuoteLayoutTest.php
git commit -m "feat(quotes): add quote_layout studio setting"
```

---

## Task 2: Wire `quote_layout` through `StudioSettingsData` and the Dokumente-Pane save action

**Files:**
- Modify: `apps/studio/app/Settings/Data/StudioSettingsData.php`
- Modify: `apps/studio/app/Settings/Http/Controllers/StudioSettingsController.php` (`updateDocuments()`)
- Test: `apps/studio/tests/Feature/Settings/StudioSettingsQuoteLayoutTest.php` (extend)

**Interfaces:**
- Consumes: `StudioSettingsResolver::quoteLayout(): string` (Task 1).
- Produces: `StudioSettingsData->quoteLayout: string`, `PATCH /einstellungen/dokumente` accepts `quote_layout`.

- [ ] **Step 1: Write the failing test**

Append to `apps/studio/tests/Feature/Settings/StudioSettingsQuoteLayoutTest.php`:

```php
it('saves quote_layout through the Dokumente pane', function () {
    [$user, $project] = studioWithProject();

    $this->actingAs($user, 'staff')->patch('/einstellungen/dokumente', [
        'quote_validity_days' => 14,
        'payment_term_days' => 14,
        'deposit_percentage' => 3000,
        'widerruf_included' => false,
        'quote_layout' => 'minimal',
    ])->assertRedirect();

    expect(app(StudioSettingsResolver::class)->quoteLayout())->toBe('minimal');
});

it('rejects an unknown quote_layout', function () {
    [$user, $project] = studioWithProject();

    $this->actingAs($user, 'staff')->patch('/einstellungen/dokumente', [
        'quote_validity_days' => 14,
        'payment_term_days' => 14,
        'deposit_percentage' => 3000,
        'widerruf_included' => false,
        'quote_layout' => 'not-a-layout',
    ])->assertSessionHasErrors('quote_layout');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Settings/StudioSettingsQuoteLayoutTest.php`
Expected: FAIL — `quote_layout` isn't validated or persisted yet.

- [ ] **Step 3: Add `quote_layout` to `updateDocuments()`**

In `apps/studio/app/Settings/Http/Controllers/StudioSettingsController.php`, in `updateDocuments()`'s `$request->validate([...])`, add after `'widerruf_text'`:

```php
            'quote_layout' => ['required', Rule::enum(QuoteLayout::class)],
```

(add `use App\Quotes\Enums\QuoteLayout;` to the file's imports if not already present — check first, `Rule` is almost certainly already imported since `updateVat()` uses `Rule::enum`).

After `$settings->set('widerruf_text', ...)`, add:

```php
        $settings->set('quote_layout', $validated['quote_layout'], $actor);
```

- [ ] **Step 4: Add `quoteLayout` to `StudioSettingsData`**

In `apps/studio/app/Settings/Data/StudioSettingsData.php`, add a constructor property after `widerrufText`:

```php
        public string $quoteLayout,
```

and in `fromResolver()`, after `widerrufText: $settings->widerrufText(),`:

```php
            quoteLayout: $settings->quoteLayout(),
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Settings/StudioSettingsQuoteLayoutTest.php`
Expected: PASS (all four tests in the file)

- [ ] **Step 6: Commit**

```bash
git add apps/studio/app/Settings/Data/StudioSettingsData.php \
  apps/studio/app/Settings/Http/Controllers/StudioSettingsController.php \
  apps/studio/tests/Feature/Settings/StudioSettingsQuoteLayoutTest.php
git commit -m "feat(quotes): save quote_layout from the Dokumente pane"
```

---

## Task 3: `QuoteLayoutRenderer` interface + `ClassicQuoteLayout`, wired into `RenderQuotePdf`

This is the task that fixes the real gap: today `RenderQuotePdf` never prints the quote number or Gültig-bis date at all, and its item table has no `.doc-table` class. Classic is the layout that is closest to today's intent, so it absorbs that fix.

**Files:**
- Create: `apps/studio/app/Quotes/Support/Layouts/QuoteLayoutRenderer.php` (interface)
- Create: `apps/studio/app/Quotes/Support/Layouts/ClassicQuoteLayout.php`
- Modify: `apps/studio/app/Quotes/Actions/RenderQuotePdf.php`
- Test: `apps/studio/tests/Feature/Quotes/QuoteLayoutRenderingTest.php`

**Interfaces:**
- Produces:
  ```php
  interface QuoteLayoutRenderer
  {
      /** @param array<string, mixed> $mergeData */
      public function render(Quote $quote, array $mergeData, string $templateBody): string;
  }
  ```
- Consumes (from `Quote`, already present): `recipientContact`, `recipient_street`, `recipient_postcode`, `recipient_city`, `number`, `valid_until` (Carbon|null), `items` (Collection<QuoteItem>), `total`, `discount_amount`, `net`, `vat_mode`, `vat_rate`, `vat_amount`.

- [ ] **Step 1: Write the interface**

```php
<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;

/**
 * One implementation per App\Quotes\Enums\QuoteLayout case. Builds the full
 * body RenderQuotePdf wraps in the shared letterhead/footer — recipient,
 * meta (Angebotsnummer, Datum, Gültig bis), the classic item table and the
 * summary chain (Zwischensumme → Rabatt → Netto → USt. → Gesamtbetrag).
 * Never called for a slider-priced quote — RenderQuotePdf::items() branches
 * on Quote::hasSlider() before any layout dispatch.
 *
 * Reference markup for every implementation:
 * apps/studio/resources/js/admin/pages/Settings/previews/
 * QuoteLayoutSample.vue (Storybook: "Dokumente/Angebot-Layouts").
 */
interface QuoteLayoutRenderer
{
    /**
     * $templateBody is the already merge-field-resolved DocumentTemplate
     * body (intro/legal prose) — RenderQuotePdf resolves it once via
     * MergeFieldResolver before calling in, so no implementation touches
     * MergeFieldResolver itself.
     *
     * @param  array<string, mixed>  $mergeData
     */
    public function render(Quote $quote, array $mergeData, string $templateBody): string;
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;

it('prints the quote number and Gueltig-bis date under the classic layout', function () {
    [$user, $project] = studioWithProject();

    $quote = Quote::factory()->for($project)->create([
        'number' => '2026-014',
        'valid_until' => '2026-09-26',
        'vat_mode' => 'standard',
        'vat_rate' => 1900,
    ]);

    app(StudioSettingsResolver::class)->set('quote_layout', 'classic');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('2026-014')
        ->and($html)->toContain('26.09.2026')
        ->and($html)->toContain('doc-table')
        ->and($html)->toContain('doc-layout-classic');
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Quotes/QuoteLayoutRenderingTest.php`
Expected: FAIL — none of the four assertions hold against today's output.

- [ ] **Step 4: Write `ClassicQuoteLayout`**

```php
<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Support\Number;

/**
 * Reference: apps/studio/resources/js/admin/pages/Settings/previews/
 * QuoteLayoutSample.vue, "Klassisch" story. Absender/Empfänger links, eine
 * Meta-Box rechts (Angebotsnr., Datum, Gültig bis), Positionstabelle mit
 * Kopfzeile, Summenblock rechtsbündig darunter.
 */
class ClassicQuoteLayout implements QuoteLayoutRenderer
{
    public function render(Quote $quote, array $mergeData, string $templateBody): string
    {
        $studio = $mergeData['studio'] ?? [];
        $studioLine = collect([$studio['name'] ?? null, $studio['street'] ?? null])
            ->filter()
            ->implode(' · ');

        return '<div class="doc-layout-classic">'
            .'<div class="dlc-head">'
            .'<div>'
            .($studioLine === '' ? '' : '<div class="dlc-from">'.e($studioLine).'</div>')
            .$this->recipient($quote)
            .'</div>'
            .$this->meta($quote)
            .'</div>'
            .$templateBody
            .$this->items($quote)
            .'</div>';
    }

    private function recipient(Quote $quote): string
    {
        $name = $quote->recipientContact?->name;

        if ($name === null) {
            return '';
        }

        $lines = [e($name)];

        if ($quote->recipient_street !== null) {
            $lines[] = e($quote->recipient_street);
        }

        $cityLine = trim(($quote->recipient_postcode ?? '').' '.($quote->recipient_city ?? ''));

        if ($cityLine !== '') {
            $lines[] = e($cityLine);
        }

        return '<div class="dlc-recipient">'.implode('<br>', $lines).'</div>';
    }

    private function meta(Quote $quote): string
    {
        $rows = [
            ['Angebotsnr.', $quote->number ?? ''],
            ['Datum', $quote->created_at?->format('d.m.Y') ?? ''],
        ];

        if ($quote->valid_until !== null) {
            $rows[] = ['Gültig bis', $quote->valid_until->format('d.m.Y')];
        }

        $cells = collect($rows)
            ->map(fn (array $row) => '<tr><td>'.e($row[0]).'</td><td>'.e($row[1]).'</td></tr>')
            ->implode('');

        return '<table class="dlc-meta"><tr><td colspan="2" class="dlc-title">Angebot</td></tr>'.$cells.'</table>';
    }

    private function items(Quote $quote): string
    {
        $rows = $quote->items->map(fn (QuoteItem $item) => $this->row(
            e($item->title).($item->description === null ? '' : '<br><small>'.e($item->description).'</small>'),
            (string) $item->quantity,
            $item->isIncluded() ? '' : $this->money($item->unit_price),
            $item->isIncluded() ? 'inklusive' : $this->money($item->discountedLineTotal()),
        ))->implode('');

        return '<table class="doc-table"><thead><tr>'
            .'<th>Beschreibung</th><th class="doc-num">Menge</th><th class="doc-num">Einzelpreis</th><th class="doc-num">Betrag</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>'
            .$this->summary($quote);
    }

    private function row(string $label, string $quantity, string $unitPrice, string $amount): string
    {
        return '<tr><td>'.$label.'</td><td class="doc-num">'.$quantity.'</td><td class="doc-num">'.$unitPrice.'</td><td class="doc-num">'.$amount.'</td></tr>';
    }

    private function summary(Quote $quote): string
    {
        $rows = ['<tr><td>Zwischensumme</td><td class="doc-num">'.$this->money($quote->total).'</td></tr>'];

        if ($quote->discount_amount > 0) {
            $rows[] = '<tr><td>Rabatt</td><td class="doc-num">−'.$this->money($quote->discount_amount).'</td></tr>';
            $rows[] = '<tr><td>Netto</td><td class="doc-num">'.$this->money($quote->net).'</td></tr>';
        }

        if ($quote->vat_mode === 'standard') {
            $rate = rtrim(rtrim(number_format($quote->vat_rate / 100, 2, ',', '.'), '0'), ',');
            $rows[] = '<tr><td>USt. '.$rate.' %</td><td class="doc-num">'.$this->money($quote->vat_amount ?? 0).'</td></tr>';
            $rows[] = '<tr class="dlc-total"><td>Gesamtbetrag</td><td class="doc-num">'.$this->money($quote->net + ($quote->vat_amount ?? 0)).'</td></tr>';
        }

        return '<table class="dlc-summary">'.implode('', $rows).'</table>';
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
```

- [ ] **Step 5: Refactor `RenderQuotePdf` to dispatch to the layout renderer**

In `apps/studio/app/Quotes/Actions/RenderQuotePdf.php`:

Add imports:

```php
use App\Quotes\Enums\QuoteLayout;
use App\Quotes\Support\Layouts\ClassicQuoteLayout;
use App\Quotes\Support\Layouts\QuoteLayoutRenderer;
```

Replace the `html()` method's `$body` assembly:

```php
    public function html(Quote $quote): string
    {
        $project = $quote->project;
        $data = $this->mergeData->fromProject($project, $this->settings);

        $template = DocumentTemplate::query()
            ->current()
            ->ofType(DocumentTemplateType::Quote)
            ->forShootKind($project->primaryShoot?->kind)
            ->orderBy('id')
            ->first();

        $templateBody = MergeFieldResolver::resolve($template?->body ?? '', $data)
            .$this->letter($quote->intro, $data);

        $body = $quote->hasSlider()
            ? $this->recipient($quote).$templateBody.$this->sliderItems($quote)
            : $this->layoutRenderer()->render($quote, $data, $templateBody);

        $body .= $this->letter($quote->outro, $data);

        return $this->renderer->wrap(DocumentTemplateType::Quote, $body, $this->settings);
    }

    private function layoutRenderer(): QuoteLayoutRenderer
    {
        $layout = QuoteLayout::tryFrom($this->settings->quoteLayout()) ?? QuoteLayout::Classic;

        return match ($layout) {
            QuoteLayout::Classic => new ClassicQuoteLayout,
            // The other four cases are added in Task 4 — until then every
            // non-classic setting falls back to Classic rather than a
            // missing-class fatal error.
            default => new ClassicQuoteLayout,
        };
    }
```

Rename `items()` to `sliderItems()` and delete everything from `$rows = $quote->items->map(...)` through the end of the old `items()` method (the non-slider branch) — `ClassicQuoteLayout::items()` replaces it. `sliderItems()` keeps exactly its old body:

```php
    private function sliderItems(Quote $quote): string
    {
        return '<table><tbody>'.$this->sliderRows($quote).'</tbody></table>';
    }
```

Delete `row()`, `summary()` and `recipient()`'s callers inside the old non-slider `items()` — but **keep** `recipient()`, `letter()`, `sliderRows()`, `timeLine()`, `money()` and `row()` exactly as they are: `sliderRows()` still calls `row()`, and `recipient()`/`letter()`/`money()` are still used above.

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Quotes/QuoteLayoutRenderingTest.php tests/Feature/Quotes/QuoteRecipientTest.php tests/Feature/Quotes/QuoteTimeLineTest.php`
Expected: PASS — including the two pre-existing files, since `recipient()` and the slider path are unchanged.

- [ ] **Step 7: Commit**

```bash
git add apps/studio/app/Quotes/Support/Layouts/QuoteLayoutRenderer.php \
  apps/studio/app/Quotes/Support/Layouts/ClassicQuoteLayout.php \
  apps/studio/app/Quotes/Actions/RenderQuotePdf.php \
  apps/studio/tests/Feature/Quotes/QuoteLayoutRenderingTest.php
git commit -m "feat(quotes): render the classic layout, fix missing quote number/Gueltig-bis"
```

---

## Task 4: The remaining four layouts — `MinimalQuoteLayout`, `LetterQuoteLayout`, `HeaderBandQuoteLayout`, `StructuredQuoteLayout`

**Files:**
- Create: `apps/studio/app/Quotes/Support/Layouts/MinimalQuoteLayout.php`
- Create: `apps/studio/app/Quotes/Support/Layouts/LetterQuoteLayout.php`
- Create: `apps/studio/app/Quotes/Support/Layouts/HeaderBandQuoteLayout.php`
- Create: `apps/studio/app/Quotes/Support/Layouts/StructuredQuoteLayout.php`
- Modify: `apps/studio/app/Quotes/Actions/RenderQuotePdf.php` (`layoutRenderer()`'s `match`)
- Test: `apps/studio/tests/Feature/Quotes/QuoteLayoutRenderingTest.php` (extend)

**Interfaces:**
- Consumes: `QuoteLayoutRenderer` (Task 3).
- Produces: nothing new consumed elsewhere — each class is only reached through `RenderQuotePdf::layoutRenderer()`'s `match`.

- [ ] **Step 1: Write the four failing tests**

Append to `apps/studio/tests/Feature/Quotes/QuoteLayoutRenderingTest.php`:

```php
it('renders the minimal layout without a doc-table', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project)->create(['number' => '2026-014', 'valid_until' => '2026-09-26']);
    app(StudioSettingsResolver::class)->set('quote_layout', 'minimal');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-minimal')
        ->and($html)->toContain('2026-014')
        ->and($html)->not->toContain('doc-table');
});

it('renders the letter layout with a closing paragraph', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project)->create(['number' => '2026-014', 'valid_until' => '2026-09-26']);
    app(StudioSettingsResolver::class)->set('quote_layout', 'letter');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-letter')
        ->and($html)->toContain('Gültig bis');
});

it('renders the header-band layout with the band wrapper', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project)->create(['number' => '2026-014']);
    app(StudioSettingsResolver::class)->set('quote_layout', 'header_band');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-header_band')
        ->and($html)->toContain('dhb-band');
});

it('renders the structured layout with card wrappers', function (): void {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project)->create(['number' => '2026-014']);
    app(StudioSettingsResolver::class)->set('quote_layout', 'structured');

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('doc-layout-structured')
        ->and($html)->toContain('dst-card');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Quotes/QuoteLayoutRenderingTest.php`
Expected: FAIL — the four settings all still render the Classic fallback.

- [ ] **Step 3: Write `MinimalQuoteLayout`**

Reference: `QuoteLayoutSample.vue`, "Minimal" story — no meta box, no table borders, quantity inline in the title, a two-column list.

```php
<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Support\Number;

class MinimalQuoteLayout implements QuoteLayoutRenderer
{
    public function render(Quote $quote, array $mergeData, string $templateBody): string
    {
        return '<div class="doc-layout-minimal">'
            .'<div class="dlm-head">'
            .'<span>Angebot'.($quote->number === null ? '' : ' '.e($quote->number)).'</span>'
            .($quote->valid_until === null ? '' : '<span>Gültig bis '.e($quote->valid_until->format('d.m.Y')).'</span>')
            .'</div>'
            .$this->recipient($quote)
            .$templateBody
            .$this->items($quote)
            .'</div>';
    }

    private function recipient(Quote $quote): string
    {
        $name = $quote->recipientContact?->name;

        if ($name === null) {
            return '';
        }

        $cityLine = trim(($quote->recipient_postcode ?? '').' '.($quote->recipient_city ?? ''));
        $address = collect([$quote->recipient_street, $cityLine])->filter()->implode(', ');

        return '<div class="dlm-to">für <strong>'.e($name).'</strong>'.($address === '' ? '' : ' · '.e($address)).'</div>';
    }

    private function items(Quote $quote): string
    {
        $rows = $quote->items->map(function (QuoteItem $item) {
            $title = e($item->title).($item->quantity > 1 ? ' · '.$item->quantity.' × '.$this->money($item->unit_price ?? 0) : '');
            $desc = $item->description === null ? '' : '<div class="dlm-desc">'.e($item->description).'</div>';
            $amount = $item->isIncluded() ? 'inklusive' : $this->money($item->discountedLineTotal());

            return '<tr><td>'.$title.$desc.'</td><td>'.$amount.'</td></tr>';
        })->implode('');

        $rows .= '<tr class="dlm-total"><td>Zwischensumme</td><td>'.$this->money($quote->total).'</td></tr>';

        if ($quote->discount_amount > 0) {
            $rows .= '<tr><td>Rabatt</td><td>−'.$this->money($quote->discount_amount).'</td></tr>';
            $rows .= '<tr><td>Netto</td><td>'.$this->money($quote->net).'</td></tr>';
        }

        if ($quote->vat_mode === 'standard') {
            $rate = rtrim(rtrim(number_format($quote->vat_rate / 100, 2, ',', '.'), '0'), ',');
            $rows .= '<tr><td>USt. '.$rate.' %</td><td>'.$this->money($quote->vat_amount ?? 0).'</td></tr>';
            $rows .= '<tr class="dlm-grand"><td>Gesamtbetrag</td><td>'.$this->money($quote->net + ($quote->vat_amount ?? 0)).'</td></tr>';
        }

        return '<table class="dlm-table">'.$rows.'</table>';
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
```

- [ ] **Step 4: Write `LetterQuoteLayout`**

Reference: `QuoteLayoutSample.vue`, "Brief" story — Ort/Datum oben rechts, Serifen-Überschrift, Anrede, Tabelle mit Kopfzeile, Schlussabsatz.

```php
<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Support\Number;

class LetterQuoteLayout implements QuoteLayoutRenderer
{
    public function render(Quote $quote, array $mergeData, string $templateBody): string
    {
        $studio = $mergeData['studio'] ?? [];
        $studioLine = collect([$studio['name'] ?? null, $studio['street'] ?? null])->filter()->implode(' · ');

        return '<div class="doc-layout-letter">'
            .'<div class="dll-top">'
            .'<span>'.e($studioLine).'</span>'
            .'<span>'.($quote->created_at?->format('d.m.Y') ?? '').'</span>'
            .'</div>'
            .'<div class="dll-title">Angebot</div>'
            .'<div class="dll-sub">'.($quote->number === null ? '' : 'Nr. '.e($quote->number)).($quote->valid_until === null ? '' : ' · gültig bis '.e($quote->valid_until->format('d.m.Y'))).'</div>'
            .$this->recipient($quote)
            .$templateBody
            .$this->items($quote)
            .($quote->valid_until === null ? '' : '<p>Das Angebot ist bis zum '.e($quote->valid_until->format('d.m.Y')).' gültig.</p>')
            .'</div>';
    }

    private function recipient(Quote $quote): string
    {
        $name = $quote->recipientContact?->name;

        if ($name === null) {
            return '';
        }

        $lines = [e($name)];

        if ($quote->recipient_street !== null) {
            $lines[] = e($quote->recipient_street);
        }

        $cityLine = trim(($quote->recipient_postcode ?? '').' '.($quote->recipient_city ?? ''));

        if ($cityLine !== '') {
            $lines[] = e($cityLine);
        }

        return '<div class="dll-to">An<br>'.implode('<br>', $lines).'</div>';
    }

    private function items(Quote $quote): string
    {
        $rows = $quote->items->map(fn (QuoteItem $item) => '<tr><td>'
            .e($item->title).($item->description === null ? '' : '<br><small>'.e($item->description).'</small>')
            .'</td><td class="doc-num">'.$item->quantity.'</td><td class="doc-num">'
            .($item->isIncluded() ? 'inklusive' : $this->money($item->discountedLineTotal()))
            .'</td></tr>')->implode('');

        $summary = '<tr><td>Zwischensumme</td><td colspan="2" class="doc-num">'.$this->money($quote->total).'</td></tr>';

        if ($quote->discount_amount > 0) {
            $summary .= '<tr><td>Rabatt</td><td colspan="2" class="doc-num">−'.$this->money($quote->discount_amount).'</td></tr>';
            $summary .= '<tr><td>Netto</td><td colspan="2" class="doc-num">'.$this->money($quote->net).'</td></tr>';
        }

        if ($quote->vat_mode === 'standard') {
            $rate = rtrim(rtrim(number_format($quote->vat_rate / 100, 2, ',', '.'), '0'), ',');
            $summary .= '<tr><td>USt. '.$rate.' %</td><td colspan="2" class="doc-num">'.$this->money($quote->vat_amount ?? 0).'</td></tr>';
            $summary .= '<tr class="dll-total"><td>Gesamtbetrag</td><td colspan="2" class="doc-num">'.$this->money($quote->net + ($quote->vat_amount ?? 0)).'</td></tr>';
        }

        return '<table class="doc-table"><thead><tr><th>Beschreibung</th><th class="doc-num">Menge</th><th class="doc-num">Betrag</th></tr></thead>'
            .'<tbody>'.$rows.$summary.'</tbody></table>';
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
```

- [ ] **Step 5: Write `HeaderBandQuoteLayout`**

Reference: `QuoteLayoutSample.vue`, "Kopfband" story — Farbfläche oben, Meta-Zeile, Tabellenkopf akzentfarben, Summenblock als Karte.

```php
<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Support\Number;

class HeaderBandQuoteLayout implements QuoteLayoutRenderer
{
    public function render(Quote $quote, array $mergeData, string $templateBody): string
    {
        $studio = $mergeData['studio'] ?? [];

        return '<div class="doc-layout-header_band">'
            .'<div class="dhb-band"><span>'.e($studio['name'] ?? '').'</span><span class="dhb-band-title">Angebot'.($quote->number === null ? '' : '<br><small>Nr. '.e($quote->number).'</small>').'</span></div>'
            .'<div class="dhb-body">'
            .'<div class="dhb-meta-row">'
            .'<span>Datum <strong>'.($quote->created_at?->format('d.m.Y') ?? '').'</strong></span>'
            .($quote->valid_until === null ? '' : '<span>Gültig bis <strong>'.e($quote->valid_until->format('d.m.Y')).'</strong></span>')
            .'</div>'
            .$this->recipient($quote)
            .$templateBody
            .$this->items($quote)
            .'</div></div>';
    }

    private function recipient(Quote $quote): string
    {
        $name = $quote->recipientContact?->name;

        if ($name === null) {
            return '';
        }

        $lines = [e($name)];

        if ($quote->recipient_street !== null) {
            $lines[] = e($quote->recipient_street);
        }

        $cityLine = trim(($quote->recipient_postcode ?? '').' '.($quote->recipient_city ?? ''));

        if ($cityLine !== '') {
            $lines[] = e($cityLine);
        }

        return '<div class="dhb-to">'.implode('<br>', $lines).'</div>';
    }

    private function items(Quote $quote): string
    {
        $rows = $quote->items->map(fn (QuoteItem $item) => '<tr><td>'
            .e($item->title).($item->description === null ? '' : '<br><small>'.e($item->description).'</small>')
            .'</td><td class="doc-num">'.$item->quantity.'</td><td class="doc-num">'
            .($item->isIncluded() ? 'inklusive' : $this->money($item->discountedLineTotal()))
            .'</td></tr>')->implode('');

        $summary = '<tr><td>Zwischensumme</td><td class="doc-num">'.$this->money($quote->total).'</td></tr>';

        if ($quote->discount_amount > 0) {
            $summary .= '<tr><td>Rabatt</td><td class="doc-num">−'.$this->money($quote->discount_amount).'</td></tr>';
            $summary .= '<tr><td>Netto</td><td class="doc-num">'.$this->money($quote->net).'</td></tr>';
        }

        if ($quote->vat_mode === 'standard') {
            $rate = rtrim(rtrim(number_format($quote->vat_rate / 100, 2, ',', '.'), '0'), ',');
            $summary .= '<tr><td>USt. '.$rate.' %</td><td class="doc-num">'.$this->money($quote->vat_amount ?? 0).'</td></tr>';
            $summary .= '<tr class="dhb-total"><td>Gesamtbetrag</td><td class="doc-num">'.$this->money($quote->net + ($quote->vat_amount ?? 0)).'</td></tr>';
        }

        return '<table class="doc-table"><thead><tr><th>Beschreibung</th><th class="doc-num">Menge</th><th class="doc-num">Betrag</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<table class="dhb-summary">'.$summary.'</table>';
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
```

- [ ] **Step 6: Write `StructuredQuoteLayout`**

Reference: `QuoteLayoutSample.vue`, "Struktur" story — Empfänger- und Meta-Karte nebeneinander, Tabelle mit alternierenden Zeilen in einer Karte, Summenblock und Fußzeile als Karten.

```php
<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Support\Number;

class StructuredQuoteLayout implements QuoteLayoutRenderer
{
    public function render(Quote $quote, array $mergeData, string $templateBody): string
    {
        return '<div class="doc-layout-structured">'
            .'<div class="dst-head"><span>Angebot'.($quote->number === null ? '' : ' Nr. '.e($quote->number)).'</span></div>'
            .'<div class="dst-cards">'.$this->recipientCard($quote).$this->metaCard($quote).'</div>'
            .$templateBody
            .'<div class="dst-card dst-items">'.$this->items($quote).'</div>'
            .'<div class="dst-card dst-summary">'.$this->summary($quote).'</div>'
            .'</div>';
    }

    private function recipientCard(Quote $quote): string
    {
        $name = $quote->recipientContact?->name;

        if ($name === null) {
            return '<div class="dst-card"></div>';
        }

        $cityLine = trim(($quote->recipient_postcode ?? '').' '.($quote->recipient_city ?? ''));
        $lines = collect([$quote->recipient_street, $cityLine])->filter()->map(fn (string $l) => e($l))->implode('<br>');

        return '<div class="dst-card"><div class="dst-label">Empfänger</div><div><strong>'.e($name).'</strong><br>'.$lines.'</div></div>';
    }

    private function metaCard(Quote $quote): string
    {
        $rows = ['<span>Datum</span><span>'.($quote->created_at?->format('d.m.Y') ?? '').'</span>'];

        if ($quote->valid_until !== null) {
            $rows[] = '<span>Gültig bis</span><span>'.e($quote->valid_until->format('d.m.Y')).'</span>';
        }

        return '<div class="dst-card"><div class="dst-label">Details</div><div class="dst-meta-grid">'.implode('', $rows).'</div></div>';
    }

    private function items(Quote $quote): string
    {
        $rows = $quote->items->map(fn (QuoteItem $item) => '<tr><td>'
            .e($item->title).($item->description === null ? '' : '<br><small>'.e($item->description).'</small>')
            .'</td><td class="doc-num">'.$item->quantity.'</td><td class="doc-num">'
            .($item->isIncluded() ? 'inklusive' : $this->money($item->discountedLineTotal()))
            .'</td></tr>')->implode('');

        return '<table class="doc-table"><thead><tr><th>Beschreibung</th><th class="doc-num">Menge</th><th class="doc-num">Betrag</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private function summary(Quote $quote): string
    {
        $rows = '<tr><td>Zwischensumme</td><td class="doc-num">'.$this->money($quote->total).'</td></tr>';

        if ($quote->discount_amount > 0) {
            $rows .= '<tr><td>Rabatt</td><td class="doc-num">−'.$this->money($quote->discount_amount).'</td></tr>';
            $rows .= '<tr><td>Netto</td><td class="doc-num">'.$this->money($quote->net).'</td></tr>';
        }

        if ($quote->vat_mode === 'standard') {
            $rate = rtrim(rtrim(number_format($quote->vat_rate / 100, 2, ',', '.'), '0'), ',');
            $rows .= '<tr><td>USt. '.$rate.' %</td><td class="doc-num">'.$this->money($quote->vat_amount ?? 0).'</td></tr>';
            $rows .= '<tr class="dst-total"><td>Gesamtbetrag</td><td class="doc-num">'.$this->money($quote->net + ($quote->vat_amount ?? 0)).'</td></tr>';
        }

        return '<table>'.$rows.'</table>';
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
```

- [ ] **Step 7: Wire the four classes into `layoutRenderer()`'s `match`**

In `apps/studio/app/Quotes/Actions/RenderQuotePdf.php`, add the four imports and replace the `match`:

```php
use App\Quotes\Support\Layouts\HeaderBandQuoteLayout;
use App\Quotes\Support\Layouts\LetterQuoteLayout;
use App\Quotes\Support\Layouts\MinimalQuoteLayout;
use App\Quotes\Support\Layouts\StructuredQuoteLayout;
```

```php
        return match ($layout) {
            QuoteLayout::Classic => new ClassicQuoteLayout,
            QuoteLayout::Minimal => new MinimalQuoteLayout,
            QuoteLayout::Letter => new LetterQuoteLayout,
            QuoteLayout::HeaderBand => new HeaderBandQuoteLayout,
            QuoteLayout::Structured => new StructuredQuoteLayout,
        };
```

(the `default =>` arm from Task 3 is removed now that the match is exhaustive over all five enum cases)

- [ ] **Step 8: Run tests to verify they pass**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Quotes/QuoteLayoutRenderingTest.php`
Expected: PASS (all 5 tests: classic + the 4 above)

- [ ] **Step 9: Commit**

```bash
git add apps/studio/app/Quotes/Support/Layouts/MinimalQuoteLayout.php \
  apps/studio/app/Quotes/Support/Layouts/LetterQuoteLayout.php \
  apps/studio/app/Quotes/Support/Layouts/HeaderBandQuoteLayout.php \
  apps/studio/app/Quotes/Support/Layouts/StructuredQuoteLayout.php \
  apps/studio/app/Quotes/Actions/RenderQuotePdf.php \
  apps/studio/tests/Feature/Quotes/QuoteLayoutRenderingTest.php
git commit -m "feat(quotes): render minimal, letter, header-band and structured layouts"
```

---

## Task 5: CSS for the five `.doc-layout-*` wrappers

**Files:**
- Modify: `apps/studio/app/Documents/Support/DocumentRenderer.php` (`STYLE` constant)
- Test: `apps/studio/tests/Feature/Documents/DocumentRendererTest.php` (extend if present, else create)

**Interfaces:**
- Consumes: nothing new — pure CSS addition to the existing shared `<style>` block every document (and the settings-page preview) already gets.

- [ ] **Step 1: Write the failing test**

Check whether `apps/studio/tests/Feature/Documents/DocumentRendererTest.php` exists (`grep -rl DocumentRendererTest apps/studio/tests`); if it does, add a test to it, otherwise create it:

```php
<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentRenderer;
use App\Settings\Support\StudioSettingsResolver;

it('ships css for all five quote layout wrappers', function () {
    $html = app(DocumentRenderer::class)->wrap(DocumentTemplateType::Quote, '<p>x</p>', app(StudioSettingsResolver::class));

    foreach (['classic', 'minimal', 'letter', 'header_band', 'structured'] as $layout) {
        expect($html)->toContain('.doc-layout-'.$layout);
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Documents/DocumentRendererTest.php`
Expected: FAIL — none of the five selectors exist in `STYLE` yet.

- [ ] **Step 3: Add the five CSS blocks to `DocumentRenderer::STYLE`**

In `apps/studio/app/Documents/Support/DocumentRenderer.php`, inside the `STYLE` heredoc, right before the closing `</style>` (after the existing `.doc-pagebreak` rule), add:

```css
          /* Angebot-Drucklayouts — je ein Block pro App\Quotes\Enums\
             QuoteLayout-Fall, Referenzmarkup in QuoteLayoutSample.vue. */
          .doc-layout-classic .dlc-head { display: flex; justify-content: space-between; align-items: flex-start; }
          .doc-layout-classic .dlc-from { font-size: 8.5pt; color: #6B6B6B; margin-bottom: 6pt; }
          .doc-layout-classic .dlc-recipient { font-weight: 600; }
          .doc-layout-classic .dlc-meta { border-collapse: collapse; }
          .doc-layout-classic .dlc-meta td { padding: 2pt 0; font-size: 9pt; }
          .doc-layout-classic .dlc-meta td:first-child { color: #6B6B6B; padding-right: 16pt; }
          .doc-layout-classic .dlc-meta td:last-child { text-align: right; }
          .doc-layout-classic .dlc-title { font-size: 12pt; font-weight: 700; padding-bottom: 8pt; }
          .doc-layout-classic .dlc-summary { width: 180pt; margin-left: auto; margin-top: 6pt; }
          .doc-layout-classic .dlc-summary td:last-child { text-align: right; }
          .doc-layout-classic .dlc-total td { border-top: 1pt solid #191919; padding-top: 6pt; font-weight: 700; }

          .doc-layout-minimal .dlm-head { display: flex; justify-content: space-between; font-size: 9pt; color: #6B6B6B; margin-bottom: 10pt; }
          .doc-layout-minimal .dlm-to { font-size: 9.5pt; color: #6B6B6B; margin-bottom: 12pt; }
          .doc-layout-minimal .dlm-table { width: 100%; border-collapse: collapse; }
          .doc-layout-minimal .dlm-table td { padding: 8pt 0; vertical-align: top; }
          .doc-layout-minimal .dlm-table td:last-child { text-align: right; white-space: nowrap; padding-left: 12pt; }
          .doc-layout-minimal .dlm-desc { font-size: 8.5pt; color: #6B6B6B; }
          .doc-layout-minimal .dlm-total td { border-top: 1pt solid #191919; font-weight: 600; }
          .doc-layout-minimal .dlm-grand { font-weight: 700; }

          .doc-layout-letter .dll-top { display: flex; justify-content: space-between; font-size: 8pt; color: #6B6B6B; }
          .doc-layout-letter .dll-title { font-family: 'Hanken Grotesk', serif; font-size: 22pt; font-weight: 500; margin: 12pt 0 3pt; }
          .doc-layout-letter .dll-sub { font-size: 8pt; color: #6B6B6B; margin-bottom: 14pt; }
          .doc-layout-letter .dll-to { font-size: 9pt; color: #6B6B6B; margin-bottom: 10pt; }
          .doc-layout-letter .dll-total td { border-top: 1pt solid #191919; font-weight: 700; }

          .doc-layout-header_band .dhb-band { background: #1C6870; color: #fff; padding: 14pt 0; display: flex; justify-content: space-between; align-items: flex-end; margin: -0pt 0 12pt; }
          .doc-layout-header_band .dhb-band-title { font-size: 14pt; font-weight: 700; text-align: right; }
          .doc-layout-header_band .dhb-meta-row { display: flex; gap: 14pt; font-size: 8pt; color: #6B6B6B; padding-bottom: 8pt; border-bottom: 0.75pt solid #E9E9E7; margin-bottom: 10pt; }
          .doc-layout-header_band .dhb-to { margin-bottom: 12pt; }
          .doc-layout-header_band .dhb-summary { width: 200pt; margin-left: auto; margin-top: 8pt; border: 0.75pt solid #E9E9E7; padding: 8pt 10pt; }
          .doc-layout-header_band .dhb-summary td:last-child { text-align: right; }
          .doc-layout-header_band .dhb-total td { border-top: 1pt solid #1C6870; font-weight: 700; color: #1C6870; }

          .doc-layout-structured .dst-head { text-align: right; font-size: 12pt; font-weight: 700; margin-bottom: 10pt; }
          .doc-layout-structured .dst-cards { display: flex; gap: 10pt; margin-bottom: 10pt; }
          .doc-layout-structured .dst-card { flex: 1; border: 0.75pt solid #DCDAD6; padding: 8pt 10pt; margin-bottom: 8pt; }
          .doc-layout-structured .dst-label { font-size: 7pt; font-weight: 600; text-transform: uppercase; color: #6B6B6B; margin-bottom: 4pt; }
          .doc-layout-structured .dst-meta-grid { display: grid; grid-template-columns: auto 1fr; gap: 2pt 10pt; font-size: 9pt; }
          .doc-layout-structured .dst-items { padding: 0; }
          .doc-layout-structured .dst-summary td:last-child { text-align: right; }
          .doc-layout-structured .dst-total td { border-top: 1pt solid #191919; font-weight: 700; }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Documents/DocumentRendererTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Documents/Support/DocumentRenderer.php \
  apps/studio/tests/Feature/Documents/DocumentRendererTest.php
git commit -m "feat(quotes): style the five doc-layout wrappers"
```

---

## Task 6: `SampleQuoteData` and the settings-page preview endpoint

**Files:**
- Create: `apps/studio/app/Quotes/Support/SampleQuoteData.php`
- Modify: `apps/studio/app/Settings/Http/Controllers/StudioSettingsController.php` (new `previewQuoteLayout()`)
- Modify: `apps/studio/routes/admin.php`
- Test: `apps/studio/tests/Feature/Settings/QuoteLayoutPreviewTest.php`

**Interfaces:**
- Produces: `App\Quotes\Support\SampleQuoteData::build(): Quote` (unsaved, never persisted), `GET /einstellungen/dokumente/angebot-vorschau?layout=classic`.

- [ ] **Step 1: Write `SampleQuoteData`**

```php
<?php

namespace App\Quotes\Support;

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Settings\Support\StudioSettingsResolver;

/**
 * A quote that has never touched the database — exactly what Einstellungen
 * → Belege & Fristen needs to preview a chosen App\Quotes\Enums\QuoteLayout
 * without a real project, contact or quote existing yet. Same shape as
 * QuoteController::preview()'s in-memory draft, minus a real project: every
 * relation is set with setRelation() rather than saved.
 */
class SampleQuoteData
{
    public static function build(StudioSettingsResolver $settings): Quote
    {
        $project = new Project(['name' => 'Erstkommunion 2026']);
        $contact = new Contact(['name' => 'Pfarrgemeinde St. Johann', 'street' => 'Kirchplatz 3', 'postcode' => '07743', 'city' => 'Jena']);

        $items = collect([
            new QuoteItem([
                'title' => 'Reportage Erstkommunion 2026',
                'description' => 'Begleitung von 2 Erstkommunionterminen, Bereitstellung in 2 Online-Galerien',
                'quantity' => 2,
                'unit_price' => 25000,
            ]),
            new QuoteItem([
                'title' => 'Familienfoto-Ergänzung',
                'description' => 'Zusätzliches Gruppenfoto im Anschluss an den Termin',
                'quantity' => 1,
                'unit_price' => null,
            ]),
        ])->each(fn (QuoteItem $item) => $item->discount_amount = $item->resolveDiscountAmount());

        $quote = new Quote([
            'number' => '2026-014',
            'valid_until' => '2026-09-26',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 1000,
        ]);
        $quote->setRelation('project', $project);
        $quote->setRelation('recipientContact', $contact);
        $quote->setRelation('items', $items);
        $quote->setRelation('paymentStages', collect());
        $quote->fill(Quote::computeTotals($items, $quote->discount_type, $quote->discount_value));
        $quote->fill(Quote::computeVat($quote->net, $settings));

        return $quote;
    }
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

it('previews a chosen layout without persisting anything', function () {
    [$user, $project] = studioWithProject();

    $response = $this->actingAs($user, 'staff')->get('/einstellungen/dokumente/angebot-vorschau?layout=header_band');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('doc-layout-header_band')
        ->toContain('Pfarrgemeinde St. Johann');

    expect(\App\Quotes\Models\Quote::query()->count())->toBe(0);
});

it('falls back to the saved layout when none is requested', function () {
    [$user, $project] = studioWithProject();
    app(\App\Settings\Support\StudioSettingsResolver::class)->set('quote_layout', 'minimal');

    $response = $this->actingAs($user, 'staff')->get('/einstellungen/dokumente/angebot-vorschau');

    $response->assertOk();
    expect($response->getContent())->toContain('doc-layout-minimal');
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Settings/QuoteLayoutPreviewTest.php`
Expected: FAIL — 404, route doesn't exist.

- [ ] **Step 4: Add `previewQuoteLayout()` to `StudioSettingsController`**

`RenderQuotePdf::html()` currently always reads the layout from `StudioSettingsResolver::quoteLayout()` — the preview needs to override it. Add an optional `?QuoteLayout $layoutOverride` parameter to `RenderQuotePdf::html()` and `layoutRenderer()`:

```php
    public function html(Quote $quote, ?QuoteLayout $layoutOverride = null): string
    {
        // ...unchanged...
        $body = $quote->hasSlider()
            ? $this->recipient($quote).$templateBody.$this->sliderItems($quote)
            : $this->layoutRenderer($layoutOverride)->render($quote, $data, $templateBody);
        // ...unchanged...
    }

    private function layoutRenderer(?QuoteLayout $override = null): QuoteLayoutRenderer
    {
        $layout = $override ?? QuoteLayout::tryFrom($this->settings->quoteLayout()) ?? QuoteLayout::Classic;
        // ...unchanged match...
    }
```

In `apps/studio/app/Settings/Http/Controllers/StudioSettingsController.php`, add:

```php
    /**
     * T-05 follow-up: the Layout radio in the Dokumente pane needs to show
     * what each of the five App\Quotes\Enums\QuoteLayout cases actually
     * renders like — with real markup, not the skeleton bars every other
     * pane's DocumentPreview.vue shows. App\Quotes\Support\SampleQuoteData
     * never touches the database, so this route needs no project or quote.
     */
    public function previewQuoteLayout(Request $request, RenderQuotePdf $render, StudioSettingsResolver $settings): HttpResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'layout' => ['nullable', Rule::enum(QuoteLayout::class)],
        ]);

        $layout = isset($validated['layout']) ? QuoteLayout::from($validated['layout']) : null;
        $sample = SampleQuoteData::build($settings);

        return response($render->html($sample, $layout))->header('Content-Type', 'text/html; charset=utf-8');
    }
```

Add the needed imports (`App\Quotes\Actions\RenderQuotePdf`, `App\Quotes\Enums\QuoteLayout`, `App\Quotes\Support\SampleQuoteData`, `Illuminate\Http\Response as HttpResponse` — check which of these the file already imports before adding).

- [ ] **Step 5: Add the route**

In `apps/studio/routes/admin.php`, directly after the `settings.documents.update` line:

```php
        Route::get('/einstellungen/dokumente/angebot-vorschau', [StudioSettingsController::class, 'previewQuoteLayout'])
            ->name('settings.documents.quote-layout-preview');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Settings/QuoteLayoutPreviewTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add apps/studio/app/Quotes/Support/SampleQuoteData.php \
  apps/studio/app/Quotes/Actions/RenderQuotePdf.php \
  apps/studio/app/Settings/Http/Controllers/StudioSettingsController.php \
  apps/studio/routes/admin.php \
  apps/studio/tests/Feature/Settings/QuoteLayoutPreviewTest.php
git commit -m "feat(quotes): add the layout preview endpoint with sample data"
```

---

## Task 7: `DocumentsForm.vue` layout picker + real HTML preview

**Files:**
- Modify: `apps/studio/resources/js/admin/pages/Settings/panes/DocumentsForm.vue`
- Modify: `apps/studio/resources/js/admin/pages/Settings/previews/DocumentPreview.vue`

No new automated test here — Vue/Inertia form wiring, covered by the existing manual QA step below and the backend tests from Tasks 1–6 that already exercise every code path this UI calls.

**Interfaces:**
- Consumes: `RadioGroup`/`RadioGroupItem` (`@/ui/radio-group`, already used in `VatForm.vue`), `settings.quoteLayout: string` (Task 2's `StudioSettingsData`), `GET /einstellungen/dokumente/angebot-vorschau?layout=...` (Task 6).

- [ ] **Step 1: Add the Layout section to `DocumentsForm.vue`**

In `apps/studio/resources/js/admin/pages/Settings/panes/DocumentsForm.vue`, add `quote_layout` to the form:

```ts
const form = useForm({
  quote_validity_days: props.settings.quoteValidityDays,
  payment_term_days: props.settings.paymentTermDays,
  deposit_percentage: basisPointsToPercent(props.settings.depositPercentage),
  widerruf_included: props.settings.widerrufIncluded,
  widerruf_text: props.settings.widerrufText ?? '',
  quote_layout: props.settings.quoteLayout,
})
```

Add `RadioGroup`/`RadioGroupItem` to the imports:

```ts
import { RadioGroup, RadioGroupItem } from '@/ui/radio-group'
```

Add a new section as the template's first `<div class="section">` block (before "Angebot"):

```html
    <div class="mt-0">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">Layout</div>
      <div class="mb-6">
        <RadioGroup
          name="quotelayout"
          :model-value="form.quote_layout"
          @update:model-value="(value) => (form.quote_layout = String(value))"
        >
          <div class="flex items-center gap-2.5">
            <RadioGroupItem id="ql-classic" value="classic" />
            <Label variant="inline" for="ql-classic">Klassisch</Label>
          </div>
          <div class="flex items-center gap-2.5">
            <RadioGroupItem id="ql-minimal" value="minimal" />
            <Label variant="inline" for="ql-minimal">Minimal</Label>
          </div>
          <div class="flex items-center gap-2.5">
            <RadioGroupItem id="ql-letter" value="letter" />
            <Label variant="inline" for="ql-letter">Brief</Label>
          </div>
          <div class="flex items-center gap-2.5">
            <RadioGroupItem id="ql-header_band" value="header_band" />
            <Label variant="inline" for="ql-header_band">Kopfband</Label>
          </div>
          <div class="flex items-center gap-2.5">
            <RadioGroupItem id="ql-structured" value="structured" />
            <Label variant="inline" for="ql-structured">Struktur</Label>
          </div>
        </RadioGroup>
      </div>
    </div>
```

Change the very next section's wrapper from `<div class="mt-0">` (the current first "Angebot" section) to `<div class="mt-8 first-of-type:mt-6">`, matching the pattern every later section already uses (only the true first section keeps `mt-0`, now the Layout section above it).

Note: `quote_layout` is not in `updateDocuments()`'s submit — it already is, since `form` submits every key it holds via `patch('/einstellungen/dokumente', ...)`.

- [ ] **Step 2: Swap the skeleton preview for the real iframe**

In `apps/studio/resources/js/admin/pages/Settings/previews/DocumentPreview.vue`, replace the whole skeleton `<div class="rounded-md bg-background ...">...</div>` block with:

```html
  <PreviewFrame :label="$t('settings.previewDocument')">
    <iframe
      :src="previewUrl"
      class="h-[520px] w-full rounded-md border-0 bg-background"
      sandbox=""
      title="Angebot-Vorschau"
    />
  </PreviewFrame>
```

Add to the `<script setup>`:

```ts
import { computed } from 'vue'

const previewUrl = computed(() =>
  `/einstellungen/dokumente/angebot-vorschau?layout=${encodeURIComponent(props.doc.quoteLayout ?? '')}`)
```

Add `quoteLayout: string` to the `doc` prop's type (in the existing `defineProps<{ doc: {...} }>()` block, alongside `widerrufIncluded: boolean`).

- [ ] **Step 3: Pass `quoteLayout` from `Index.vue`'s `documentPreview` computed**

In `apps/studio/resources/js/admin/pages/Settings/Index.vue`, in the `documentPreview` computed property, add after `widerrufIncluded: ...`:

```ts
    quoteLayout: documents?.quote_layout ?? props.settings.quoteLayout,
```

- [ ] **Step 4: Manual verification**

Run: `docker compose up -d` (from repo root, per project convention), open `/einstellungen?pane=documents`, confirm:
- The Layout radio shows five options, Klassisch pre-selected on a fresh studio.
- Clicking each radio updates the preview iframe to visibly different markup within ~1s (no debounce needed, per spec — a radio click, not typing).
- Saving persists the choice (reload the page, the same radio stays selected).
- Switching to `/einstellungen?pane=vat` still shows the same iframe preview reflecting the last-saved layout.

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/pages/Settings/panes/DocumentsForm.vue \
  apps/studio/resources/js/admin/pages/Settings/previews/DocumentPreview.vue \
  apps/studio/resources/js/admin/pages/Settings/Index.vue
git commit -m "feat(quotes): add the layout picker and live preview to Belege & Fristen"
```
