# Galerie-Testnetz Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the two client-facing gallery surfaces an assertion that breaks when their logic breaks, and fix the arrow-key bug that the missing assertion hid.

**Architecture:** Three extractions, each one collapsing a duplicate that already exists. The image-width allow-list and the srcset builder move out of two `.vue` files into `portal/lib/images.ts`; the keyboard decision and the index wrap-around move into `portal/lib/lightbox.ts`; the same two pure functions get hoisted to module scope inside `apps/gallery/src/lib/lightbox.ts` so a node-environment test can reach them without a DOM. No code is shared across the two apps — they keep separate copies on purpose.

**Tech Stack:** Vue 3 `<script setup>`, TypeScript, Vitest (`environment: 'node'`), pnpm workspaces (`@studio/app`, `@studio/gallery`).

**Spec:** `docs/superpowers/specs/2026-08-25-galerie-testnetz-design.md`

## Global Constraints

- No new entry in any `package.json`. Both Vitest configs stay on `environment: 'node'` — no jsdom, no happy-dom, no `@vue/test-utils`.
- No code shared between `apps/studio` and `apps/gallery`. `apps/gallery/src/lib/images.ts:1-11` already rejected a `packages/*` module for this; each app keeps its own copy.
- The allow-list a test asserts against is written out literally, never imported from the module under test.
- Source-of-truth widths: `App\Gallery\Support\ImageVariant::WIDTHS` = `[400, 800, 1200, 1600, 2400]`, formats `['avif', 'webp', 'jpeg']`. `GalleryImageController.php:33` answers 422 outside them. A subset is legal.
- Imports inside `resources/js` use the `@/` alias (`@/portal/lib/images`); test files import their neighbour relatively (`./images`).
- No markup changes. The 90 Playwright frames must stay byte-identical.
- Commit messages: Conventional Commits, subject ≤ 50 chars.

---

### Task 1: The width allow-list

**Files:**
- Create: `apps/studio/resources/js/portal/lib/images.ts`
- Create: `apps/studio/resources/js/portal/lib/images.test.ts`
- Modify: `apps/studio/resources/js/portal/pages/Gallery/Show.vue:166-174`
- Modify: `apps/studio/resources/js/portal/components/GalleryLightbox.vue:125-129`

**Interfaces:**
- Consumes: nothing.
- Produces: `WIDTHS: readonly number[]`, `FORMATS: readonly string[]`, `GRID_WIDTHS: readonly number[]`, `STAGE_WIDTHS: readonly number[]`, `type Format = 'avif' | 'webp' | 'jpeg'`, `srcSet(src: string, format: Format, widths: readonly number[]): string`.

Both per-screen subsets live in this module rather than in their `.vue`. The choice of subset is still the screen's; declaring it here is what lets one test see both.

- [ ] **Step 1: Write the failing test**

Create `apps/studio/resources/js/portal/lib/images.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { FORMATS, GRID_WIDTHS, srcSet, STAGE_WIDTHS, WIDTHS } from './images'

describe('the allow-list', () => {
  // Written out, not imported from ImageVariant: a test that reads its
  // expectation from the thing it checks asserts nothing. These numbers
  // standing twice is the assertion.
  it('carries the widths and formats GalleryImageController accepts', () => {
    expect([...WIDTHS]).toEqual([400, 800, 1200, 1600, 2400])
    expect([...FORMATS]).toEqual(['avif', 'webp', 'jpeg'])
  })

  it('offers only widths from that list on either screen', () => {
    for (const width of [...GRID_WIDTHS, ...STAGE_WIDTHS]) {
      expect(WIDTHS).toContain(width)
    }
  })
})

describe('srcSet', () => {
  it('appends the query to the path the server built, one entry per width', () => {
    const set = srcSet('/galerie-bilder/ver_abc/a.jpg', 'webp', GRID_WIDTHS)

    expect(set.split(', ')).toEqual([
      '/galerie-bilder/ver_abc/a.jpg?w=400&fmt=webp 400w',
      '/galerie-bilder/ver_abc/a.jpg?w=800&fmt=webp 800w',
      '/galerie-bilder/ver_abc/a.jpg?w=1200&fmt=webp 1200w',
      '/galerie-bilder/ver_abc/a.jpg?w=1600&fmt=webp 1600w',
    ])
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd apps/studio && npx vitest run resources/js/portal/lib/images.test.ts
```

