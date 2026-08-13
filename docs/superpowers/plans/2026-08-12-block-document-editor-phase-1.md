# Block Document Editor — Phase 1 (Editor Core) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the raw-HTML `<textarea>` on `/dokumentvorlagen` with an Editor.js block editor — floating inserter, per-document-type block palette, merge-field chips — beside a live preview on the right half of the screen.

**Architecture:** Editor.js JSON (`document_templates.blocks`) becomes the edit source of truth. A pure TypeScript serializer turns those blocks into HTML and the form posts both, so `document_templates.body` keeps its exact meaning and every downstream consumer — `MergeFieldResolver`, `DocumentRenderer`, `RenderContractJob`, Gotenberg — is untouched. One PHP catalogue (`DocumentBlockCatalog`) decides which blocks a document type may use, and is read by both the palette prop and save validation so the two cannot drift.

**Tech Stack:** Laravel 12 + Inertia 2 + Vue 3 (`<script setup lang="ts">`), Editor.js 2.x, Tailwind 4 alongside the clickdummy token CSS, Pest (PHP), vitest (TS), Playwright (e2e).

**Spec:** [`docs/superpowers/specs/2026-08-12-block-document-editor-design.md`](../specs/2026-08-12-block-document-editor-design.md)
**Mockup:** [`docs/superpowers/specs/2026-08-12-block-document-editor-mockup.html`](../specs/2026-08-12-block-document-editor-mockup.html) — open it in a browser before starting; it is the visual target.

## Global Constraints

- **Commands run through Docker.** `pnpm php` is `docker compose run --rm php`. Tests: `pnpm php ./vendor/bin/pest`, `pnpm --filter @studio/app test` (vitest), `pnpm --filter @studio/app exec playwright test`, `pnpm lint` (vue-tsc).
- **Never mutate a template row.** Editing goes through `DocumentTemplate::newVersion()`, which inserts a new row and moves `is_current`. Any change to save logic must preserve this.
- **`type` and `shoot_kind` are never edit input.** They identify a family; `newVersion()` carries them forward.
- **German UI copy is terse and native.** No explanatory hint paragraphs. Match the existing `documentTemplates.*` keys in `apps/studio/resources/js/locales/de.json` — short noun phrases, no marketing tone. All user-visible strings go through `$t()`.
- **Phase 1 blocks only:** paragraph, header, list, quote, delimiter, table, columns, callout, spacer, image, plus the inline merge-field tool and the legacy `rawHtml` block. Page break, signature and Widerruf are Phase 2 — do not add palette entries for unimplemented blocks.
- **Image block takes a URL only in Phase 1.** No upload endpoint — that is out of scope and must not be invented.
- **New dependencies are limited to** `@editorjs/editorjs`, `@editorjs/header`, `@editorjs/list`, `@editorjs/quote`, `@editorjs/delimiter`, `@editorjs/table`. Nothing else. `@vueuse/core` (already installed) provides `useDebounceFn`.
- **Preview iframe keeps `sandbox=""`** with no `allow-*` tokens.
- **CSS** goes in a new `apps/studio/resources/css/document-editor.css`, imported from `admin.css`, using the tokens in `resources/css/tokens.css` (`--s1`…`--s7`, `--ink`, `--muted`, `--faint`, `--line`, `--accent`, `--accent-bg`, `--r`, `--font`). No new colour values.
- All paths below are relative to `apps/studio/` unless they start with `docs/`.

## File Structure

**Create:**
- `app/Documents/Support/DocumentBlockCatalog.php` — which blocks each document type may use; the single source for palette and validation.
- `database/migrations/2026_08_12_090000_add_blocks_to_document_templates_table.php` — the `blocks` JSONB column.
- `resources/js/admin/documents/blocks/types.ts` — shared TS types for an Editor.js document.
- `resources/js/admin/documents/blocks/serialize.ts` — blocks → HTML, both profiles.
- `resources/js/admin/documents/blocks/serialize.test.ts`
- `resources/js/admin/documents/blocks/importHtml.ts` — legacy `body` → one `rawHtml` block; sample substitution for the instant preview.
- `resources/js/admin/documents/blocks/importHtml.test.ts`
- `resources/js/admin/documents/blocks/tools.ts` — palette → Editor.js `tools` config.
- `resources/js/admin/documents/blocks/tools.test.ts`
- `resources/js/admin/documents/blocks/mergeFieldTool.ts` — the inline merge-field chip.
- `resources/js/admin/documents/blocks/customTools.ts` — callout, spacer, columns, image; Editor.js ships none of them.
- `resources/js/admin/components/DocumentEditor.vue` — the Editor.js shell.
- `resources/js/admin/components/DocumentPreviewPane.vue` — the right half.
- `resources/css/document-editor.css`
- `tests/Unit/Documents/DocumentBlockCatalogTest.php`
- `tests/e2e/document-editor.spec.ts`

**Modify:**
- `app/Documents/Models/DocumentTemplate.php` — `blocks` in `$fillable` and `casts()`; `newVersion()` carries it.
- `app/Documents/Data/DocumentTemplateData.php` — expose `blocks`.
- `app/Documents/Support/MergeFieldVocabulary.php` — add `samples()`.
- `app/Documents/Http/Controllers/DocumentTemplateController.php` — accept/validate `blocks`, pass palette + samples as props.
- `resources/js/admin/pages/DocumentTemplates/Create.vue`, `Edit.vue` — swap the textarea for the editor + preview split.
- `resources/js/locales/de.json` — `documentTemplates.*` additions.
- `resources/css/admin.css` — import the new stylesheet.
- `tests/Feature/Documents/DocumentTemplateAdminTest.php` — blocks round-trip and validation.
- `apps/studio/package.json` — the six Editor.js packages.

**Deleted at the end of Task 8:** `resources/js/admin/components/DocumentTemplatePreview.vue` — replaced by `DocumentPreviewPane.vue`. Check with `grep -rn "DocumentTemplatePreview" resources/js` before removing.

---

### Task 1: The block catalogue

The one place that answers "may a `columns` block appear in a mail?". Read later by both the palette prop and save validation.

**Files:**
- Create: `app/Documents/Support/DocumentBlockCatalog.php`
- Test: `tests/Unit/Documents/DocumentBlockCatalogTest.php`

**Interfaces:**
- Consumes: `App\Documents\Enums\DocumentTemplateType` (existing: `Contract`, `Quote`, `Mail`).
- Produces:
  - `DocumentBlockCatalog::for(DocumentTemplateType $type): array` — list of `['type' => string, 'group' => string, 'label' => string, 'inline' => bool]`, in palette order.
  - `DocumentBlockCatalog::allows(DocumentTemplateType $type, string $blockType): bool` — true for block types this document type may persist. Always false for inline tools and always **true** for `rawHtml` (legacy bodies must remain saveable for every type).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Documents/DocumentBlockCatalogTest.php`:

```php
<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentBlockCatalog;

it('offers layout blocks to a contract but never a page-breaking column layout to a mail', function () {
    $contract = collect(DocumentBlockCatalog::for(DocumentTemplateType::Contract))->pluck('type');
    $mail = collect(DocumentBlockCatalog::for(DocumentTemplateType::Mail))->pluck('type');

    expect($contract)->toContain('columns', 'spacer', 'table')
        ->and($mail)->toContain('paragraph', 'table')
        ->and($mail)->not->toContain('columns')
        ->and($mail)->not->toContain('spacer');
});

it('offers the merge-field tool to every type, flagged inline', function () {
    foreach (DocumentTemplateType::cases() as $type) {
        $field = collect(DocumentBlockCatalog::for($type))->firstWhere('type', 'mergeField');

        expect($field)->not->toBeNull()
            ->and($field['inline'])->toBeTrue()
            ->and($field['group'])->toBe('data');
    }
});

it('never lets an inline tool through as a block', function () {
    expect(DocumentBlockCatalog::allows(DocumentTemplateType::Contract, 'mergeField'))->toBeFalse();
});

it('allows a legacy raw-html block for every type so imported bodies stay saveable', function () {
    foreach (DocumentTemplateType::cases() as $type) {
        expect(DocumentBlockCatalog::allows($type, 'rawHtml'))->toBeTrue();
    }
});

it('rejects a block type nobody implements', function () {
    expect(DocumentBlockCatalog::allows(DocumentTemplateType::Contract, 'signature'))->toBeFalse()
        ->and(DocumentBlockCatalog::allows(DocumentTemplateType::Quote, 'nonsense'))->toBeFalse();
});

it('labels every entry, so the palette never renders a bare key', function () {
    foreach (DocumentTemplateType::cases() as $type) {
        foreach (DocumentBlockCatalog::for($type) as $entry) {
            expect($entry['label'])->not->toBeEmpty();
        }
    }
});
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `pnpm php ./vendor/bin/pest tests/Unit/Documents/DocumentBlockCatalogTest.php`
Expected: FAIL — `Class "App\Documents\Support\DocumentBlockCatalog" not found`.

- [ ] **Step 3: Write the catalogue**

Create `app/Documents/Support/DocumentBlockCatalog.php`:

```php
<?php

namespace App\Documents\Support;

use App\Documents\Enums\DocumentTemplateType;

/**
 * Which blocks each document type may use — the single source the inserter
 * palette and save validation both read, so what a user is offered and what
 * the server accepts can never drift apart. Same role MergeFieldVocabulary
 * plays for merge paths.
 *
 * A page break has no meaning in an email and a two-column layout survives
 * email clients badly, so the allow-list is per type rather than one global
 * set filtered in the UI.
 *
 * Phase 1 lists only implemented blocks. Adding a block here without a
 * serializer case in resources/js/admin/documents/blocks/serialize.ts would
 * put an entry in the palette that renders to nothing.
 */
class DocumentBlockCatalog
{
    /**
     * type => [group, label, allowed document types, inline?]
     *
     * @var array<string, array{group: string, label: string, types: list<string>, inline?: bool}>
     */
    private const BLOCKS = [
        'paragraph' => ['group' => 'text', 'label' => 'Absatz', 'types' => ['contract', 'quote', 'mail']],
        'header' => ['group' => 'text', 'label' => 'Überschrift', 'types' => ['contract', 'quote', 'mail']],
        'list' => ['group' => 'text', 'label' => 'Liste', 'types' => ['contract', 'quote', 'mail']],
        'quote' => ['group' => 'text', 'label' => 'Zitat', 'types' => ['contract', 'quote', 'mail']],
        'delimiter' => ['group' => 'text', 'label' => 'Trenner', 'types' => ['contract', 'quote', 'mail']],
        'table' => ['group' => 'layout', 'label' => 'Tabelle', 'types' => ['contract', 'quote', 'mail']],
        'columns' => ['group' => 'layout', 'label' => '2 Spalten', 'types' => ['contract', 'quote']],
        'image' => ['group' => 'layout', 'label' => 'Bild', 'types' => ['contract', 'quote', 'mail']],
        'callout' => ['group' => 'layout', 'label' => 'Hinweis', 'types' => ['contract', 'quote', 'mail']],
        'spacer' => ['group' => 'layout', 'label' => 'Abstand', 'types' => ['contract', 'quote']],
        'mergeField' => ['group' => 'data', 'label' => 'Feld', 'types' => ['contract', 'quote', 'mail'], 'inline' => true],
    ];

    /**
     * The palette for one document type, in the order the inserter shows it.
     *
     * @return list<array{type: string, group: string, label: string, inline: bool}>
     */
    public static function for(DocumentTemplateType $type): array
    {
        $palette = [];

        foreach (self::BLOCKS as $blockType => $definition) {
            if (! in_array($type->value, $definition['types'], true)) {
                continue;
            }

            $palette[] = [
                'type' => $blockType,
                'group' => $definition['group'],
                'label' => $definition['label'],
                'inline' => $definition['inline'] ?? false,
            ];
        }

        return $palette;
    }

    /**
     * May this document type persist a block of this type?
     *
     * rawHtml is allowed everywhere and deliberately absent from the palette:
     * it is what a pre-block-editor body imports into, so refusing it would
     * make every legacy template unsaveable the moment someone opened it.
     */
    public static function allows(DocumentTemplateType $type, string $blockType): bool
    {
        if ($blockType === 'rawHtml') {
            return true;
        }

        $definition = self::BLOCKS[$blockType] ?? null;

        if ($definition === null || ($definition['inline'] ?? false)) {
            return false;
        }

        return in_array($type->value, $definition['types'], true);
    }
}
```

