# Block document editor — design

Date: 2026-08-12
Status: approved, ready for an implementation plan
Mockup: [`2026-08-12-block-document-editor-mockup.html`](./2026-08-12-block-document-editor-mockup.html)

## Problem

Document templates are authored in a raw HTML `<textarea>`
(`resources/js/admin/pages/DocumentTemplates/Create.vue`). Staff who are not
comfortable writing HTML cannot change a contract's wording without help, and
the only check before a document reaches a client is a manual "Vorschau"
button that swaps a sandboxed iframe in below the form.

Replace it with a visual block editor — components list, drag/insert, live
preview on the right half of the screen — modelled on Gutenberg and Strapi's
blocks editor.

## Decisions

| Decision | Choice | Why |
|---|---|---|
| Engine | **Editor.js** | Block-native by design; user's choice over Tiptap. |
| Storage | **JSON source + HTML mirror** | `blocks` JSONB is the edit source of truth; serialising to `body` (HTML) on save leaves `MergeFieldResolver` → `DocumentRenderer` → Gotenberg completely untouched. |
| Components list | **Floating inserter popover** | Editor.js's own searchable toolbox, opened from `+` or `/`. No permanent rail — the canvas keeps its width. |
| Preview | **Instant client paint + debounced server truth** | The client pass is cosmetic only; the server response always overwrites it. |
| Palette | **Per document type** | A signature block has no business in a mail; a page break has none in an email. |

Known trade-off of the Editor.js choice: it has no ProseMirror-style inline
mark model, so inline formatting is HTML-string patching inside a block. Bold
+ link + merge-field chip interacting on the same run of text is coarser than
it would be with Tiptap. Accepted.

## Data model

Migration on `document_templates`:

- `blocks jsonb nullable` — Editor.js output (`{time, blocks: [...], version}`). Edit source of truth.
- `is_ad_hoc boolean not null default false` — a version created for one project's wording, excluded from the template index.

`body` keeps its meaning and its consumers exactly: HTML, derived from
`blocks` at save time. Nothing downstream of the column changes.

`DocumentTemplate::newVersion()` and `createFirstVersion()` carry both new
columns forward, so blocks are versioned for free by the existing mechanism —
an already-rendered contract keeps pointing at the exact row, blocks and all.

**Legacy rows** have `blocks = null`. The editor imports their `body` into a
single `rawHtml` block on open; that import is persisted on the next save.
No backfill migration — a template nobody edits stays exactly as it is.

**Drift.** `blocks` and `body` could in principle disagree if a client bug
posts one without the other. `blocks` is authoritative; re-saving repairs
`body`. Mark this in code with a `ponytail:` comment naming the ceiling
(server-side re-serialisation from `blocks` is the upgrade path, and requires
a PHP serializer we are deliberately not writing).

## Block catalogue and per-type palette

One source of truth: `App\Documents\Support\DocumentBlockCatalog`. It names
every block, its group, and which `DocumentTemplateType` values may use it.
It is read in three places, so the palette a user sees and the blocks the
server accepts can never drift:

1. the Inertia prop that builds the inserter,
2. save validation (a block not allowed for that type is a validation error, not silently dropped),
3. the TS serializer's profile selection.

| Group | Block | contract | quote | mail | letterhead/footer |
|---|---|:--:|:--:|:--:|:--:|
| Text | paragraph | ✓ | ✓ | ✓ | ✓ |
| Text | heading (H1–H3) | ✓ | ✓ | ✓ (H2/H3) | — |
| Text | list | ✓ | ✓ | ✓ | — |
| Text | quote | ✓ | ✓ | ✓ | — |
| Text | divider | ✓ | ✓ | ✓ | ✓ |
| Layout | table | ✓ | ✓ | ✓ | — |
| Layout | two columns | ✓ | ✓ | — | ✓ |
| Layout | image | ✓ | ✓ | ✓ | ✓ |
| Layout | callout | ✓ | ✓ | ✓ | — |
| Layout | spacer | ✓ | ✓ | — | ✓ |
| Document | page break | ✓ | ✓ | — | — |
| Document | signature | ✓ | — | — | — |
| Document | Widerruf (`settings.widerrufText`) | ✓ | ✓ | — | — |
| Data | merge field (inline) | ✓ | ✓ | ✓ | ✓ |
| Data | line items | ✓ | ✓ | ✓ | — |
| Data | packages | ✓ | ✓ | ✓ | — |

`type` is fixed for the life of a template family (`newVersion()` never
accepts it), so a palette never changes under the user mid-edit.

These allow-lists are a product judgement, not a technical constraint —
correcting a cell is a one-line change in the catalogue.

## Serializer

`resources/js/admin/documents/blocks/serialize.ts` — one pure function per
block type, `blocks → html`. Output is generated from JSON, so it is escaped
by construction; only the legacy `rawHtml` block passes through verbatim.

Two profiles:

- `print` — semantic HTML plus classes, `page-break-after` for the page break block, `@page` margins. Consumed by Gotenberg.
- `mail` — inline styles, table-based two-column and layout, no page break. Email HTML is not print HTML.

Pure in, pure out, so it is directly unit-testable.

## Editor shell