Expected: FAIL, `Failed to resolve import "./images"`.

- [ ] **Step 3: Write minimal implementation**

Create `apps/studio/resources/js/portal/lib/images.ts`:

```ts
/**
 * The widths and formats the image proxy takes, kept by hand a second time.
 * GalleryImageController answers 422 for anything outside
 * App\Gallery\Support\ImageVariant's list, so a drifting copy shows up as an
 * empty tile and nowhere else. apps/gallery keeps its own third copy for the
 * cross-origin viewer, and its docblock says why that is cheaper than a
 * shared package.
 */
export const WIDTHS = [400, 800, 1200, 1600, 2400] as const
export const FORMATS = ['avif', 'webp', 'jpeg'] as const

export type Format = (typeof FORMATS)[number]

/** The grid is at most four columns of a 1440px page. 2400 would only ever be discarded. */
export const GRID_WIDTHS = [400, 800, 1200, 1600] as const

/** The stage fills 72vw, where a retina 2400 pays for itself and 400 never shows. */
export const STAGE_WIDTHS = [800, 1200, 1600, 2400] as const

/**
 * The portal is same-origin: GalleryController hands the path over ready
 * (`/galerie-bilder/{version}/{key}`) and only the query is built here.
 */
export function srcSet(src: string, format: Format, widths: readonly number[]): string {
  return widths.map((w) => `${src}?w=${w}&fmt=${format} ${w}w`).join(', ')
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd apps/studio && npx vitest run resources/js/portal/lib/images.test.ts
```

Expected: PASS, 3 tests.

- [ ] **Step 5: Point Show.vue at the module**

In `apps/studio/resources/js/portal/pages/Gallery/Show.vue`, add to the imports at the top of `<script setup>`:

```ts
import { GRID_WIDTHS, srcSet } from '@/portal/lib/images'
```

Then replace lines 166-174 — the comment, `WIDTHS`, and the local `srcSet` — leaving `SIZES` in place:

```ts
// The grid deliberately stops below the proxy's largest variant; see
// GRID_WIDTHS. Its own sizes attribute, which no other screen shares:
const SIZES = '(max-width: 760px) 50vw, (max-width: 1080px) 33vw, 25vw'
```

The three template call sites at lines 346, 347 and 350 gain the widths argument:

```html
<source type="image/avif" :srcset="srcSet(asset.src, 'avif', GRID_WIDTHS)" :sizes="SIZES" />
<source type="image/webp" :srcset="srcSet(asset.src, 'webp', GRID_WIDTHS)" :sizes="SIZES" />
```

and the `<img>` at 350:

```html
:srcset="srcSet(asset.src, 'jpeg', GRID_WIDTHS)"
```

The old local `srcSet` took the whole `Asset`; the module takes `asset.src`. Every call site changes accordingly.

- [ ] **Step 6: Point GalleryLightbox.vue at the module**

In `apps/studio/resources/js/portal/components/GalleryLightbox.vue`, add:

```ts
import { srcSet, STAGE_WIDTHS } from '@/portal/lib/images'
```

Delete lines 125-129 (`const WIDTHS` and `function srcSet`). Update the template at line 179:

```html
<img :src="`${asset.src}?w=1600&fmt=jpeg`" :srcset="srcSet(asset.src, 'jpeg', STAGE_WIDTHS)" sizes="72vw" alt="" />
```

- [ ] **Step 7: Verify nothing else references the deleted locals**

```bash
cd apps/studio && grep -rn "WIDTHS" resources/js/portal
```

Expected: only the two import lines and the `lib/images.ts` declarations. No `const WIDTHS` inside a `.vue`.

- [ ] **Step 8: Run lint and the full frontend suite**

```bash
cd apps/studio && npx vue-tsc --noEmit && npx vitest run
```