- [ ] **Step 4: Run the tests and make sure they pass**

Run: `pnpm php ./vendor/bin/pest tests/Unit/Documents/DocumentBlockCatalogTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Documents/Support/DocumentBlockCatalog.php tests/Unit/Documents/DocumentBlockCatalogTest.php
git commit -m "feat(documents): add the per-type block catalogue"
```

---

### Task 2: The `blocks` column

Give a template somewhere to keep its edit source of truth, versioned by the mechanism that already exists.

**Files:**
- Create: `database/migrations/2026_08_12_090000_add_blocks_to_document_templates_table.php`
- Modify: `app/Documents/Models/DocumentTemplate.php`, `app/Documents/Data/DocumentTemplateData.php`
- Test: `tests/Feature/Documents/DocumentTemplateVersioningTest.php` (append)

**Interfaces:**
- Consumes: `DocumentTemplate::newVersion(array $attributes)` and `createFirstVersion(array $attributes)` (existing).
- Produces: `DocumentTemplate->blocks` — `?array`, cast from JSONB. `DocumentTemplateData->blocks` — `?array`, surfacing in TS as `App.Documents.Data.DocumentTemplateData['blocks']`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Documents/DocumentTemplateVersioningTest.php`:

```php
it('carries blocks into a new version alongside the body', function () {
    $template = DocumentTemplate::factory()->create([
        'body' => '<p>alt</p>',
        'blocks' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'alt']]]],
    ]);

    $version = $template->newVersion([
        'name' => $template->name,
        'body' => '<p>neu</p>',
        'blocks' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'neu']]]],
    ]);

    expect($version->version)->toBe(2)
        ->and($version->blocks['blocks'][0]['data']['text'])->toBe('neu')
        ->and($template->fresh()->blocks['blocks'][0]['data']['text'])->toBe('alt');
});

it('leaves blocks null for a template written before the editor existed', function () {
    $template = DocumentTemplate::factory()->create(['body' => '<p>alt</p>', 'blocks' => null]);

    expect($template->fresh()->blocks)->toBeNull();
});
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `pnpm php ./vendor/bin/pest tests/Feature/Documents/DocumentTemplateVersioningTest.php`
Expected: FAIL — SQL error, column `blocks` does not exist.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_12_090000_add_blocks_to_document_templates_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The block editor's source of truth. `body` keeps its exact meaning — HTML,
 * serialized from these blocks on save — so MergeFieldResolver,
 * DocumentRenderer and the Gotenberg pipeline are untouched by this column.
 *
 * Nullable with no backfill: a template written before the editor existed
 * keeps blocks null and is imported into a single rawHtml block the first
 * time someone opens it. A template nobody edits stays exactly as it is.
 *
 * ponytail: blocks and body are written together by the client and could in
 * principle disagree if a bug posts one without the other. blocks is
 * authoritative and re-saving repairs body. Upgrade path if that ever bites:
 * a PHP serializer deriving body server-side, which is deliberately not
 * written here — it would be a second renderer to keep in step with the TS one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->jsonb('blocks')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('blocks');
        });
    }
};
```

- [ ] **Step 4: Make the model and Data object carry it**

In `app/Documents/Models/DocumentTemplate.php`, add `'blocks'` to `$fillable`:

```php
protected $fillable = ['family_id', 'version', 'type', 'shoot_kind', 'name', 'body', 'blocks', 'is_current'];
```

Add the cast in `casts()`:

```php
protected function casts(): array
{
    return [
        'type' => DocumentTemplateType::class,
        'blocks' => 'array',
        'is_current' => 'boolean',
    ];
}
```

In `newVersion()`, add `blocks` to the `static::create([...])` array, defaulting to what the row already has so a caller that omits it does not wipe the blocks:

```php
'body' => $attributes['body'],
'blocks' => $attributes['blocks'] ?? $this->blocks,
```

In `createFirstVersion()`, add:

```php
'body' => $attributes['body'],
'blocks' => $attributes['blocks'] ?? null,
```

In `app/Documents/Data/DocumentTemplateData.php`, add the property to the constructor after `body`:

```php
public string $body,
/** @var array<string, mixed>|null */
public ?array $blocks,
```

and to `fromModel()`:

```php
body: $template->body,
blocks: $template->blocks,
```

- [ ] **Step 5: Run the migration and the tests**

```bash
pnpm php php artisan migrate
pnpm php ./vendor/bin/pest tests/Feature/Documents/
```
Expected: migration runs; all Documents tests PASS.

- [ ] **Step 6: Regenerate the TypeScript types**

Run: `pnpm php php artisan typescript:transform`
Expected: `resources/js/types/generated.d.ts` now shows `blocks` on `App.Documents.Data.DocumentTemplateData`.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Documents/Models/DocumentTemplate.php app/Documents/Data/DocumentTemplateData.php resources/js/types/generated.d.ts tests/Feature/Documents/DocumentTemplateVersioningTest.php
git commit -m "feat(documents): store editor blocks alongside the rendered body"
```

---

### Task 3: The serializer

Blocks → HTML, pure, no DOM. This is what makes `body` derivable and keeps the whole PHP render path unchanged.

**Files:**
- Create: `resources/js/admin/documents/blocks/types.ts`, `resources/js/admin/documents/blocks/serialize.ts`
- Test: `resources/js/admin/documents/blocks/serialize.test.ts`

**Interfaces:**
- Produces:
  - `type EditorBlock = { id?: string; type: string; data: Record<string, any> }`
  - `type EditorDocument = { time?: number; blocks: EditorBlock[]; version?: string }`
  - `type SerializeProfile = 'print' | 'mail'`
  - `serialize(doc: EditorDocument, profile: SerializeProfile): string`
  - `stripUnsafeHrefs(html: string): string`

- [ ] **Step 1: Write the failing test**

Create `resources/js/admin/documents/blocks/serialize.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { serialize, stripUnsafeHrefs } from './serialize'
import type { EditorDocument } from './types'

const doc = (...blocks: EditorDocument['blocks']): EditorDocument => ({ blocks })

describe('text blocks', () => {
  it('renders a paragraph and keeps the inline markup Editor.js already sanitised', () => {
    const html = serialize(doc({ type: 'paragraph', data: { text: 'Hallo <b>Lena</b>' } }), 'print')

    expect(html).toBe('<p>Hallo <b>Lena</b></p>')
  })

  it('renders a heading at its level, clamped to h1-h3', () => {
    expect(serialize(doc({ type: 'header', data: { text: 'Vertrag', level: 2 } }), 'print')).toBe('<h2>Vertrag</h2>')
    expect(serialize(doc({ type: 'header', data: { text: 'Tief', level: 9 } }), 'print')).toBe('<h3>Tief</h3>')
  })

  it('renders both list styles, and tolerates the nested-list item shape', () => {
    expect(serialize(doc({ type: 'list', data: { style: 'ordered', items: ['eins', 'zwei'] } }), 'print'))
      .toBe('<ol><li>eins</li><li>zwei</li></ol>')

    expect(serialize(doc({ type: 'list', data: { style: 'unordered', items: [{ content: 'eins', items: [] }] } }), 'print'))
      .toBe('<ul><li>eins</li></ul>')
  })

  it('renders a quote with its caption, and omits an empty caption', () => {
    expect(serialize(doc({ type: 'quote', data: { text: 'Zitat', caption: 'Lena' } }), 'print'))
      .toBe('<blockquote><p>Zitat</p><cite>Lena</cite></blockquote>')

    expect(serialize(doc({ type: 'quote', data: { text: 'Zitat', caption: '' } }), 'print'))
      .toBe('<blockquote><p>Zitat</p></blockquote>')
  })

  it('renders a delimiter as a rule', () => {
    expect(serialize(doc({ type: 'delimiter', data: {} }), 'print')).toBe('<hr class="doc-rule">')
  })
})

describe('layout blocks', () => {
  it('renders a table, promoting the first row to headings when asked', () => {
    const block = { type: 'table', data: { withHeadings: true, content: [['Position', 'Betrag'], ['Reportage', '2.400']] } }

    expect(serialize(doc(block), 'print')).toBe(
      '<table class="doc-table"><thead><tr><th>Position</th><th>Betrag</th></tr></thead>' +
        '<tbody><tr><td>Reportage</td><td>2.400</td></tr></tbody></table>',
    )
  })

  it('renders columns as a flex row for print and as a table for mail', () => {
    const block = { type: 'columns', data: { left: 'links', right: 'rechts' } }

    expect(serialize(doc(block), 'print')).toBe(
      '<div class="doc-cols"><div class="doc-col">links</div><div class="doc-col">rechts</div></div>',
    )

    const mail = serialize(doc(block), 'mail')
    expect(mail).toContain('<table')
    expect(mail).toContain('width="50%"')
    expect(mail).not.toContain('class="doc-cols"')
  })

  it('renders a callout with a class for print and inline styles for mail', () => {
    const block = { type: 'callout', data: { text: 'Bitte beachten' } }

    expect(serialize(doc(block), 'print')).toBe('<div class="doc-callout">Bitte beachten</div>')
    expect(serialize(doc(block), 'mail')).toContain('style="')
  })

  it('renders a spacer at one of three fixed heights and ignores anything else', () => {
    expect(serialize(doc({ type: 'spacer', data: { size: 'l' } }), 'print')).toBe('<div style="height:48px"></div>')
    expect(serialize(doc({ type: 'spacer', data: { size: 'nonsense' } }), 'print')).toBe('<div style="height:24px"></div>')
  })

  it('renders an image with an escaped alt, and drops one with an unsafe url', () => {
    expect(serialize(doc({ type: 'image', data: { url: 'https://x.test/a.png', caption: 'Logo & Co' } }), 'print'))
      .toBe('<figure><img src="https://x.test/a.png" alt="Logo &amp; Co"><figcaption>Logo &amp; Co</figcaption></figure>')

    expect(serialize(doc({ type: 'image', data: { url: 'javascript:alert(1)', caption: '' } }), 'print')).toBe('')
  })
})

describe('safety and unknown input', () => {
  it('passes a legacy raw-html block through untouched', () => {
    expect(serialize(doc({ type: 'rawHtml', data: { html: '<p>alt <span>x</span></p>' } }), 'print'))
      .toBe('<p>alt <span>x</span></p>')
  })

  it('drops an anchor href that is not http, https or mailto', () => {
    expect(stripUnsafeHrefs('<a href="javascript:alert(1)">x</a>')).toBe('<a>x</a>')
    expect(stripUnsafeHrefs('<a href="https://x.test">x</a>')).toBe('<a href="https://x.test">x</a>')
    expect(stripUnsafeHrefs('<a href="mailto:a@b.test">x</a>')).toBe('<a href="mailto:a@b.test">x</a>')
  })

  it('strips an unsafe href out of paragraph text on the way through', () => {
    expect(serialize(doc({ type: 'paragraph', data: { text: '<a href="javascript:x()">klick</a>' } }), 'print'))
      .toBe('<p><a>klick</a></p>')
  })

  it('skips a block type it does not know rather than emitting garbage', () => {
    expect(serialize(doc({ type: 'signature', data: {} }, { type: 'paragraph', data: { text: 'da' } }), 'print'))
      .toBe('<p>da</p>')
  })

  it('joins blocks with a newline so the stored body stays readable in a diff', () => {
    expect(serialize(doc({ type: 'paragraph', data: { text: 'a' } }, { type: 'paragraph', data: { text: 'b' } }), 'print'))
      .toBe('<p>a</p>\n<p>b</p>')
  })
})
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `pnpm --filter @studio/app test`
Expected: FAIL — cannot resolve `./serialize`.

- [ ] **Step 3: Write the types**

Create `resources/js/admin/documents/blocks/types.ts`:

```ts
// The shape Editor.js's save() returns. Kept in its own module so the
// serializer, the importer and the Vue components share one definition
// rather than each restating it.
export type EditorBlock = {
  id?: string
  type: string
  // Editor.js block data is per-tool by design; narrowing it here would mean
  // a union that has to be edited every time a tool is added.
  data: Record<string, any>
}