New `resources/js/admin/components/DocumentEditor.vue`, wrapping a vanilla
Editor.js instance (create in `onMounted`, `destroy()` in `onBeforeUnmount`,
`v-model` fed by its `onChange` → `save()`).

Layout, per the mockup:

- `grid-template-columns: minmax(0,1fr) 7px minmax(0,1fr)` with a draggable splitter, ratio persisted in `localStorage`. Default 50/50.
- **Both halves use the same paper width.** If the canvas sheet is narrower than the preview sheet, line wraps differ and staff learn to distrust the preview.
- Letterhead and footer appear on the canvas as dimmed, non-editable dashed bands with a "Briefkopf bearbeiten →" link. They come from studio settings, not from the template, and the editor must not imply otherwise.
- Merge-field chips render **the sample value** (`Lena Meier`), not `{{ contact.name }}`, so prose stays readable. The path is shown on hover and in the block settings.
- `lineItems`, `packages` and `signature` render as bordered objects with a source line ("wird beim Rendern aus dem Angebot gefüllt") rather than as editable text.
- Below 1100px the halves stack and the preview collapses to a toggle.

## Preview

1. On every change: serialize `blocks` → HTML in TS, substitute sample values with a plain regex, write into the sandboxed `srcdoc` iframe. Instant, cosmetic, decides nothing.
2. On 400ms idle: POST the serialized HTML to the existing `/dokumentvorlagen/vorschau`, which resolves through the real `MergeFieldResolver`, then `DynamicBlockRenderer`, then `DocumentRenderer`. The response replaces the iframe content.

Server truth always wins. `sandbox=""` on the iframe stays exactly as it is
today. Scroll position is preserved across refreshes so the sheet does not
jump while typing.

The `A4 | E-Mail` toggle in the mockup selects the serializer profile the
preview requests. It is shown only for `mail` templates, where both readings
are meaningful; a contract or quote previews as A4 only.

## Backend changes

- Migration: `document_templates.blocks`, `document_templates.is_ad_hoc`.
- `DocumentBlockCatalog` (new) — the palette/allow-list source described above.
- `DocumentTemplateController`: `store`/`update` accept and validate `blocks`; `index` excludes `is_ad_hoc`; `create`/`edit` pass the palette and `MergeFieldVocabulary::sample()` as props.
- `MergeFieldVocabulary`: add `quote.items` (array) and `packages` with sample rows, so the preview shows a realistic three-line table.
- `DynamicBlockRenderer` (new): runs between merge resolution and letterhead wrapping; replaces `<div data-block="line-items">` / `data-block="packages"` markers with a server-built table from merge data. **No template language** — one block type, one renderer, no `{{#each}}` parser.
- `StudioSettingsController`: letterhead/footer fields plus a Settings→Dokumente pane using the same editor with the reduced palette. These columns exist (`StudioSettingsResolver::documentLetterheadHtml()`) but have no editing UI today.

## Per-project one-off wording

`contracts.contract_template_id` already points at one exact row, not a
family. So one-off wording needs no new table: staff open a project's
contract tab, pick a template, choose "Wording anpassen", and edit its blocks.
On send, if edited, `newVersion()` writes a row with `is_ad_hoc = true`
leaving `is_current` on the real current version, and the contract's FK points
at the ad-hoc row. No new lifecycle, no new table, template list unaffected.

## Security

- Serializer output is escaped by construction. Only the legacy `rawHtml` block is verbatim — that is today's status quo, not a new hole.
- Link `href` is whitelisted to `http`/`https`/`mailto` in the serializer **and** re-checked server-side before a mail body is sent, because mail HTML reaches an inbox we do not render.
- Image uploads use the existing tenant-scoped disk conventions.
- The preview iframe keeps its empty `sandbox=""` (no `allow-*` tokens).
- Staff posting `body` HTML is unchanged in trust level — no new boundary is crossed by also accepting `blocks`.

## Testing

- **vitest** over the serializer: one case per block type × both profiles, the legacy-import round trip, and the `href` whitelist.
- **Pest**: `DocumentBlockCatalog` per-type allow-lists; save rejecting a block not allowed for the type; `DynamicBlockRenderer` marker replacement; the widened preview endpoint; ad-hoc versioning leaving `is_current` alone; the settings pane.
- **Playwright**: one e2e — insert three blocks, assert the preview iframe text, save, reload, assert blocks survive.

## Phasing

Four surfaces is too much for one plan. Implement in this order, each
independently shippable:

1. **Editor core** — Editor.js shell, text + layout blocks, merge-field chip, per-type palette, split preview, on `/dokumentvorlagen`. The whole payoff.
2. **Document blocks** — signature, Widerruf, page break, plus the letterhead/footer settings pane.
3. **Dynamic data blocks** — `DynamicBlockRenderer`, `quote.items` vocabulary, line-items and packages. The only real backend work.
4. **Per-project one-off wording** — ad-hoc versions.

Phases 3 and 4 get their own implementation plans.

## Open questions

- Merge-field chips show the sample value rather than the field name. If staff need the path visible at a glance, the fallback is a monospace path in a muted pill.
- The per-type palette cells above are a first cut and expected to be corrected in review.