Expected: no type errors; 176 tests pass (173 before, 3 new).

- [ ] **Step 9: Commit**

```bash
git add apps/studio/resources/js/portal/lib/images.ts \
        apps/studio/resources/js/portal/lib/images.test.ts \
        apps/studio/resources/js/portal/pages/Gallery/Show.vue \
        apps/studio/resources/js/portal/components/GalleryLightbox.vue
git commit -m "refactor(portal): one width list, with a test"
```

---

### Task 2: The arrow keys during a comment

**Files:**
- Create: `apps/studio/resources/js/portal/lib/lightbox.ts`
- Create: `apps/studio/resources/js/portal/lib/lightbox.test.ts`
- Modify: `apps/studio/resources/js/portal/components/GalleryLightbox.vue:101-113` and `:254-256`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `type KeyIntent = 'close' | 'prev' | 'next' | null`, `keyIntent(key: string, typing: boolean): KeyIntent`, `stepIndex(index: number, delta: number, length: number): number`.

The bug this closes: `GalleryLightbox.vue:122` binds `onKey` to `window`, and `onKey` at `:108` never asks whether the comment field has focus. A client fixing a typo with the left arrow steps to the next picture; `sendComment()` reads `asset.value.key` at send time (`:89-92`), so the comment files under the wrong photo. `apps/gallery/src/lib/lightbox.ts:183` already guards this — the portal copy never got it.

- [ ] **Step 1: Write the failing test**

Create `apps/studio/resources/js/portal/lib/lightbox.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { keyIntent, stepIndex } from './lightbox'

describe('keyIntent', () => {
  it('closes on Escape even while the comment field has focus', () => {
    expect(keyIntent('Escape', true)).toBe('close')
  })

  it('steps on the arrows when nothing is being typed', () => {
    expect(keyIntent('ArrowLeft', false)).toBe('prev')
    expect(keyIntent('ArrowRight', false)).toBe('next')
  })

  it('leaves the arrows to the comment field while it has focus', () => {
    expect(keyIntent('ArrowLeft', true)).toBeNull()
    expect(keyIntent('ArrowRight', true)).toBeNull()
  })

  it('ignores every other key', () => {
    expect(keyIntent('a', false)).toBeNull()
    expect(keyIntent('Enter', false)).toBeNull()
  })
})

describe('stepIndex', () => {
  it('wraps past the last picture to the first', () => {
    expect(stepIndex(3, 1, 4)).toBe(0)
  })

  it('wraps before the first picture to the last', () => {
    expect(stepIndex(0, -1, 4)).toBe(3)
  })

  it('moves by one in between', () => {
    expect(stepIndex(1, 1, 4)).toBe(2)
    expect(stepIndex(1, -1, 4)).toBe(0)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd apps/studio && npx vitest run resources/js/portal/lib/lightbox.test.ts
```

Expected: FAIL, `Failed to resolve import "./lightbox"`.

- [ ] **Step 3: Write minimal implementation**

Create `apps/studio/resources/js/portal/lib/lightbox.ts`:

```ts
export type KeyIntent = 'close' | 'prev' | 'next' | null

/**
 * Escape always closes; the arrows belong to the comment field while it holds
 * focus. Without the split, moving the caret back over a typo steps to the
 * next picture, and sendComment() then files the text under that one.
 */
export function keyIntent(key: string, typing: boolean): KeyIntent {
  if (key === 'Escape') return 'close'
  if (typing) return null
  if (key === 'ArrowLeft') return 'prev'
  if (key === 'ArrowRight') return 'next'

  return null
}

/** Both ends wrap: the last picture's next is the first. */
export function stepIndex(index: number, delta: number, length: number): number {
  return (index + delta + length) % length
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd apps/studio && npx vitest run resources/js/portal/lib/lightbox.test.ts
```

Expected: PASS, 6 tests.

- [ ] **Step 5: Track focus on the comment field**

In `apps/studio/resources/js/portal/components/GalleryLightbox.vue`, add the import:

```ts
import { keyIntent, stepIndex } from '@/portal/lib/lightbox'
```

Next to `const draft = ref('')` at line 25, add:

```ts
const typing = ref(false)
```

At line 255, the `<Input>` reports its own focus rather than the handler querying the DOM — the component knows, and a `ref` is what the pure function can be handed:

```html
<Input
  v-model="draft"
  type="text"
  :placeholder="$t('gallery.commentPlaceholder')"
  @focus="typing = true"
  @blur="typing = false"
/>
```

- [ ] **Step 6: Route both handlers through the module**

Replace `step` and `onKey` at lines 101-113:

```ts
function step(by: number) {
  if (open.value === null) return

  open.value = stepIndex(open.value, by, props.assets.length)
  listsOpen.value = false
}

function onKey(event: KeyboardEvent) {
  if (open.value === null) return

  const intent = keyIntent(event.key, typing.value)

  if (intent === 'close') open.value = null
  if (intent === 'prev') step(-1)
  if (intent === 'next') step(1)
}
```

- [ ] **Step 7: Run lint and the full frontend suite**

```bash
cd apps/studio && npx vue-tsc --noEmit && npx vitest run
```

Expected: no type errors; 182 tests pass.

- [ ] **Step 8: Check the fix in the browser**

Start the portal surface, open a gallery as a client, open a picture, click into the comment field, type `hallo`, press the left arrow.

Expected: the caret moves inside the field, the picture does not change. Pressing Escape from inside the field still closes the lightbox.

- [ ] **Step 9: Commit**

```bash
git add apps/studio/resources/js/portal/lib/lightbox.ts \
        apps/studio/resources/js/portal/lib/lightbox.test.ts \
        apps/studio/resources/js/portal/components/GalleryLightbox.vue
git commit -m "fix(portal): arrows stay in the comment field"
```

---

### Task 3: The Astro viewer's own copy

**Files:**
- Modify: `apps/gallery/src/lib/lightbox.ts:22-24`, `:124`, `:178-186`
- Create: `apps/gallery/src/lib/lightbox.test.ts`

**Interfaces:**
- Consumes: nothing. The two apps share no code; these are separate declarations with the same shape as Task 2's.
- Produces: `keyIntent(key: string, typing: boolean): KeyIntent`, `stepIndex(index: number, delta: number, length: number): number`, both exported from `lightbox.ts` alongside `initLightbox`.

`lightbox.ts` today exports only `initLightbox`, a closure of DOM wiring that a node-environment test cannot call. Hoisting the two pure decisions to module scope makes them importable; nothing at module top level touches `document`, so importing the file under `environment: 'node'` stays safe.

- [ ] **Step 1: Write the failing test**

Create `apps/gallery/src/lib/lightbox.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { keyIntent, stepIndex } from './lightbox'

describe('keyIntent', () => {
  it('closes on Escape even while the comment field has focus', () => {
    expect(keyIntent('Escape', true)).toBe('close')
  })

  it('steps on the arrows when the guest is not typing', () => {
    expect(keyIntent('ArrowLeft', false)).toBe('prev')
    expect(keyIntent('ArrowRight', false)).toBe('next')
  })

  it('leaves the arrows to the comment field while it has focus', () => {
    expect(keyIntent('ArrowLeft', true)).toBeNull()
    expect(keyIntent('ArrowRight', true)).toBeNull()
  })

  it('ignores every other key', () => {
    expect(keyIntent('a', false)).toBeNull()
  })
})

describe('stepIndex', () => {
  it('wraps in both directions', () => {
    expect(stepIndex(3, 1, 4)).toBe(0)
    expect(stepIndex(0, -1, 4)).toBe(3)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd apps/gallery && npx vitest run src/lib/lightbox.test.ts
```

Expected: FAIL. The module resolves but the names do not: `does not provide an export named 'keyIntent'`.

- [ ] **Step 3: Hoist the two decisions to module scope**

In `apps/gallery/src/lib/lightbox.ts`, directly below `const STAGE_WIDTH = 1600` at line 22:

```ts
export type KeyIntent = 'close' | 'prev' | 'next' | null

/**
 * The comment field owns the arrows while it holds focus — a guest moving the
 * caret back over a typo would otherwise flip to the next picture. Escape
 * closes regardless.
 */
export function keyIntent(key: string, typing: boolean): KeyIntent {
  if (key === 'Escape') return 'close'
  if (typing) return null
  if (key === 'ArrowLeft') return 'prev'
  if (key === 'ArrowRight') return 'next'

  return null
}

/** Both ends wrap: the last picture's next is the first. */
export function stepIndex(index: number, delta: number, length: number): number {
  return (index + delta + length) % length
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd apps/gallery && npx vitest run src/lib/lightbox.test.ts
```

Expected: PASS, 5 tests.

- [ ] **Step 5: Route the closure through them**

Replace line 124:

```ts
  const step = (delta: number): void => open(stepIndex(index, delta, assets.length))
```

Replace the keydown listener at lines 178-186:

```ts
  document.addEventListener('keydown', (event) => {
    if (!root.classList.contains('open')) return

    const intent = keyIntent(event.key, document.activeElement === commentInput)

    if (intent === 'close') close()
    if (intent === 'prev') step(-1)
    if (intent === 'next') step(1)
  })
```

The old block's comment moves onto `keyIntent` in Step 3; it is not duplicated here.

- [ ] **Step 6: Run lint and the full gallery suite**

```bash
cd apps/gallery && npx astro check && npx vitest run
```

Expected: no errors; 48 tests pass (43 before, 5 new).

- [ ] **Step 7: Commit**

```bash
git add apps/gallery/src/lib/lightbox.ts apps/gallery/src/lib/lightbox.test.ts
git commit -m "test(gallery): cover the lightbox's two decisions"
```

---

### Task 4: Prove nothing moved

**Files:**
- Modify: none expected.

**Interfaces:**
- Consumes: Tasks 1-3 complete.
- Produces: nothing.

Tasks 1-3 change only which module a value comes from. `Show.vue` and `GalleryLightbox.vue` render the same widths in the same order, so every Playwright frame must match byte for byte. A diff here means a subset was mistyped.

- [ ] **Step 1: Run the workspace lint and test scripts as CI does**

```bash
pnpm lint && pnpm test
```

Expected: both workspaces pass; 182 + 48 tests.

- [ ] **Step 2: Run the PHP suite**

```bash
docker compose exec -e APP_SURFACE=admin -T api ./vendor/bin/pest
```

Expected: 1967 passed, 1 skipped. This plan touches no PHP; a failure here is unrelated and must be reported rather than fixed inside this task.

- [ ] **Step 3: Refresh the e2e baselines before comparing**

Baselines age out because `E2eSeeder` seeds relative to `now()`. Re-record on `dev` first, so the comparison measures this branch and not the calendar:

```bash
cd apps/studio && git stash && npx playwright test --update-snapshots && git stash pop
```

- [ ] **Step 4: Run the visual specs**

```bash
cd apps/studio && npx playwright test visual.spec.ts visual-portal.spec.ts
```

Expected: PASS, 90 frames unchanged. A portal frame that differs means `GRID_WIDTHS` or `STAGE_WIDTHS` no longer matches what the screen offered before — compare against `Show.vue:169` and `GalleryLightbox.vue:125` in `git show dev:`.

- [ ] **Step 5: Commit any refreshed baselines separately**

```bash
git add apps/studio/tests/e2e/visual.spec.ts-snapshots apps/studio/tests/e2e/visual-portal.spec.ts-snapshots
git commit -m "test(e2e): refresh aged baselines"
```

Skip this step if `git status` shows no snapshot changes.

---

## Not in this plan

- **`isCurrent` from spec §3.3.** The stale-comment guard reduces to `requested === current`. A test for `===` is a test that cannot fail for the reason it claims to; the guard stays inline with its G-12 comment. Revisit if `loadComments` ever grows a second condition.
- **`apps/gallery` e2e.** Spec §5 names the ceiling: a second `webServer` in `playwright.config.ts` for Astro on 4321, plus an `E2eSeeder` fixture for a guest gate. Separate plan.
- **The remaining 212 untested `.vue`.** Deliberate; see spec "Nicht Ziel".