export type EditorDocument = {
  time?: number
  blocks: EditorBlock[]
  version?: string
}

// print goes to Gotenberg, mail goes into an inbox we do not render.
export type SerializeProfile = 'print' | 'mail'

export type PaletteEntry = {
  type: string
  group: string
  label: string
  inline: boolean
}
```

- [ ] **Step 4: Write the serializer**

Create `resources/js/admin/documents/blocks/serialize.ts`:

```ts
import type { EditorBlock, EditorDocument, SerializeProfile } from './types'

// Blocks -> HTML. Pure, no DOM, so it runs identically in the browser and in
// vitest's node environment. Its output lands in document_templates.body and
// from there in MergeFieldResolver, DocumentRenderer and Gotenberg — nothing
// downstream of the column knows the editor exists.
//
// Inline markup inside a block (bold, links, merge-field chips) arrives
// already restricted by each tool's Editor.js `sanitize` config, so this file
// does not re-sanitise it. The one exception is href, which is re-checked
// here because a mail body reaches an inbox we never render ourselves.

const SPACER_HEIGHTS: Record<string, string> = { s: '12px', m: '24px', l: '48px' }
const SAFE_URL = /^(https?:|mailto:)/i

export function stripUnsafeHrefs(html: string): string {
  return html.replace(/\s+href="([^"]*)"/gi, (match, url: string) => (SAFE_URL.test(url.trim()) ? match : ''))
}

function escapeText(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

function inline(value: unknown): string {
  return stripUnsafeHrefs(String(value ?? ''))
}

/** @editorjs/list v1 gives plain strings, v2 gives {content, items}. Accept both. */
function itemText(item: unknown): string {
  if (typeof item === 'string') return inline(item)

  return inline((item as { content?: string })?.content ?? '')
}

function serializeBlock(block: EditorBlock, profile: SerializeProfile): string {
  const data = block.data ?? {}

  switch (block.type) {
    case 'paragraph':
      return `<p>${inline(data.text)}</p>`

    case 'header': {
      const level = Math.min(3, Math.max(1, Number(data.level) || 2))

      return `<h${level}>${inline(data.text)}</h${level}>`
    }

    case 'list': {
      const tag = data.style === 'ordered' ? 'ol' : 'ul'
      const items = (data.items ?? []).map((item: unknown) => `<li>${itemText(item)}</li>`).join('')

      return `<${tag}>${items}</${tag}>`
    }

    case 'quote': {
      const caption = String(data.caption ?? '')
      const cite = caption === '' ? '' : `<cite>${inline(caption)}</cite>`

      return `<blockquote><p>${inline(data.text)}</p>${cite}</blockquote>`
    }

    case 'delimiter':
      return '<hr class="doc-rule">'

    case 'table': {
      const rows: string[][] = data.content ?? []
      const cell = (tag: string) => (row: string[]) =>
        `<tr>${row.map((value) => `<${tag}>${inline(value)}</${tag}>`).join('')}</tr>`

      if (data.withHeadings && rows.length > 0) {
        const [head, ...body] = rows

        return (
          `<table class="doc-table"><thead>${cell('th')(head)}</thead>` +
          `<tbody>${body.map(cell('td')).join('')}</tbody></table>`
        )
      }

      return `<table class="doc-table"><tbody>${rows.map(cell('td')).join('')}</tbody></table>`
    }

    case 'columns': {
      // Inline HTML per side rather than nested blocks: a nested Editor.js
      // instance per column is a large amount of machinery for a two-column
      // address header, and the tool in Task 6b is two contenteditable divs.
      const left = inline(data.left)
      const right = inline(data.right)

      // Email clients still lay out with tables far more reliably than with
      // flex, so the mail profile emits one rather than a styled div.
      if (profile === 'mail') {
        return (
          '<table width="100%" cellpadding="0" cellspacing="0"><tr>' +
          `<td width="50%" valign="top">${left}</td><td width="50%" valign="top">${right}</td>` +
          '</tr></table>'
        )
      }

      return `<div class="doc-cols"><div class="doc-col">${left}</div><div class="doc-col">${right}</div></div>`
    }

    case 'callout':
      return profile === 'mail'
        ? `<div style="padding:12px 14px;border-left:3px solid #B44E22;background:#FBF0EA">${inline(data.text)}</div>`
        : `<div class="doc-callout">${inline(data.text)}</div>`

    case 'spacer':
      return `<div style="height:${SPACER_HEIGHTS[String(data.size)] ?? SPACER_HEIGHTS.m}"></div>`

    case 'image': {
      const url = String(data.url ?? '').trim()

      if (!SAFE_URL.test(url)) return ''

      const caption = escapeText(String(data.caption ?? ''))
      const figcaption = caption === '' ? '' : `<figcaption>${caption}</figcaption>`

      return `<figure><img src="${escapeText(url)}" alt="${caption}">${figcaption}</figure>`
    }

    // What a pre-editor body is imported into. Verbatim by definition — this
    // is the same staff-authored HTML the textarea accepted before.
    case 'rawHtml':
      return String(data.html ?? '')

    // A block from a later phase, or a tool that was removed. Emitting a
    // guess would put wrong wording in a contract; skipping is honest.
    default:
      return ''
  }
}

export function serialize(doc: EditorDocument, profile: SerializeProfile): string {
  return (doc.blocks ?? [])
    .map((block) => serializeBlock(block, profile))
    .filter((html) => html !== '')
    .join('\n')
}
```

- [ ] **Step 5: Run the tests and make sure they pass**

Run: `pnpm --filter @studio/app test`
Expected: PASS, all serializer tests green.

- [ ] **Step 6: Commit**

```bash
git add resources/js/admin/documents/blocks/
git commit -m "feat(documents): serialize editor blocks to print and mail html"
```

---

### Task 4: Legacy import and sample substitution

Two small pure functions the editor needs before it can open an old template or paint an instant preview.

**Files:**
- Create: `resources/js/admin/documents/blocks/importHtml.ts`
- Test: `resources/js/admin/documents/blocks/importHtml.test.ts`

**Interfaces:**
- Consumes: `EditorDocument` from `./types`.
- Produces:
  - `htmlToBlocks(html: string): EditorDocument` — wraps a legacy body in one `rawHtml` block; an empty body yields an empty document, not a block containing nothing.
  - `fillSamples(html: string, samples: Record<string, string>): string` — replaces `{{ path }}` with its sample value; an unknown path becomes an empty string, matching `MergeFieldResolver`'s behaviour.

- [ ] **Step 1: Write the failing test**

Create `resources/js/admin/documents/blocks/importHtml.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { fillSamples, htmlToBlocks } from './importHtml'
import { serialize } from './serialize'

describe('htmlToBlocks', () => {
  it('wraps a pre-editor body in a single raw-html block that serializes back to itself', () => {
    const body = '<p>Vertrag mit {{ contact.name }}</p><ul><li>x</li></ul>'
    const doc = htmlToBlocks(body)

    expect(doc.blocks).toHaveLength(1)
    expect(doc.blocks[0].type).toBe('rawHtml')
    expect(serialize(doc, 'print')).toBe(body)
  })

  it('gives an empty body an empty document rather than an empty block', () => {
    expect(htmlToBlocks('').blocks).toEqual([])
    expect(htmlToBlocks('   ').blocks).toEqual([])
  })
})

describe('fillSamples', () => {
  const samples = { 'contact.name': 'Lena Meier', 'project.name': 'Hochzeit Meier & Kowalski' }

  it('substitutes a known path, with or without inner spaces', () => {
    expect(fillSamples('<p>{{ contact.name }}</p>', samples)).toBe('<p>Lena Meier</p>')
    expect(fillSamples('<p>{{contact.name}}</p>', samples)).toBe('<p>Lena Meier</p>')
  })

  it('empties an unknown path, the same way MergeFieldResolver does', () => {
    expect(fillSamples('<p>{{ studio.unknown }}</p>', samples)).toBe('<p></p>')
  })

  it('substitutes every occurrence, not just the first', () => {
    expect(fillSamples('{{ contact.name }} und {{ contact.name }}', samples)).toBe('Lena Meier und Lena Meier')
  })

  it('leaves html without placeholders untouched', () => {
    expect(fillSamples('<p>nichts</p>', samples)).toBe('<p>nichts</p>')
  })
})
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `pnpm --filter @studio/app test`
Expected: FAIL — cannot resolve `./importHtml`.

- [ ] **Step 3: Write the module**

Create `resources/js/admin/documents/blocks/importHtml.ts`:

```ts
import type { EditorDocument } from './types'

// A template written before the block editor existed has blocks = null. Rather
// than parsing its HTML into blocks — which would silently rewrite wording
// that is already in signed contracts' template history — it becomes one
// rawHtml block the author can keep, replace, or build around.
export function htmlToBlocks(html: string): EditorDocument {
  if (html.trim() === '') return { blocks: [] }

  return { blocks: [{ type: 'rawHtml', data: { html } }] }
}

// The instant half of the two-speed preview: what the sheet shows in the
// ~400ms before the server's answer lands. Cosmetic only — the server response
// always overwrites it, and MergeFieldResolver remains the only thing that
// decides what a real document says.
//
// ponytail: a plain regex, not a resolver. It matches MergeFieldResolver's
// {{ path }} form and its empty-string-for-unknown behaviour; if the two ever
// need to agree on more than that, this should be replaced by a server round
// trip rather than grown into a second engine.
export function fillSamples(html: string, samples: Record<string, string>): string {
  return html.replace(/\{\{\s*([\w.]+)\s*\}\}/g, (_match, path: string) => samples[path] ?? '')
}
```

- [ ] **Step 4: Run the tests and make sure they pass**

Run: `pnpm --filter @studio/app test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/admin/documents/blocks/importHtml.ts resources/js/admin/documents/blocks/importHtml.test.ts
git commit -m "feat(documents): import legacy bodies and fill sample values"
```

---

### Task 5: Controller accepts and validates blocks

Persist blocks, refuse a block the type may not use, and hand the editor its palette and sample values.

**Files:**
- Create: `app/Documents/Support/SafeHtml.php`
- Modify: `app/Documents/Http/Controllers/DocumentTemplateController.php`, `app/Documents/Support/MergeFieldVocabulary.php`
- Test: `tests/Feature/Documents/DocumentTemplateAdminTest.php` (append), `tests/Unit/Documents/SafeHtmlTest.php`

**Interfaces:**
- Consumes: `DocumentBlockCatalog::for()` / `::allows()` (Task 1), `DocumentTemplate->blocks` (Task 2).
- Produces:
  - `SafeHtml::hasUnsafeHref(string $html): bool` — true when any `href` uses a scheme outside `http`, `https`, `mailto`, in any quoting style.
  - `MergeFieldVocabulary::samples(): array<string, string>` — flat path => sample value.
  - Inertia props on `DocumentTemplates/Create` and `DocumentTemplates/Edit`: `palette` (list of `{type, group, label, inline}`) and `mergeFields` (list of `{path, sample}`).
  - `store`/`update` accept `blocks` as a nullable array.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Documents/DocumentTemplateAdminTest.php`:

```php
it('stores the editor blocks next to the serialized body', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', [
            'name' => 'Fotovertrag',
            'type' => 'contract',
            'shoot_kind' => null,
            'body' => '<p>Hallo</p>',
            'blocks' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Hallo']]]],
        ])
        ->assertRedirect('/dokumentvorlagen');

    $template = DocumentTemplate::query()->firstOrFail();

    expect($template->body)->toBe('<p>Hallo</p>')
        ->and($template->blocks['blocks'][0]['type'])->toBe('paragraph');
});

it('refuses a block the document type may not use', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', [
            'name' => 'Angebotsmail',
            'type' => 'mail',
            'shoot_kind' => null,
            'body' => '<p>x</p>',
            'blocks' => ['blocks' => [['type' => 'columns', 'data' => ['left' => [], 'right' => []]]]],
        ])
        ->assertInvalid(['blocks']);

    expect(DocumentTemplate::query()->count())->toBe(0);
});

it('accepts a template saved without blocks, the way an untouched legacy row is', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', [
            'name' => 'Alt',
            'type' => 'contract',
            'shoot_kind' => null,
            'body' => '<p>alt</p>',
        ])
        ->assertRedirect('/dokumentvorlagen');

    expect(DocumentTemplate::query()->firstOrFail()->blocks)->toBeNull();
});

it('gives the create screen a palette for its type and the merge-field samples', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/dokumentvorlagen/neu')
        ->assertInertia(fn ($page) => $page
            ->component('DocumentTemplates/Create')
            ->has('palettes.contract')
            ->has('palettes.mail')
            ->has('mergeFields.0.path')
            ->has('mergeFields.0.sample'));
});

it('gives the edit screen the palette for the template it is editing', function () {
    $template = DocumentTemplate::factory()->create(['type' => 'mail']);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get("/dokumentvorlagen/{$template->public_id}")
        ->assertInertia(fn ($page) => $page
            ->component('DocumentTemplates/Edit')
            ->where('palette', fn ($palette) => collect($palette)->pluck('type')->doesntContain('columns')));
});
```

Note: `assertInertia` needs `use Inertia\Testing\AssertableInertia;` only if you type-hint it — the closure form above does not. Check the top of the file for the `DocumentTemplate` and `StaffRole` imports and add them if missing.

Also create `tests/Unit/Documents/SafeHtmlTest.php`:

```php
<?php

use App\Documents\Support\SafeHtml;

it('spots an unsafe href in every quoting style', function () {
    expect(SafeHtml::hasUnsafeHref('<a href="javascript:alert(1)">x</a>'))->toBeTrue()
        ->and(SafeHtml::hasUnsafeHref("<a href='javascript:alert(1)'>x</a>"))->toBeTrue()
        ->and(SafeHtml::hasUnsafeHref('<a href=javascript:alert(1)>x</a>'))->toBeTrue()
        ->and(SafeHtml::hasUnsafeHref('<a href = "data:text/html;base64,PHNjcmlwdD4=">x</a>'))->toBeTrue();
});

it('passes the three schemes a document may link to', function () {
    expect(SafeHtml::hasUnsafeHref('<a href="https://x.test">x</a>'))->toBeFalse()
        ->and(SafeHtml::hasUnsafeHref('<a href="http://x.test">x</a>'))->toBeFalse()
        ->and(SafeHtml::hasUnsafeHref("<a href='mailto:a@b.test'>x</a>"))->toBeFalse();
});

it('passes html with no links at all', function () {
    expect(SafeHtml::hasUnsafeHref('<p>Vertrag mit {{ contact.name }}</p>'))->toBeFalse();
});

it('treats a relative href as unsafe, since a document has no site to be relative to', function () {
    expect(SafeHtml::hasUnsafeHref('<a href="/intern/kunden">x</a>'))->toBeTrue();
});
```

And append to `tests/Feature/Documents/DocumentTemplateAdminTest.php`:

```php
it('refuses a body carrying an href with an unsafe scheme', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', [
            'name' => 'Angebotsmail',
            'type' => 'mail',
            'shoot_kind' => null,
            'body' => '<p><a href=\'javascript:alert(1)\'>klick</a></p>',
        ])
        ->assertInvalid(['body']);

    expect(DocumentTemplate::query()->count())->toBe(0);
});
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `pnpm php ./vendor/bin/pest tests/Feature/Documents/DocumentTemplateAdminTest.php`
Expected: FAIL — `blocks` is not persisted and `palette` is missing from the props.

- [ ] **Step 3: Add `samples()` to the vocabulary**

In `app/Documents/Support/MergeFieldVocabulary.php`, add below `paths()`:

```php
/**
 * The flat path => sample map behind sample(). The editor shows these values
 * in its merge-field chips so a paragraph reads as prose rather than as
 * braces, and paints them into its instant preview.
 *
 * @return array<string, string>
 */
public static function samples(): array
{
    return self::SAMPLE;
}
```

- [ ] **Step 4: Widen the controller**

In `app/Documents/Http/Controllers/DocumentTemplateController.php`:

Add the imports:

```php
use App\Documents\Support\DocumentBlockCatalog;
use Illuminate\Validation\ValidationException;
```

Replace the body of `create()`:

```php
public function create(): Response
{
    $this->authorize('create', DocumentTemplate::class);

    return Inertia::render('DocumentTemplates/Create', [
        'types' => $this->typeOptions(),
        'shootKinds' => $this->shootKindOptions(),
        // Keyed by type: the create screen's type select changes the palette
        // without a round trip, and there are three small lists in total.
        'palettes' => collect(DocumentTemplateType::cases())
            ->mapWithKeys(fn (DocumentTemplateType $type) => [$type->value => DocumentBlockCatalog::for($type)])
            ->all(),
        'mergeFields' => $this->mergeFieldOptions(),
    ]);
}
```

Replace the body of `edit()` (keeping the `abort_unless`):

```php
public function edit(DocumentTemplate $documentTemplate): Response
{
    $this->authorize('update', $documentTemplate);

    abort_unless($documentTemplate->is_current, 404);

    return Inertia::render('DocumentTemplates/Edit', [
        'template' => DocumentTemplateData::fromModel($documentTemplate),
        // One palette, not three: a family's type never changes.
        'palette' => DocumentBlockCatalog::for($documentTemplate->type),
        'mergeFields' => $this->mergeFieldOptions(),
    ]);
}
```

In `store()`, replace the `$validated = $request->validate([...])` call with:

```php
$validated = $request->validate([
    'name' => ['required', 'string', 'max:255'],
    'body' => ['required', 'string', 'max:100000'],
    'type' => ['required', Rule::enum(DocumentTemplateType::class)],
    'shoot_kind' => ['nullable', Rule::enum(ShootKind::class)],
    'blocks' => ['nullable', 'array'],
    'blocks.blocks' => ['nullable', 'array'],
    'blocks.blocks.*.type' => ['required_with:blocks.blocks', 'string'],
]);

$this->assertBlocksAllowed($request, DocumentTemplateType::from($validated['type']));
```

In `update()`, likewise:

```php
$validated = $request->validate([
    'name' => ['required', 'string', 'max:255'],
    'body' => ['required', 'string', 'max:100000'],
    'blocks' => ['nullable', 'array'],
    'blocks.blocks' => ['nullable', 'array'],
    'blocks.blocks.*.type' => ['required_with:blocks.blocks', 'string'],
]);

$this->assertBlocksAllowed($request, $documentTemplate->type);
```

Add the two private helpers at the bottom of the class:

```php
/**
 * A block the type may not use is a validation error, never a silent drop:
 * quietly discarding a block would hand a client a document missing wording
 * its author believed they had written.
 */
private function assertBlocksAllowed(Request $request, DocumentTemplateType $type): void
{
    $blocks = $request->input('blocks.blocks', []);

    foreach ($blocks as $block) {
        $blockType = $block['type'] ?? '';

        if (! DocumentBlockCatalog::allows($type, $blockType)) {
            throw ValidationException::withMessages([
                'blocks' => "Baustein \"{$blockType}\" ist für diesen Dokumenttyp nicht erlaubt.",
            ]);
        }
    }
}

/** @return list<array{path: string, sample: string}> */
private function mergeFieldOptions(): array
{
    return collect(MergeFieldVocabulary::samples())
        ->map(fn (string $sample, string $path) => ['path' => $path, 'sample' => $sample])
        ->values()
        ->all();
}
```

`store()` and `update()` need no further change: `$validated` now carries `blocks`, and `createFirstVersion()` / `newVersion()` already read it (Task 2).

Now the server-side half of the href check. The TS serializer strips unsafe hrefs on the way out of the editor, but that runs in the browser and is not a trust boundary — `body` arrives as a POST field and need never have passed through the editor at all. Create `app/Documents/Support/SafeHtml.php`:

```php
<?php

namespace App\Documents\Support;

/**
 * The server-side half of the href check the serializer also performs in the
 * browser. The browser half keeps a template author from pasting a bad link
 * in by accident; this half is the one that actually holds, because `body`
 * arrives as a POST field and need never have passed through the editor.
 *
 * It matters most for mail: a Contract or Quote becomes a PDF we render
 * ourselves, but a mail body is HTML handed to an inbox we never render, so
 * a javascript:/data: href in it is a link someone else's client decides
 * what to do with.
 *
 * Relative hrefs count as unsafe: a document is a PDF or an email, with no
 * site for "/intern/kunden" to be relative to, so one is a mistake rather
 * than a link.
 */
class SafeHtml
{
    /** Matches href in all three quoting styles: "x", 'x', and bare. */
    private const HREF = '/\s+href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';

    private const SAFE_SCHEME = '/^(https?:|mailto:)/i';

    public static function hasUnsafeHref(string $html): bool
    {
        preg_match_all(self::HREF, $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $url = trim($match[3] ?? '') ?: trim($match[2] ?? '') ?: trim($match[1] ?? '');

            if (preg_match(self::SAFE_SCHEME, $url) !== 1) {
                return true;
            }
        }

        return false;
    }
}
```

Add the rule to the `body` line in **both** `store()` and `update()`:

```php
'body' => ['required', 'string', 'max:100000', function (string $attribute, mixed $value, \Closure $fail) {
    if (SafeHtml::hasUnsafeHref((string) $value)) {
        $fail('Links dürfen nur auf http, https oder mailto zeigen.');
    }
}],
```

and import `use App\Documents\Support\SafeHtml;`.

- [ ] **Step 5: Run the tests and make sure they pass**

Run: `pnpm php ./vendor/bin/pest tests/Feature/Documents/`
Expected: PASS, including the five new tests.

- [ ] **Step 6: Commit**

```bash
git add app/Documents tests/Feature/Documents/DocumentTemplateAdminTest.php
git commit -m "feat(documents): accept and validate editor blocks on save"
```

---

### Task 6: The editor shell

Editor.js wired to the palette, with the merge-field inline tool.

**Files:**
- Create: `resources/js/admin/documents/blocks/tools.ts`, `resources/js/admin/documents/blocks/mergeFieldTool.ts`, `resources/js/admin/documents/blocks/customTools.ts`, `resources/js/admin/components/DocumentEditor.vue`
- Test: `resources/js/admin/documents/blocks/tools.test.ts`
- Modify: `apps/studio/package.json`

**Interfaces:**
- Consumes: `PaletteEntry` (Task 3), `EditorDocument` (Task 3).
- Produces:
  - `buildTools(palette: PaletteEntry[], registry: Record<string, unknown>): Record<string, unknown>` — the Editor.js `tools` option, containing exactly the palette's non-inline entries that the registry knows about.
  - `DocumentEditor.vue` — props `palette: PaletteEntry[]`, `mergeFields: {path: string, sample: string}[]`; `v-model` of type `EditorDocument`.

- [ ] **Step 1: Install the dependencies**

```bash
pnpm --filter @studio/app add @editorjs/editorjs @editorjs/header @editorjs/list @editorjs/quote @editorjs/delimiter @editorjs/table
```

Expected: six entries added to `apps/studio/package.json` dependencies.

- [ ] **Step 2: Write the failing test**

`buildTools` takes the registry as a parameter precisely so this test needs no DOM and no real Editor.js import.

Create `resources/js/admin/documents/blocks/tools.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { buildTools } from './tools'
import type { PaletteEntry } from './types'

const entry = (type: string, inline = false): PaletteEntry => ({ type, group: 'text', label: type, inline })

describe('buildTools', () => {
  const registry = { paragraph: 'P', header: 'H', columns: 'C' }

  it('registers exactly the palette entries the registry knows', () => {
    const tools = buildTools([entry('paragraph'), entry('header')], registry)

    expect(Object.keys(tools).sort()).toEqual(['header', 'paragraph'])
  })

  it('leaves out a block the palette does not offer, so a mail cannot insert columns', () => {
    const tools = buildTools([entry('paragraph')], registry)

    expect(tools).not.toHaveProperty('columns')
  })

  it('leaves out inline tools — they belong to the inline toolbar, not the inserter', () => {
    const tools = buildTools([entry('paragraph'), entry('mergeField', true)], registry)

    expect(tools).not.toHaveProperty('mergeField')
  })

  it('skips a palette entry with no implementation rather than crashing the editor', () => {
    const tools = buildTools([entry('paragraph'), entry('signature')], registry)

    expect(Object.keys(tools)).toEqual(['paragraph'])
  })
})
```

- [ ] **Step 3: Run it to make sure it fails**

Run: `pnpm --filter @studio/app test`
Expected: FAIL — cannot resolve `./tools`.

- [ ] **Step 4: Write `buildTools`**

Create `resources/js/admin/documents/blocks/tools.ts`:

```ts
import type { PaletteEntry } from './types'

// Palette (from DocumentBlockCatalog, per document type) -> Editor.js `tools`.
//
// The registry is a parameter rather than an import so this stays a pure
// function: DocumentEditor.vue passes the real tool classes, and its test
// passes strings. It also means a palette entry whose tool has not been built
// yet (a Phase 2 block reaching a stale bundle) drops out quietly instead of
// throwing inside Editor.js's constructor.
export function buildTools(
  palette: PaletteEntry[],
  registry: Record<string, unknown>,
): Record<string, unknown> {
  const tools: Record<string, unknown> = {}

  for (const entry of palette) {
    if (entry.inline) continue

    const tool = registry[entry.type]

    if (tool !== undefined) tools[entry.type] = tool
  }

  return tools
}
```

- [ ] **Step 5: Run the tests and make sure they pass**

Run: `pnpm --filter @studio/app test`
Expected: PASS.

- [ ] **Step 6: Write the merge-field inline tool**

Create `resources/js/admin/documents/blocks/mergeFieldTool.ts`:

```ts
// The inline tool behind the chips in the mockup. A merge field is a span
// carrying its path in data-mf and *showing the sample value* — a paragraph
// full of {{ contact.name }} is unreadable as prose, which is what staff are
// actually editing. serialize.ts turns the span back into {{ path }} via the
// data attribute, so what is stored stays exactly what MergeFieldResolver
// already understands.
type MergeField = { path: string; sample: string }

export function createMergeFieldTool(fields: MergeField[]) {
  return class MergeFieldTool {
    static isInline = true
    static title = 'Feld'

    // Editor.js strips anything not declared here when it saves a block.
    static get sanitize() {
      return { span: { class: 'doc-mf', 'data-mf': true } }
    }

    private button: HTMLButtonElement | null = null
    private range: Range | null = null

    render(): HTMLElement {
      this.button = document.createElement('button')
      this.button.type = 'button'
      this.button.classList.add('ce-inline-tool')
      this.button.textContent = '{ }'

      return this.button
    }

    surround(range: Range): void {
      this.range = range
      this.showPicker()
    }

    checkState(): boolean {
      return false
    }

    private showPicker(): void {
      const picker = document.createElement('div')
      picker.className = 'doc-mf-picker'

      const search = document.createElement('input')
      search.className = 'inp'
      search.type = 'search'
      picker.append(search)

      const list = document.createElement('div')
      list.className = 'doc-mf-list'
      picker.append(list)

      const paint = () => {
        const needle = search.value.toLowerCase()
        list.replaceChildren()

        for (const field of fields.filter((f) => f.path.toLowerCase().includes(needle)).slice(0, 12)) {
          const option = document.createElement('button')
          option.type = 'button'
          option.className = 'doc-mf-option'
          option.innerHTML = `<b>${field.sample}</b><em>${field.path}</em>`
          option.addEventListener('click', () => {
            this.insert(field)
            picker.remove()
          })
          list.append(option)
        }
      }

      search.addEventListener('input', paint)
      paint()

      document.body.append(picker)
      search.focus()

      // One dismissal path, registered after this click finishes bubbling.
      setTimeout(() => {
        document.addEventListener('click', () => picker.remove(), { once: true })
      }, 0)
    }

    private insert(field: MergeField): void {
      if (this.range === null) return

      const chip = document.createElement('span')
      chip.className = 'doc-mf'
      chip.dataset.mf = field.path
      chip.contentEditable = 'false'
      chip.textContent = field.sample

      this.range.deleteContents()
      this.range.insertNode(chip)
    }
  }
}
```

Now teach the serializer to turn a chip back into a placeholder. In `resources/js/admin/documents/blocks/serialize.ts`, add to the `inline()` function:

```ts
const MERGE_CHIP = /<span[^>]*data-mf="([\w.]+)"[^>]*>.*?<\/span>/gi

function inline(value: unknown): string {
  return stripUnsafeHrefs(String(value ?? '').replace(MERGE_CHIP, (_match, path: string) => `{{ ${path} }}`))
}
```

And add to `serialize.test.ts`:

```ts
it('turns a merge-field chip back into the placeholder MergeFieldResolver understands', () => {
  const chip = '<span class="doc-mf" data-mf="contact.name" contenteditable="false">Lena Meier</span>'

  expect(serialize(doc({ type: 'paragraph', data: { text: `Hallo ${chip}` } }), 'print'))
    .toBe('<p>Hallo {{ contact.name }}</p>')
})
```

- [ ] **Step 7: Run the serializer tests**

Run: `pnpm --filter @studio/app test`
Expected: PASS, including the new chip round-trip test.

- [ ] **Step 8: Write the four custom tools**

Editor.js ships nothing for a callout, a spacer, a two-column row or a URL-only image, and these four are what make a contract's palette differ from a mail's. Create `resources/js/admin/documents/blocks/customTools.ts`:

```ts
// The blocks Editor.js has no package for. Each is a plain tool class: a
// toolbox entry, a render() that returns its editing DOM, and a save() that
// returns exactly the data shape serialize.ts expects. Kept in one file
// because they are four small classes that change together, not four
// concerns.
//
// `sanitize` is the allow-list Editor.js applies when it saves a block, which
// is why serialize.ts does not re-sanitise inline markup.

function field(placeholder: string, value: string, className = 'doc-editable'): HTMLElement {
  const element = document.createElement('div')
  element.className = className
  element.contentEditable = 'true'
  element.dataset.placeholder = placeholder
  element.innerHTML = value

  return element
}

/** data: { text: string } */
export class CalloutTool {
  static get toolbox() {
    return { title: 'Hinweis', icon: '<b>◇</b>' }
  }

  static get sanitize() {
    return { text: { b: {}, i: {}, a: { href: true }, span: { class: 'doc-mf', 'data-mf': true } } }
  }

  private element: HTMLElement

  constructor({ data }: { data: { text?: string } }) {
    this.element = field('Hinweis', data?.text ?? '', 'doc-callout doc-editable')
  }

  render(): HTMLElement {
    return this.element
  }

  save(): { text: string } {
    return { text: this.element.innerHTML }
  }
}

/** data: { size: 's' | 'm' | 'l' } — serialize.ts falls back to 'm' for anything else. */
export class SpacerTool {
  static get toolbox() {
    return { title: 'Abstand', icon: '<b>↕</b>' }
  }

  private element: HTMLElement
  private size: string

  constructor({ data }: { data: { size?: string } }) {
    this.size = data?.size ?? 'm'
    this.element = document.createElement('div')
    this.element.className = 'doc-spacer-tool'
    this.paint()
  }

  private paint(): void {
    this.element.replaceChildren()

    for (const size of ['s', 'm', 'l']) {
      const button = document.createElement('button')
      button.type = 'button'
      button.textContent = size.toUpperCase()
      button.className = size === this.size ? 'doc-size on' : 'doc-size'
      button.addEventListener('click', () => {
        this.size = size
        this.paint()
      })
      this.element.append(button)
    }
  }

  render(): HTMLElement {
    return this.element
  }

  save(): { size: string } {
    return { size: this.size }
  }
}

/** data: { left: string, right: string } — inline HTML per side, not nested blocks. */
export class ColumnsTool {
  static get toolbox() {
    return { title: '2 Spalten', icon: '<b>◫</b>' }
  }

  static get sanitize() {
    const inline = { b: {}, i: {}, a: { href: true }, br: {}, span: { class: 'doc-mf', 'data-mf': true } }

    return { left: inline, right: inline }
  }

  private element: HTMLElement
  private left: HTMLElement
  private right: HTMLElement

  constructor({ data }: { data: { left?: string; right?: string } }) {
    this.left = field('Links', data?.left ?? '')
    this.right = field('Rechts', data?.right ?? '')
    this.element = document.createElement('div')
    this.element.className = 'doc-cols'
    this.left.classList.add('doc-col')
    this.right.classList.add('doc-col')
    this.element.append(this.left, this.right)
  }

  render(): HTMLElement {
    return this.element
  }

  save(): { left: string; right: string } {
    return { left: this.left.innerHTML, right: this.right.innerHTML }
  }
}

/** data: { url: string, caption: string } — URL only; uploads are out of scope for Phase 1. */
export class ImageTool {
  static get toolbox() {
    return { title: 'Bild', icon: '<b>▭</b>' }
  }

  private element: HTMLElement
  private url: HTMLInputElement
  private caption: HTMLInputElement

  constructor({ data }: { data: { url?: string; caption?: string } }) {
    this.url = document.createElement('input')
    this.url.className = 'inp'
    this.url.type = 'url'
    this.url.placeholder = 'https://…'
    this.url.value = data?.url ?? ''

    this.caption = document.createElement('input')
    this.caption.className = 'inp'
    this.caption.type = 'text'
    this.caption.placeholder = 'Bildunterschrift'
    this.caption.value = data?.caption ?? ''

    const figure = document.createElement('img')
    figure.className = 'doc-image-preview'
    const paint = () => {
      figure.src = this.url.value
      figure.style.display = this.url.value === '' ? 'none' : 'block'
    }
    this.url.addEventListener('input', paint)
    paint()

    this.element = document.createElement('div')
    this.element.className = 'doc-image-tool'
    this.element.append(figure, this.url, this.caption)
  }

  render(): HTMLElement {
    return this.element
  }

  save(): { url: string; caption: string } {
    return { url: this.url.value.trim(), caption: this.caption.value }
  }
}
```

These classes touch `document` at construction time, so they are not unit-tested in vitest's node environment — Task 9's Playwright run is what exercises them. `serialize.ts` already has a tested case for each of the four data shapes above.

Add the matching styles to `resources/css/document-editor.css` in Task 8 Step 4 — append:

```css
.doc-editable:empty::before { content: attr(data-placeholder); color: var(--faint); }
.doc-spacer-tool { display: flex; gap: var(--s2); }
.doc-size { padding: 3px 9px; border: 1px solid var(--line); border-radius: var(--r-sm); background: none; cursor: pointer; }
.doc-size.on { background: var(--ink); color: var(--bg); border-color: var(--ink); }
.doc-image-tool { display: flex; flex-direction: column; gap: var(--s2); }
.doc-image-preview { max-width: 100%; border-radius: 4px; }
```

- [ ] **Step 9: Write the editor component**

Create `resources/js/admin/components/DocumentEditor.vue`:

```vue
<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import EditorJS from '@editorjs/editorjs'
import Header from '@editorjs/header'
import List from '@editorjs/list'
import Quote from '@editorjs/quote'
import Delimiter from '@editorjs/delimiter'
import Table from '@editorjs/table'
import { buildTools } from '@/admin/documents/blocks/tools'
import { createMergeFieldTool } from '@/admin/documents/blocks/mergeFieldTool'
import { CalloutTool, ColumnsTool, ImageTool, SpacerTool } from '@/admin/documents/blocks/customTools'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

// The Editor.js shell. Vanilla instance rather than a wrapper package: the
// library is framework-agnostic and its lifecycle is two calls, so a wrapper
// would be a dependency that only renames them.
//
// The palette comes from the server (DocumentBlockCatalog) rather than being
// listed here, so what the inserter offers and what save validation accepts
// are the same list.
const props = defineProps<{
  palette: PaletteEntry[]
  mergeFields: { path: string; sample: string }[]
}>()

const model = defineModel<EditorDocument>({ required: true })

const holder = ref<HTMLElement | null>(null)
let editor: EditorJS | null = null

// paragraph is Editor.js's built-in default and needs no entry. The four
// custom tools come from customTools.ts (Task 6b) — Editor.js ships nothing
// for a callout, a spacer, a two-column row or a URL-only image.
const registry = {
  header: { class: Header, config: { levels: [1, 2, 3], defaultLevel: 2 } },
  list: List,
  quote: Quote,
  delimiter: Delimiter,
  table: Table,
  callout: CalloutTool,
  spacer: SpacerTool,
  columns: ColumnsTool,
  image: ImageTool,
}

onMounted(() => {
  editor = new EditorJS({
    holder: holder.value!,
    data: model.value,
    minHeight: 240,
    placeholder: 'Text schreiben oder / für Bausteine',
    tools: {
      ...buildTools(props.palette, registry),
      mergeField: { class: createMergeFieldTool(props.mergeFields) },
    },
    // Editor.js fires onChange on every keystroke; save() is cheap (it walks
    // the block list) and the preview pane is what debounces the round trip.
    onChange: async () => {
      model.value = (await editor!.save()) as EditorDocument
    },
  })
})

onBeforeUnmount(() => {
  editor?.destroy()
  editor = null
})

// A parent that replaces the whole document (type switch on the create screen)
// has to push it into the instance — Editor.js does not watch its data option.
watch(
  () => props.palette,
  async () => {
    if (editor === null) return
    await editor.isReady
    await editor.render(model.value)
  },
)
</script>

<template>
  <div ref="holder" class="doc-canvas"></div>
</template>
```

- [ ] **Step 10: Type-check**

Run: `pnpm lint`
Expected: no errors from `DocumentEditor.vue`, `tools.ts`, `mergeFieldTool.ts`, `customTools.ts`. If `@editorjs/*` ships no types for a package, add a one-line `declare module '@editorjs/<name>'` to `resources/js/types/inertia.d.ts`'s neighbours — create `resources/js/types/editorjs.d.ts` for it rather than sprinkling `@ts-expect-error`.

- [ ] **Step 11: Commit**

```bash
git add resources/js/admin/documents resources/js/admin/components/DocumentEditor.vue package.json ../../pnpm-lock.yaml
git commit -m "feat(documents): add the Editor.js shell, custom tools and merge-field tool"
```

---

### Task 7: The preview pane

The right half: instant local paint, then server truth on idle.

**Files:**
- Create: `resources/js/admin/components/DocumentPreviewPane.vue`

**Interfaces:**
- Consumes: `serialize()` (Task 3), `fillSamples()` (Task 4), `useDebounceFn` from `@vueuse/core` (already installed), the existing `POST /dokumentvorlagen/vorschau` endpoint.
- Produces: `DocumentPreviewPane.vue` — props `type: string`, `doc: EditorDocument`, `samples: Record<string, string>`.

This task has no unit test of its own: its logic is `serialize` + `fillSamples` (both already tested) plus a debounce from `@vueuse/core`. Its behaviour is covered end-to-end in Task 9. Do not add a jsdom environment to `vitest.config.ts` for it.

- [ ] **Step 1: Write the component**

Create `resources/js/admin/components/DocumentPreviewPane.vue`:

```vue
<script setup lang="ts">
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { serialize } from '@/admin/documents/blocks/serialize'
import { fillSamples } from '@/admin/documents/blocks/importHtml'
import type { EditorDocument } from '@/admin/documents/blocks/types'

// Two-speed preview. The local paint keeps the sheet alive while typing; the
// server answer is the one that counts, because it runs the real
// MergeFieldResolver and DocumentRenderer — the same pair RenderContractJob
// uses. The local pass never decides anything, it only fills the 400ms.
const props = defineProps<{
  type: string
  doc: EditorDocument
  samples: Record<string, string>
}>()

const html = ref('')
const stale = ref(false)

const refreshFromServer = useDebounceFn(async () => {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
  const body = serialize(props.doc, props.type === 'mail' ? 'mail' : 'print')

  // An empty document has nothing to preview and the endpoint requires a body.
  if (body === '') {
    html.value = ''
    stale.value = false

    return
  }

  const response = await fetch('/dokumentvorlagen/vorschau', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'text/html' },
    body: JSON.stringify({ type: props.type, body }),
  })

  if (response.ok) {
    html.value = await response.text()
    stale.value = false
  }
}, 400)

watch(
  () => [props.doc, props.type],
  () => {
    stale.value = true
    html.value = fillSamples(serialize(props.doc, props.type === 'mail' ? 'mail' : 'print'), props.samples)
    refreshFromServer()
  },
  { deep: true, immediate: true },
)
</script>

<template>
  <section class="doc-pane">
    <div class="doc-panehead">
      <span class="kick">{{ $t('documentTemplates.previewLabel') }}</span>
      <span class="doc-live" :class="{ stale }">{{
        stale ? $t('documentTemplates.previewStale') : $t('documentTemplates.previewFresh')
      }}</span>
    </div>
    <!-- sandbox="" (no allow-* tokens at all): a template body is
         staff-authored HTML, not trusted script — an empty sandbox strips
         script execution, form submission and top-level navigation, so a
         <script> in a preview can never run with this session's origin. -->
    <iframe class="doc-previewframe" :srcdoc="html" sandbox=""></iframe>
  </section>
</template>
```

- [ ] **Step 2: Type-check**

Run: `pnpm lint`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/admin/components/DocumentPreviewPane.vue
git commit -m "feat(documents): add the two-speed preview pane"
```

---

### Task 8: Wire the screens

Swap the textarea for the split, add copy and styling, drop the old preview component.

**Files:**
- Modify: `resources/js/admin/pages/DocumentTemplates/Create.vue`, `Edit.vue`, `resources/js/locales/de.json`, `resources/css/admin.css`
- Create: `resources/css/document-editor.css`
- Delete: `resources/js/admin/components/DocumentTemplatePreview.vue`

**Interfaces:**
- Consumes: `DocumentEditor.vue` (Task 6), `DocumentPreviewPane.vue` (Task 7), the `palettes` / `palette` / `mergeFields` props (Task 5), `htmlToBlocks()` (Task 4).

- [ ] **Step 1: Add the copy**

In `resources/js/locales/de.json`, inside `documentTemplates`, replace `"bodyHint"` and add:

```json
"bodyHint": "Bausteine über + oder /",
"previewLabel": "Vorschau · Beispieldaten",
"previewFresh": "aktuell",
"previewStale": "wird geprüft",
"letterhead": "Briefkopf · aus Einstellungen",
"footer": "Fußzeile · aus Einstellungen",
"blocksError": "Baustein für diesen Dokumenttyp nicht erlaubt"
```

Keep them this short. Do not add explanatory sentences.

- [ ] **Step 2: Rewrite `Edit.vue`**

Replace `resources/js/admin/pages/DocumentTemplates/Edit.vue` entirely:

```vue
<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import DocumentEditor from '@/admin/components/DocumentEditor.vue'
import DocumentPreviewPane from '@/admin/components/DocumentPreviewPane.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { htmlToBlocks } from '@/admin/documents/blocks/importHtml'
import { serialize } from '@/admin/documents/blocks/serialize'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

const props = defineProps<{
  template: App.Documents.Data.DocumentTemplateData
  palette: PaletteEntry[]
  mergeFields: { path: string; sample: string }[]
}>()

// A template written before the block editor has blocks = null; its body
// becomes one rawHtml block the author can keep or replace. The import is
// only persisted if they save.
const doc = ref<EditorDocument>((props.template.blocks as EditorDocument | null) ?? htmlToBlocks(props.template.body))

const samples = computed(() =>
  Object.fromEntries(props.mergeFields.map((field) => [field.path, field.sample])),
)

const form = useForm({ name: props.template.name, body: props.template.body, blocks: doc.value })

function submit() {
  form.body = serialize(doc.value, props.template.type === 'mail' ? 'mail' : 'print')
  form.blocks = doc.value
  form.patch(`/dokumentvorlagen/${props.template.id}`)
}
</script>

<template>
  <AdminLayout active="dokumentvorlagen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('documentTemplates.title') }} · {{ template.typeLabel }}</div>
        <h1>{{ template.name }}</h1>
      </div>
      <Button type="button" :disabled="form.processing" @click="submit">{{ $t('common.save') }}</Button>
    </div>
    <p class="hint">{{ $t('documentTemplates.version', { version: template.version }) }} · {{ $t('documentTemplates.editHint') }}</p>
    <p v-if="form.errors.blocks" class="err">{{ form.errors.blocks }}</p>

    <div class="fld" style="max-width: 420px">
      <Label for="name">{{ $t('documentTemplates.name') }}</Label>
      <Input id="name" v-model="form.name" type="text" required />
      <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
    </div>

    <div class="doc-split">
      <section class="doc-pane">
        <div class="doc-panehead">
          <span class="kick">{{ $t('documentTemplates.body') }}</span>
          <span class="hint">{{ $t('documentTemplates.bodyHint') }}</span>
        </div>
        <div class="doc-sheet">
          <div class="doc-chrome">{{ $t('documentTemplates.letterhead') }}</div>
          <DocumentEditor v-model="doc" :palette="palette" :merge-fields="mergeFields" />
          <div class="doc-chrome foot">{{ $t('documentTemplates.footer') }}</div>
        </div>
      </section>

      <div class="doc-splitbar"></div>

      <DocumentPreviewPane :type="template.type" :doc="doc" :samples="samples" />
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 3: Rewrite `Create.vue`**

Replace `resources/js/admin/pages/DocumentTemplates/Create.vue` entirely. It keeps its three selects and gains the same split; the palette follows the selected type.

```vue
<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import DocumentEditor from '@/admin/components/DocumentEditor.vue'
import DocumentPreviewPane from '@/admin/components/DocumentPreviewPane.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { serialize } from '@/admin/documents/blocks/serialize'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

const props = defineProps<{
  types: { value: string; label: string }[]
  shootKinds: { value: string; label: string }[]
  palettes: Record<string, PaletteEntry[]>
  mergeFields: { path: string; sample: string }[]
}>()

const doc = ref<EditorDocument>({ blocks: [] })
const form = useForm({ name: '', body: '', blocks: doc.value, type: 'contract', shoot_kind: '' })

const palette = computed(() => props.palettes[form.type] ?? [])
const samples = computed(() =>
  Object.fromEntries(props.mergeFields.map((field) => [field.path, field.sample])),
)

function submit() {
  form.body = serialize(doc.value, form.type === 'mail' ? 'mail' : 'print')
  form.blocks = doc.value
  form.transform((data) => ({ ...data, shoot_kind: data.shoot_kind || null })).post('/dokumentvorlagen')
}
</script>

<template>
  <AdminLayout active="dokumentvorlagen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('documentTemplates.title') }}</div>
        <h1>{{ $t('documentTemplates.createTitle') }}</h1>
      </div>
      <Button type="button" :disabled="form.processing" @click="submit">{{ $t('common.save') }}</Button>
    </div>
    <p v-if="form.errors.blocks" class="err">{{ form.errors.blocks }}</p>

    <div class="doc-meta">
      <div class="fld">
        <Label for="type">{{ $t('documentTemplates.type') }}</Label>
        <Select :model-value="form.type" @update:model-value="(value) => (form.type = String(value))">
          <SelectTrigger id="type"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="form.errors.type" class="err">{{ form.errors.type }}</p>
      </div>
      <div class="fld">
        <Label for="shoot_kind">{{ $t('documentTemplates.shootKind') }}</Label>
        <Select :model-value="form.shoot_kind" @update:model-value="(value) => (form.shoot_kind = String(value))">
          <SelectTrigger id="shoot_kind"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="">{{ $t('documentTemplates.shootKindAny') }}</SelectItem>
            <SelectItem v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="fld">
        <Label for="name">{{ $t('documentTemplates.name') }}</Label>
        <Input id="name" v-model="form.name" type="text" required />
        <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
      </div>
    </div>

    <div class="doc-split">
      <section class="doc-pane">
        <div class="doc-panehead">
          <span class="kick">{{ $t('documentTemplates.body') }}</span>
          <span class="hint">{{ $t('documentTemplates.bodyHint') }}</span>
        </div>
        <div class="doc-sheet">
          <div class="doc-chrome">{{ $t('documentTemplates.letterhead') }}</div>
          <DocumentEditor v-model="doc" :palette="palette" :merge-fields="mergeFields" />
          <div class="doc-chrome foot">{{ $t('documentTemplates.footer') }}</div>
        </div>
      </section>

      <div class="doc-splitbar"></div>

      <DocumentPreviewPane :type="form.type" :doc="doc" :samples="samples" />
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 4: Write the stylesheet**

Create `resources/css/document-editor.css`. Values come from the mockup; the tokens are the ones already in `tokens.css`.

```css
/*
 * The block editor's own screen. Both halves use the same paper width on
 * purpose: a canvas narrower than the preview wraps lines differently, and
 * staff learn to distrust the preview.
 */

.doc-meta { display: flex; gap: var(--s5); margin: var(--s4) 0 0; }
.doc-meta .fld { max-width: 240px; }

.doc-split {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 7px minmax(0, 1fr);
  gap: 0;
  margin-top: var(--s5);
  min-height: 0;
}
.doc-splitbar { display: flex; align-items: center; justify-content: center; cursor: col-resize; }
.doc-splitbar::before { content: ''; width: 1px; height: 100%; background: var(--line); }

.doc-pane { min-width: 0; display: flex; flex-direction: column; }
.doc-panehead {
  display: flex; align-items: center; gap: var(--s3);
  padding-bottom: var(--s3); border-bottom: 1px solid var(--line);
}
.doc-panehead .hint { margin-left: auto; }
.doc-live { margin-left: auto; font: 400 11px/1 var(--font); color: var(--faint); }
.doc-live.stale { color: var(--accent); }

.doc-sheet {
  background: #fff; border: 1px solid var(--line); border-radius: 2px;
  margin: var(--s5) auto 0; padding: 0 40px 40px; max-width: 520px; width: 100%;
}
.doc-chrome {
  margin: 0 -40px var(--s5); padding: 9px 12px;
  border-bottom: 1px dashed var(--line-hover); background: #FCFBFA;
  font: 500 10px/1.4 var(--font); letter-spacing: .06em; text-transform: uppercase; color: var(--faint);
}
.doc-chrome.foot { margin: var(--s5) -40px -40px; border-bottom: none; border-top: 1px dashed var(--line-hover); }

.doc-previewframe {
  flex: 1; width: 100%; min-height: 520px; margin-top: var(--s5);
  border: 1px solid var(--line); border-radius: 2px; background: #fff;
}

/* merge-field chip: the sample value, with the path on hover */
.doc-mf {
  display: inline-flex; align-items: center; padding: 1px 6px; border-radius: 4px;
  background: var(--accent-bg); color: #8A3A18; font-weight: 500;
  box-shadow: inset 0 0 0 1px #F1DCD0; white-space: nowrap;
}
.doc-mf-picker {
  position: absolute; z-index: 40; width: 288px; padding: var(--s3);
  background: #fff; border: 1px solid var(--line-hover); border-radius: var(--r);
  box-shadow: 0 12px 32px -8px rgba(20, 16, 14, .18);
}
.doc-mf-list { display: flex; flex-direction: column; margin-top: var(--s2); max-height: 260px; overflow: auto; }
.doc-mf-option {
  display: flex; flex-direction: column; align-items: flex-start; gap: 2px;
  padding: 7px var(--s2); border: 0; border-radius: 6px; background: none; text-align: left; cursor: pointer;
}
.doc-mf-option:hover { background: var(--hover); }
.doc-mf-option b { font: 500 12.5px/1.3 var(--font); color: var(--ink); }
.doc-mf-option em { font: 400 11px/1.3 var(--font); font-style: normal; color: var(--faint); }

/*
 * Editor.js tools build their own DOM in plain TypeScript, so they cannot use
 * the <Input> component the rest of the admin now uses. This is the one field
 * rule left in CSS, and it deliberately mirrors inputVariants' ported values
 * rather than inventing a second field style.
 */
.doc-tool-input {
  width: 100%;
  padding: 9px 11px;
  border: 1px solid var(--line);
  border-radius: var(--r);
  background: var(--bg);
  font: 400 13px/1.4 var(--font);
  color: var(--ink);
  transition: border-color 120ms ease-out;
}
.doc-tool-input::placeholder { color: var(--faint); }
.doc-tool-input:hover { border-color: var(--line-hover); }
.doc-tool-input:focus { border-color: var(--accent); outline: none; }

/* custom block tools */
.doc-editable:empty::before { content: attr(data-placeholder); color: var(--faint); }
.doc-spacer-tool { display: flex; gap: var(--s2); }
.doc-size { padding: 3px 9px; border: 1px solid var(--line); border-radius: var(--r-sm); background: none; cursor: pointer; }
.doc-size.on { background: var(--ink); color: var(--bg); border-color: var(--ink); }
.doc-image-tool { display: flex; flex-direction: column; gap: var(--s2); }
.doc-image-preview { max-width: 100%; border-radius: 4px; }

/* rendered document blocks — the classes serialize.ts emits */
.doc-rule { border: 0; border-top: 1px solid var(--line); margin: var(--s5) 0; }
.doc-table { width: 100%; border-collapse: collapse; }
.doc-table th, .doc-table td { padding: 6px 0; border-bottom: 1px solid var(--line); text-align: left; }
.doc-cols { display: flex; gap: var(--s6); }
.doc-col { flex: 1; min-width: 0; }
.doc-callout { padding: 12px 14px; border-left: 3px solid var(--accent); background: var(--accent-bg); }

@media (max-width: 1100px) {
  .doc-split { grid-template-columns: minmax(0, 1fr); }
  .doc-splitbar { display: none; }
  .doc-previewframe { min-height: 360px; }
}
```

In `resources/css/admin.css`, add the import below the existing two:

```css
@import "./document-editor.css";
```

- [ ] **Step 4b: Repoint the Editor.js tools off the retired `.inp` class**

Task 6's tools were written against `.inp`, which no longer exists — the shadcn-vue migration (commit `9259e07`) retired `.inp`, `.btn`, `.abtn`, `.txtbtn` and `.fld > label` from `forms.css`, `tokens.css` and `admin-extra.css`. These two files build raw DOM inside Editor.js tools, so they cannot use the `<Input>` component; they take the `.doc-tool-input` rule added above instead.

In `resources/js/admin/documents/blocks/mergeFieldTool.ts`, the picker's search field:

```ts
search.className = 'doc-tool-input'
```

In `resources/js/admin/documents/blocks/customTools.ts`, both inputs in `ImageTool`'s constructor:

```ts
this.url.className = 'doc-tool-input'
...
this.caption.className = 'doc-tool-input'
```

Grep for any other `'inp'`, `'btn'`, `'txtbtn'` or `'abtn'` string in `resources/js/admin/documents/` and `resources/js/admin/components/DocumentEditor.vue` / `DocumentPreviewPane.vue` before moving on — a retired class in a string literal fails silently, with no build error and no test failure, and shows up only as an unstyled control.

- [ ] **Step 5: Delete the old preview component**

```bash
grep -rn "DocumentTemplatePreview" resources/js
```
Expected: no hits after Steps 2 and 3. Then:

```bash
git rm resources/js/admin/components/DocumentTemplatePreview.vue
```

- [ ] **Step 6: Type-check and run every test**

```bash
pnpm lint
pnpm --filter @studio/app test
pnpm php ./vendor/bin/pest
```
Expected: all PASS. `tests/Feature/Documents/DocumentTemplatePreviewTest.php` must still pass untouched — the endpoint did not change.

- [ ] **Step 7: Commit**

```bash
git add resources/js resources/css
git commit -m "feat(documents): replace the html textarea with the block editor"
```

---

### Task 9: End-to-end

One journey that fails if any seam in Tasks 1–8 is broken.

**Files:**
- Create: `tests/e2e/document-editor.spec.ts`

**Interfaces:**
- Consumes: `signIn` from `tests/e2e/helpers` (existing; seeded owner is `e2e-basic@studio.test` / `correct-horse`).

- [ ] **Step 1: Write the test**

Create `tests/e2e/document-editor.spec.ts`:

```ts
import { expect, test } from '@playwright/test'
import { signIn } from './helpers'

test('a template written in blocks previews with sample data and survives a reload', async ({ page }) => {
  await signIn(page)

  await page.goto('/dokumentvorlagen/neu')
  await page.getByLabel('Name').fill('Fotovertrag Hochzeit')

  // Editor.js focuses its first empty paragraph on mount.
  await page.locator('.doc-canvas [contenteditable="true"]').first().click()
  await page.keyboard.type('Vertrag mit ')

  // The chip is the seam between the editor and MergeFieldResolver: typed as a
  // placeholder here, it must come back as a resolved name in the preview.
  await page.keyboard.type('{{ contact.name }}')

  // The server preview is debounced by 400ms and rendered into a sandboxed
  // iframe, so assert against the frame rather than the page.
  const preview = page.frameLocator('.doc-previewframe')
  await expect(preview.getByText('Lena Meier')).toBeVisible({ timeout: 5000 })

  await page.getByRole('button', { name: 'Speichern' }).click()
  await expect(page).toHaveURL('/dokumentvorlagen')

  await page.getByRole('link', { name: 'Fotovertrag Hochzeit' }).click()
  await expect(page.locator('.doc-canvas')).toContainText('Vertrag mit')
})
```

- [ ] **Step 2: Build the bundle and run it**

```bash
pnpm --filter @studio/app build
pnpm --filter @studio/app exec playwright test document-editor
```
Expected: PASS. If the index link name does not match, check `DocumentTemplates/Index.vue` for how a row is rendered and adjust the locator — do not change the page to suit the test.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/document-editor.spec.ts
git commit -m "test(documents): cover the block editor end to end"
```

---

### Task 10: Documentation

`docs/documents.md` is the file a future engineer reads before touching any of this. It currently says a template's `body` is HTML staff type by hand.

**Files:**
- Modify: `docs/documents.md`

- [ ] **Step 1: Add the section**

Insert after the "Merge fields" section:

```markdown
## Authoring: the block editor

A template's wording is edited as blocks, not as HTML.
`document_templates.blocks` holds Editor.js JSON and is the edit source of
truth; `body` is the HTML those blocks serialize to
(`resources/js/admin/documents/blocks/serialize.ts`) and keeps its exact
former meaning — `MergeFieldResolver`, `DocumentRenderer` and
`RenderContractJob` know nothing about blocks.

Which blocks a document type may use is `App\Documents\Support\
DocumentBlockCatalog` — one list, read both by the inserter palette and by
save validation, so what staff are offered and what the server accepts cannot
drift. A block type there with no case in `serialize.ts` renders to nothing;
add both in the same commit.

A template written before the editor existed has `blocks` null. It is
imported into a single `rawHtml` block the first time someone opens it, and
that import is persisted only if they save — a template nobody edits is never
rewritten.

Merge fields are an inline tool: a `<span data-mf="contact.name">` showing the
sample value while editing, serialized back to `{{ contact.name }}`. The
vocabulary the picker offers is `MergeFieldVocabulary::samples()`, the same
constant the preview resolves against.

## Sanitising block HTML

Editor.js applies each tool's `sanitize` config when it *saves*, inside a live
browser editor. A `blocks` payload POSTed straight at the controller never
meets it, so `App\Documents\Support\InlineHtmlSanitizer` re-applies the
allow-list on the write path, in both `store()` and `update()`, before
anything is persisted. That is the copy that holds.

`resources/js/admin/documents/blocks/sanitizeInline.ts` carries the same list
for the read path, because a tool constructed from stored data assigns it
straight into a `contenteditable`. Keep the two lists identical — a tag
allowed on one side and not the other is either a hole or a silently stripped
feature. The chip's `data-mf` in particular must stay allowed on both: the
serializer reads it back to rebuild `{{ contact.name }}`.

`script`, `style`, `img`, `iframe`, `object` and `embed` are dropped whole
rather than unwrapped. Every other disallowed tag is unwrapped, keeping the
author's text — a `<div>` wraps prose someone wrote, while a `<script>` wraps
machine source that was never document text and would otherwise land in a PDF
a client reads.
```

- [ ] **Step 2: Commit**

```bash
git add docs/documents.md
git commit -m "docs(documents): describe the block editor authoring path"
```

---

### Task 11: Server-side block sanitisation

Added after the Task 6 review found that a `blocks` payload POSTed directly reaches storage with unsanitised inner HTML, which then executes in the next staff member's admin DOM. Task 6's fix closes the read path; this closes the write path, which is the one that actually holds. Run this immediately after Task 6.

**Files:**
- Create: `app/Documents/Support/InlineHtmlSanitizer.php`, `tests/Unit/Documents/InlineHtmlSanitizerTest.php`
- Modify: `app/Documents/Http/Controllers/DocumentTemplateController.php`
- Test: `tests/Feature/Documents/DocumentTemplateAdminTest.php` (append)

**Interfaces:**
- Consumes: the validated `blocks` array in `store()`/`update()` (Task 5).
- Produces:
  - `InlineHtmlSanitizer::inline(string $html): string` — strips every tag and attribute outside the allow-list, unwrapping disallowed tags rather than deleting their text.
  - `InlineHtmlSanitizer::blocks(array $blocks): array` — walks a blocks payload and sanitises every string leaf, leaving a `rawHtml` block's `html` untouched.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Documents/InlineHtmlSanitizerTest.php`:

```php
<?php

use App\Documents\Support\InlineHtmlSanitizer;

it('keeps the inline markup a document may carry', function () {
    $html = 'Hallo <b>Lena</b> und <em>Jonas</em><br><a href="https://x.test">Link</a>';

    expect(InlineHtmlSanitizer::inline($html))->toBe($html);
});

it('keeps a merge-field chip intact, since the serializer reads it back', function () {
    $chip = '<span class="doc-mf" data-mf="contact.name">Lena Meier</span>';

    expect(InlineHtmlSanitizer::inline($chip))->toBe($chip);
});

it('unwraps a disallowed tag but keeps the text inside it', function () {
    expect(InlineHtmlSanitizer::inline('<script>alert(1)</script>'))->toBe('alert(1)')
        ->and(InlineHtmlSanitizer::inline('<div>Absatz</div>'))->toBe('Absatz')
        ->and(InlineHtmlSanitizer::inline('<b onclick="x()">fett</b>'))->toBe('<b>fett</b>');
});

it('drops an img with an onerror payload entirely, tag and attributes', function () {
    expect(InlineHtmlSanitizer::inline('<img src=x onerror=alert(1)>'))->toBe('');
});

it('strips an href that is not http, https or mailto', function () {
    expect(InlineHtmlSanitizer::inline('<a href="javascript:alert(1)">klick</a>'))->toBe('<a>klick</a>')
        ->and(InlineHtmlSanitizer::inline("<a href='mailto:a@b.test'>mail</a>"))->toBe('<a href="mailto:a@b.test">mail</a>');
});

it('leaves German text and its umlauts exactly as written', function () {
    expect(InlineHtmlSanitizer::inline('Vertragsgemäß, für Grüße'))->toBe('Vertragsgemäß, für Grüße');
});

it('sanitises every string leaf in a blocks payload', function () {
    $blocks = ['blocks' => [
        ['type' => 'paragraph', 'data' => ['text' => 'Hallo <img src=x onerror=alert(1)>']],
        ['type' => 'columns', 'data' => ['left' => '<b>links</b>', 'right' => '<script>x()</script>rechts']],
    ]];

    $clean = InlineHtmlSanitizer::blocks($blocks);

    expect($clean['blocks'][0]['data']['text'])->toBe('Hallo ')
        ->and($clean['blocks'][1]['data']['left'])->toBe('<b>links</b>')
        ->and($clean['blocks'][1]['data']['right'])->toBe('x()rechts');
});

it('leaves a legacy rawHtml block alone, since it is a pre-editor body by definition', function () {
    $blocks = ['blocks' => [['type' => 'rawHtml', 'data' => ['html' => '<div class="alt"><p>Alt</p></div>']]]];

    expect(InlineHtmlSanitizer::blocks($blocks)['blocks'][0]['data']['html'])
        ->toBe('<div class="alt"><p>Alt</p></div>');
});
```

And append to `tests/Feature/Documents/DocumentTemplateAdminTest.php`:

```php
it('sanitises block html on the way into storage, not merely on the way out', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', [
            'name' => 'Fotovertrag',
            'type' => 'contract',
            'shoot_kind' => null,
            'body' => '<p>Hallo</p>',
            'blocks' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Hallo <img src=x onerror=alert(1)>']]]],
        ])
        ->assertRedirect('/dokumentvorlagen');

    expect(DocumentTemplate::query()->firstOrFail()->blocks['blocks'][0]['data']['text'])->toBe('Hallo ');
});
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `pnpm php ./vendor/bin/pest tests/Unit/Documents/InlineHtmlSanitizerTest.php`
Expected: FAIL — `Class "App\Documents\Support\InlineHtmlSanitizer" not found`.

- [ ] **Step 3: Write the sanitiser**

Create `app/Documents/Support/InlineHtmlSanitizer.php`:

```php
<?php

namespace App\Documents\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * The write-path half of the editor's inline-HTML allow-list. Editor.js
 * applies each tool's `sanitize` config when it saves inside a live browser
 * editor — which means a `blocks` payload POSTed straight at the controller
 * never meets it. Without this, such a payload is stored verbatim and then
 * re-injected as live HTML into the next staff member's contenteditable when
 * they open the template.
 *
 * The mirror of this list lives in
 * resources/js/admin/documents/blocks/sanitizeInline.ts, which defends the
 * read path. Keep the two in sync; this one is the one that holds.
 *
 * DOM-based rather than regex-based: an allow-list over parsed nodes is the
 * only kind that survives nested and malformed markup.
 */
class InlineHtmlSanitizer
{
    /** tag => allowed attributes. Mirrors DOCUMENT_INLINE in sanitizeInline.ts. */
    private const ALLOW = [
        'b' => [],
        'strong' => [],
        'i' => [],
        'em' => [],
        'u' => [],
        'br' => [],
        'a' => ['href'],
        'span' => ['class', 'data-mf'],
    ];

    private const SAFE_SCHEME = '/^(https?:|mailto:)/i';

    /** Tags whose text is worth nothing without them — dropped whole, not unwrapped. */
    private const DROP_WHOLE = ['script', 'style', 'img', 'iframe', 'object', 'embed'];

    public static function inline(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument;

        // The XML prolog is what stops DOMDocument reading UTF-8 as Latin-1 and
        // turning "Grüße" into mojibake.
        $loaded = @$document->loadHTML(
            '<?xml encoding="utf-8" ?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if ($loaded === false) {
            return '';
        }

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        self::clean($body);

        $out = '';

        foreach ($body->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return $out;
    }

    /**
     * Every string leaf in a blocks payload, sanitised. A rawHtml block is
     * skipped: it holds a pre-block-editor body, which is the same
     * staff-authored HTML the old textarea accepted, and no tool renders it
     * into the editor's DOM.
     *
     * @param  array<mixed>  $blocks
     * @return array<mixed>
     */
    public static function blocks(array $blocks): array
    {
        return self::walk($blocks, false);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function walk(array $value, bool $raw): array
    {
        $clean = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $clean[$key] = self::walk($item, $raw || ($item['type'] ?? null) === 'rawHtml');

                continue;
            }

            $clean[$key] = is_string($item) && ! $raw ? self::inline($item) : $item;
        }

        return $clean;
    }

    private static function clean(DOMNode $node): void
    {
        // Snapshot first: the loop mutates the live child list.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            self::clean($child);

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WHOLE, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! array_key_exists($tag, self::ALLOW)) {
                self::unwrap($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                if (! in_array($attribute->name, self::ALLOW[$tag], true)) {
                    $child->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a' && preg_match(self::SAFE_SCHEME, $child->getAttribute('href')) !== 1) {
                $child->removeAttribute('href');
            }
        }
    }

    /** Keeps the author's text, drops the tag around it. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
```

- [ ] **Step 4: Apply it in the controller**

In `DocumentTemplateController.php`, import `use App\Documents\Support\InlineHtmlSanitizer;` and sanitise immediately after `assertBlocksAllowed(...)` in **both** `store()` and `update()`:

```php
if (isset($validated['blocks']) && is_array($validated['blocks'])) {
    $validated['blocks'] = InlineHtmlSanitizer::blocks($validated['blocks']);
}
```

- [ ] **Step 5: Run the tests**

```bash
pnpm php ./vendor/bin/pest tests/Unit/Documents/ tests/Feature/Documents/
```
Expected: PASS, including the eight new sanitiser tests and the storage test.

- [ ] **Step 6: Commit**

```bash
git add app/Documents/Support/InlineHtmlSanitizer.php app/Documents/Http/Controllers/DocumentTemplateController.php tests/Unit/Documents/InlineHtmlSanitizerTest.php tests/Feature/Documents/DocumentTemplateAdminTest.php
git commit -m "fix(documents): sanitise block html on the write path"
```

---

## Verification checklist

Before declaring Phase 1 done, run all of these and confirm the output:

```bash
pnpm lint
pnpm --filter @studio/app test
pnpm php ./vendor/bin/pest
pnpm --filter @studio/app build
pnpm --filter @studio/app exec playwright test
```

Then, by hand at `/dokumentvorlagen/neu`:

- Switching the type select from Vertrag to E-Mail removes "2 Spalten" and "Abstand" from the inserter.
- The merge-field picker inserts a chip showing `Lena Meier`, and the preview shows the same name.
- An existing legacy template opens with its old wording intact and saves without changing it.

## What Phase 1 deliberately leaves out

- Page break, signature and Widerruf blocks — Phase 2, with the letterhead/footer settings pane.
- Line items and packages — Phase 3, the only part needing `DynamicBlockRenderer` and new merge vocabulary.
- Per-project one-off wording — Phase 4.
- Image uploads. The image block takes a URL; an upload endpoint is not in scope.
- Drag-to-reorder beyond what Editor.js gives out of the box.
- A persisted splitter ratio. The split is fixed 50/50 in Phase 1; `localStorage` persistence is a small follow-up if anyone asks.
