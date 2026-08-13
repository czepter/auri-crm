# CRM Visual System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Recolour every surface onto a neutral grey base where the warm accent means *action* and nothing else, add the four components the reference screen introduces, and give the project screen an activity feed backed by `audit_logs`.

**Architecture:** The change is mostly one stylesheet. `mockups/_shared/tokens.css` is the source of truth and is edited first; `apps/studio/resources/css/tokens.css` is then ported to match. Steps 1–2 leave the app fully recoloured with no Vue file touched. The activity feed needs per-project events, so `audit_logs` gains a nullable `project_id`, `AuditLogger::record()` gains a matching `?int` parameter, and roughly twenty of its eighty call sites start passing one. A read-only presenter in the Crm module turns those rows into a whitelisted payload.

**Tech Stack:** Laravel 12 + Inertia 2 + Vue 3 (`<script setup lang="ts">`), plain CSS custom properties (no Tailwind translation), Pest (PHP), Playwright (e2e).

**Spec:** [`docs/superpowers/specs/2026-08-13-crm-visual-system-design.md`](../specs/2026-08-13-crm-visual-system-design.md)
**Mockup:** [`docs/superpowers/specs/2026-08-13-crm-visual-system-mockup.html`](../specs/2026-08-13-crm-visual-system-mockup.html) — open it in a browser before starting; it is the visual target.

## Global Constraints

- **Commands run through Docker.** `pnpm php` is `docker compose run --rm php`. Tests: `pnpm php ./vendor/bin/pest`, `pnpm --filter @studio/app test` (vitest), `pnpm --filter @studio/app exec playwright test`, `pnpm lint` (vue-tsc).
- **`mockups/_shared/` is the source of truth for CSS.** Every token or component rule is written there first and then ported verbatim into `apps/studio/resources/css/`. Never the other way round.
- **No Tailwind translation.** The tokens stay CSS custom properties. Do not convert any rule in this plan into utility classes.
- **The accent is action-only.** After Task 2, `var(--accent)` and `var(--accent-bg)` may only appear on something the user can press or focus. Any state, badge, or warning uses `--ok`, `--due`, or `--late`.
- **Exact palette values, copied from the spec:**
  `--bg:#FFFFFF` `--ink:#191919` `--muted:#6B6B6B` `--faint:#A3A3A3` `--hover:#F5F5F4` `--line:#E9E9E7` `--line-hover:#DCDCD9` `--accent:#B44E22` `--accent-bg:#FBF0EA` `--ok:#3F6B4A` `--ok-bg:#EDF3EE` `--due:#8A6212` `--due-bg:#FAF3E4` `--late:#B3261E` `--late-bg:#FCF0EF`
- **Gallery dark overrides, copied from the spec:**
  `--ok:#7FB98C` `--ok-bg:#16211A` `--due:#D9B25C` `--due-bg:#241D0E` `--late:#E88379` `--late-bg:#2A1512`
- **No integer identifier in any Inertia prop, JSON body, URL, email or filename.** `NoInternalIdsTest` enforces it. `project_id` is an internal column and never leaves the server.
- **German UI copy is terse and native.** Activity labels are short noun phrases — "Angebot gesendet", never a sentence. No explanatory hint paragraphs.
- **`audit_logs` is append-only.** The model throws on `updating` and `deleting`. Nothing in this plan may relax that.
- All paths below are relative to `apps/studio/` unless they start with `docs/` or `mockups/`.

---

## File Structure

**Create:**
- `database/migrations/2026_08_13_000100_add_project_id_to_audit_logs_table.php` — the nullable FK and its index.
- `database/migrations/2026_08_13_000200_backfill_audit_log_project_ids.php` — one-time assignment for historical rows that carry the project in `context`.
- `app/Crm/Support/ProjectActivity.php` — turns a project's audit rows into a whitelisted, labelled, newest-first array. The Crm module owns the timeline.
- `lang/de/activity.php` — event key → German label. The map that decides which events are shown at all.
- `tests/Feature/Crm/ProjectActivityTest.php` — the presenter's own suite.
- `tests/e2e/project-detail.spec.ts` — the screen renders tiles, action rail and feed.

**Modify:**
- `mockups/_shared/tokens.css` — palette, accent reassignment, gallery overrides.
- `mockups/_shared/admin-extra.css` — the four new components, accent reassignment.
- `apps/studio/resources/css/tokens.css` — ported copy of the above.
- `apps/studio/resources/css/admin-extra.css` — ported copy of the above.
- `apps/studio/resources/css/app.css:39` — `--color-destructive`.
- `apps/studio/resources/css/forms.css:35`, `gallery.css:56,58`, `document-editor.css:27,48` — accent reassignment.
- `app/Support/AuditLogger.php` — the new `?int $projectId` parameter.
- Twenty-four call sites across nineteen files, listed in Task 6.
- `app/Crm/Http/Controllers/ProjectController.php` — the `activity` prop.
- `resources/js/admin/pages/Projects/Show.vue` — tiles, action rail, perforation, feed.
- `tests/Feature/AuditLogTest.php` — coverage for the new parameter.
- `tests/Feature/NoInternalIdsTest.php` — nothing to change, but it must stay green; the activity payload is the thing most likely to break it.

**Delete:**
- `.bklist` / `.bkrow` rules from `mockups/_shared/tokens.css`. They have no Vue consumer; `.tiles` replaces them.

---

## Task 1: The neutral base palette

**Files:**
- Modify: `mockups/_shared/tokens.css:17-45` (the `:root` block)
- Modify: `apps/studio/resources/css/tokens.css:17-45` (the ported `:root` block)

**Interfaces:**
- Consumes: nothing.
- Produces: the fifteen custom properties every later task reads. `--due`, `--due-bg`, `--late` and `--late-bg` are new; every other name already exists and only changes value.

- [ ] **Step 1: Rewrite the `:root` palette in the mockups**

In `mockups/_shared/tokens.css`, replace the colour block inside `:root` (the lines from `--bg:` through `--ok-bg:`) with:

```css
  /* neutral ground. The warm accent is action and nothing else — a state
     that borrows it reads as a button, which is the confusion this palette
     exists to end. Status has its own three. */
  --bg:#FFFFFF;
  --ink:#191919;
  --muted:#6B6B6B;
  --faint:#A3A3A3;
  --hover:#F5F5F4;      /* fill AND hover — the reference's cards and its
                           hover states are the same grey; two tokens would
                           invite them to drift */
  --line:#E9E9E7;
  --line-hover:#DCDCD9;

  --accent:#B44E22;     --accent-bg:#FBF0EA;   /* action only */

  --ok:#3F6B4A;         --ok-bg:#EDF3EE;
  --due:#8A6212;        --due-bg:#FAF3E4;
  --late:#B3261E;       --late-bg:#FCF0EF;
```

Leave the spacing scale (`--s1`…`--s7`), `--r`, `--r-sm` and `--font` exactly as they are.

- [ ] **Step 2: Update the `[data-theme="portal"]` hover**

Still in `mockups/_shared/tokens.css`, the portal block overrides `--hover:#F8F5F3` — a warm value. Change that one line to:

```css
  --hover:#FAFAF9;
```

Everything else in the portal block (the spacing overrides and `--r:10px`) is unchanged.

- [ ] **Step 3: Port both changes into the app**

Apply the identical edits from Steps 1 and 2 to `apps/studio/resources/css/tokens.css`. The two files must be byte-identical in these blocks — that is the rule its header comment states.

- [ ] **Step 4: Verify the port did not drift**

Run:

```bash
diff <(sed -n '/^:root {/,/^}/p' mockups/_shared/tokens.css) \
     <(sed -n '/^:root {/,/^}/p' apps/studio/resources/css/tokens.css)
```

Expected: no output.

- [ ] **Step 5: Look at a screen**

Run `pnpm dev`, open `/auftraege`, and confirm the page is grey rather than warm and that nothing has become unreadable. Orange still appears on buttons — reassigning the rest is Task 2.

- [ ] **Step 6: Commit**

```bash
git add mockups/_shared/tokens.css apps/studio/resources/css/tokens.css
git commit -m "style(tokens): move the base palette to neutral grey"
```

---

## Task 2: Reassign every accent site

**Files:**
- Modify: `mockups/_shared/tokens.css` and `apps/studio/resources/css/tokens.css` — lines 151, 163, 215, 216, 236, 243, 258, 293, 301
- Modify: `mockups/_shared/admin-extra.css` and `apps/studio/resources/css/admin-extra.css` — lines 21, 37, 53, 95, 114, 126, 179
- Modify: `apps/studio/resources/css/app.css:39`
- Modify: `apps/studio/resources/css/forms.css:35`
- Modify: `apps/studio/resources/css/gallery.css:56,58`
- Modify: `apps/studio/resources/css/document-editor.css:27,48`

**Interfaces:**
- Consumes: `--ok`, `--due`, `--late` and their `-bg` pairs from Task 1.
- Produces: `.paystatus.late` and the outlined `.taudience.client`, both rendered by Task 11; and `.stage .now.late`, which ships as a rule with no producer. `DeriveProjectPosition` carries no overdue concept, and deciding when a board position counts as overdue is a product question this plan does not answer. The rule is written so the vocabulary is complete; wiring it is a later story.

This task is mechanical. Work down the table; every row is a colour swap, not a restructure. Do the mockup file and the app file together for each rule so the two never sit in different states.

- [ ] **Step 1: The alarms become `--late`**

```css
/* tokens.css:151 */
.age.warn { color:var(--late); font-weight:500; /* …keep the existing background-image line, but
                                                    change its stroke to %23B3261E */ }

/* tokens.css:163 */
.flag { /* …unchanged layout… */ background:var(--late-bg); color:var(--late);
        /* the inline SVG's stroke becomes %23B3261E */ }

/* admin-extra.css:21 */
.bar > i.warn { background:var(--late); }

/* admin-extra.css:37 */
.chip.fail { background:var(--late-bg); color:var(--late); }

/* admin-extra.css:95 */
.kpi b i { font-style:normal; color:var(--late); }

/* admin-extra.css:114 */
.dr .what i { font-style:normal; color:var(--late); font-weight:500; }

/* admin-extra.css:126 */
.ready i.no { color:var(--late); }

/* forms.css:35 */
.err { font:400 11.5px/1.45 var(--font); color:var(--late); }

/* gallery.css:56,58 */
[data-theme="gallery"] .err { background:var(--late-bg); border:1px solid #40251A; }
[data-theme="gallery"] .err b { display:block; color:var(--late); font:600 13px/1.5 var(--font); margin-bottom:4px; }
```

The two inline `background-image` data-URIs in `.age.warn` and `.flag` hard-code `%23B44E22`. Both become `%23B3261E`. Miss these and the icon stays orange beside red text.

- [ ] **Step 2: The pending states become `--due`**

```css
/* tokens.css:243 */
.chip.now { background:var(--due-bg); color:var(--due); }

/* tokens.css:293 — and the new sibling below it */
.paystatus.due  { background:var(--due-bg); color:var(--due); }
.paystatus.late { background:var(--late-bg); color:var(--late); }

/* document-editor.css:27 */
.doc-live.stale { color: var(--due); }
```

- [ ] **Step 3: The good states become `--ok`**

```css
/* tokens.css:215-216 — and the new .late modifier */
.stage .now { display:inline-flex; align-items:center; gap:7px; padding:6px 11px;
              border-radius:var(--r-sm); background:var(--ok-bg); color:var(--ok);
              font:600 12px/1 var(--font); }
.stage .now .dot { width:7px; height:7px; border-radius:50%; background:var(--ok); flex:none; }
.stage .now.late { background:var(--late-bg); color:var(--late); }
.stage .now.late .dot { background:var(--late); }

/* tokens.css:236 */
.bkrow i { font-style:normal; color:var(--ok); font-weight:600; }
```

- [ ] **Step 4: The non-states go neutral**

```css
/* tokens.css:258 — "primary shoot" is a label, not a state */
.sname .prim { display:inline-block; margin-left:6px; font:600 9px/1.5 var(--font);
               letter-spacing:.08em; text-transform:uppercase; color:var(--ink);
               vertical-align:1px; }

/* tokens.css:301 — a category. Outlined against the filled internal chip, so the
   two stay distinct in greyscale rather than relying on a colour neither has. */
.taudience.client   { background:transparent; border:1px solid var(--line-hover); color:var(--muted); }
.taudience.internal { background:var(--hover); color:var(--muted); }

/* admin-extra.css:53 — "repeat client" is information */
.rp { font:500 10px/1.5 var(--font); color:var(--muted); background:var(--hover);
      padding:2px 7px; border-radius:var(--r-sm); margin-left:8px; }

/* admin-extra.css:179 — "required" is a field property */
.qreq { font:600 10px/1.5 var(--font); letter-spacing:.05em; text-transform:uppercase;
        color:var(--muted); background:var(--hover); padding:2px 7px; border-radius:var(--r-sm); }

/* document-editor.css:48 — a merge field is dynamic content, like inline code */
.doc-merge-field { background: var(--hover); color: var(--ink); font-weight: 500; }
```

The selector on `document-editor.css:48` may differ from `.doc-merge-field` — use whatever selector is already on that line and change only its `background` and `color` declarations.

- [ ] **Step 5: Destructive stops sharing a colour with primary**

```css
/* app.css:39 */
  --color-destructive: var(--late);
```

`--color-ring` on line 43 stays `var(--accent)`. A focus ring is interaction.

- [ ] **Step 6: Verify no state still borrows the accent**

Run:

```bash
grep -n 'accent' apps/studio/resources/css/*.css
```

Expected: hits at exactly these places, and nowhere else — the `--accent` / `--accent-bg`
definitions in `tokens.css`'s `:root` and its `[data-theme="gallery"]` block,
`document-editor.css:83` (`.doc-tool-input:focus`), `document-editor.css:100` (`.doc-callout`),
`app.css:43` (`--color-ring`), `portal.css:33` (`.pmark i`), `portal.css:110` (the consent
checkbox). Every one is something the user presses or focuses. Any other hit is a miss —
check it against the reassignment table above rather than assuming it is fine.

- [ ] **Step 7: Verify the mockups and the app still match**

```bash
diff mockups/_shared/tokens.css apps/studio/resources/css/tokens.css
```

Expected: only the differences the app's own header comment documents (the dropped `.dummybar` rule and the app's added `margin:0` / `display:block` notes). No colour differences.

- [ ] **Step 8: Commit**

```bash
git add mockups/_shared apps/studio/resources/css
git commit -m "style(tokens): give status its own colours, leave the accent to actions"
```

---

## Task 3: Gallery dark theme gets the semantic set

**Files:**
- Modify: `mockups/_shared/tokens.css` (the `[data-theme="gallery"]` block)
- Modify: `apps/studio/resources/css/tokens.css` (same block)

**Interfaces:**
- Consumes: the six semantic names from Task 1.
- Produces: nothing new — it overrides existing names for one theme.

- [ ] **Step 1: Add the dark variants**

The gallery block currently overrides only the neutrals and the accent, so it inherits `--ok-bg:#EDF3EE` from `:root` — a light green panel on a `#0E0C0B` page. Append to the `[data-theme="gallery"]` block in both files:

```css
  /* the semantic three, re-lit for a dark ground. Inheriting :root's here
     would put a near-white panel on a near-black page — already wrong for
     --ok before this change, and wrong three times over with --due and
     --late added. */
  --ok:#7FB98C;    --ok-bg:#16211A;
  --due:#D9B25C;   --due-bg:#241D0E;
  --late:#E88379;  --late-bg:#2A1512;
```

- [ ] **Step 2: Verify contrast on the real screen**

Run `pnpm dev` and open a gallery gate screen with a validation error (submit the email gate empty). The error text must be legible against the dark panel.

- [ ] **Step 3: Commit**

```bash
git add mockups/_shared/tokens.css apps/studio/resources/css/tokens.css
git commit -m "style(gallery): re-light the semantic colours for the dark theme"
```

---

## Task 4: The four new components

**Files:**
- Modify: `mockups/_shared/admin-extra.css` (append a new section)
- Modify: `apps/studio/resources/css/admin-extra.css` (ported copy)
- Delete: the `.bklist` / `.bkrow` rules in `mockups/_shared/tokens.css`

**Interfaces:**
- Consumes: the palette from Task 1.
- Produces: `.tiles`, `.fact`, `.fact.ghost`, `.rail`, `.railrow`, `.railrow.danger`, `.perf`, `.feed`, `.ev`, `.ev.pending` — the class names Task 10 and Task 11 write into templates.

- [ ] **Step 1: Write the components into the mockups**

Append to `mockups/_shared/admin-extra.css`:

```css
/* ---------- the reference's four shapes ----------
   Filled tiles, an action rail, a perforation and an activity feed. All four
   are layout over the existing tokens — no new colour, no asset, no script. */

/* .tiles — replaces the .kv / .kvrow fact list. A fact is a tile, not a row:
   the label sits over the value instead of beside it, which is what lets three
   of them share a line without the values colliding. */
.tiles { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:var(--s3); }
.fact { background:var(--hover); border-radius:var(--r); padding:14px var(--s4) var(--s4); }
.fact .l { font:600 9.5px/1 var(--font); letter-spacing:.11em; text-transform:uppercase; color:var(--faint); }
.fact .v { font:500 16px/1.2 var(--font); letter-spacing:-.02em; margin-top:9px;
           font-variant-numeric:tabular-nums; }
.fact .s { font:400 12.5px/1.3 var(--font); color:var(--muted); margin-top:var(--s1);
           font-variant-numeric:tabular-nums; }
/* the empty state keeps the grid rhythm that loose grey text broke */
.fact.ghost { background:transparent; border:1px dashed var(--line); }
.fact.ghost .v { color:var(--faint); font-weight:400; }

/* .rail — one vertical list of verbs, replacing the ghost buttons that were
   scattered through .cardhead. Not a button variant: this is a layout for a
   list of links, the way .nav already is in the sidebar. */
.rail { display:flex; flex-direction:column; gap:2px; margin:-8px 0 0 -10px; }
.railrow { display:flex; align-items:center; gap:12px; padding:8px var(--s3); border-radius:7px;
       color:var(--ink); text-decoration:none; font:400 14px/1 var(--font); cursor:pointer;
       background:none; border:none; width:100%; text-align:left;
       transition:background .12s ease-out, color .12s ease-out; }
.railrow:hover { background:var(--hover); }
.railrow svg { width:17px; height:17px; color:var(--muted); }
/* destructive on hover only — an option should not shout while it sits unused */
.railrow.danger:hover, .railrow.danger:hover svg { color:var(--late); }

/* .perf — the reference's tear line. A repeating radial gradient, so it
   recolours with --line like every other rule here and needs no asset. */
.perf { height:8px; margin:var(--s5) 0; opacity:.75;
  background:radial-gradient(circle at 3px 6px, transparent 2.6px,
             var(--line) 2.7px 3.2px, transparent 3.3px) repeat-x;
  background-size:8px 8px; }

/* .feed — the same dot-and-rail technique .tlrail already uses for the
   shooting day, so the two timelines read as one family. */
.feedlab { font:600 9.5px/1 var(--font); letter-spacing:.11em; text-transform:uppercase;
           color:var(--faint); margin-bottom:14px; }
.feed { display:flex; flex-direction:column; }
.ev { display:grid; grid-template-columns:9px 1fr; gap:12px; position:relative; padding-bottom:var(--s4); }
.ev:last-child { padding-bottom:0; }
.ev .dot { width:7px; height:7px; border-radius:50%; background:var(--faint); margin-top:5px;
           position:relative; z-index:1; }
.ev::before { content:''; position:absolute; left:3px; top:12px; bottom:0; width:1px; background:var(--line); }
.ev:last-child::before { display:none; }
.ev .t { font:400 13.5px/1.3 var(--font); }
.ev .w { font:400 12px/1.3 var(--font); color:var(--faint); margin-top:3px;
         font-variant-numeric:tabular-nums; }
.ev.pending .dot { background:var(--line); }
.ev.pending .t { color:var(--faint); }
```

- [ ] **Step 2: Delete the grid that never shipped**

Remove the `.bklist` and `.bkrow` rules from `mockups/_shared/tokens.css` (the block under the comment `booking facts: label over value, two columns, no tiles`, including that comment). They have no Vue consumer — the app built `.kv` instead, and the two have been drifting since. `.tiles` replaces both.

- [ ] **Step 3: Verify nothing referenced them**

```bash
grep -rn 'bklist\|bkrow' mockups apps/studio/resources
```

Expected: hits only in `mockups/admin/*.html` clickdummy screens. Update those screens to use `.tiles` markup from the mockup in `docs/superpowers/specs/2026-08-13-crm-visual-system-mockup.html`, then re-run until there is no output.

- [ ] **Step 4: Port into the app**

Append the same component block to `apps/studio/resources/css/admin-extra.css`, and remove the `.bklist` / `.bkrow` rules from `apps/studio/resources/css/tokens.css` if the port ever carried them.

- [ ] **Step 5: Commit**

```bash
git add mockups apps/studio/resources/css
git commit -m "style(admin): add tiles, the action rail, the perforation and the feed"
```

---

## Task 5: `audit_logs` learns its project

**Files:**
- Create: `database/migrations/2026_08_13_000100_add_project_id_to_audit_logs_table.php`
- Modify: `app/Support/AuditLogger.php:17-31`
- Test: `tests/Feature/AuditLogTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `AuditLogger::record(string $event, ?User $actor = null, array $context = [], ?string $subject = null, ?int $projectId = null): AuditLog` — the signature Task 6 calls, and the `audit_logs.project_id` column Tasks 7 and 8 read.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/AuditLogTest.php`:

```php
it('records the project an event belongs to', function () {
    $project = Project::factory()->create();

    $log = AuditLogger::record('quote.sent', null, [], 'q_123', $project->id);

    expect($log->project_id)->toBe($project->id);
});

it('leaves project_id null for events that belong to no project', function () {
    $log = AuditLogger::record('staff.login.succeeded', staffMember());

    expect($log->project_id)->toBeNull();
});
```

Add `use App\Crm\Models\Project;` to the file's imports.

- [ ] **Step 2: Run it and watch it fail**

Run: `pnpm php ./vendor/bin/pest tests/Feature/AuditLogTest.php`
Expected: FAIL — `Unknown column 'project_id'`, or `Too few arguments`.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_13_000100_add_project_id_to_audit_logs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // nullOnDelete, not cascade: the model throws on deleting, so a
            // cascade would ask the database to remove rows the application
            // refuses to remove.
            $table->foreignId('project_id')->nullable()->after('actor_id')
                ->constrained('projects')->nullOnDelete();

            $table->index(['tenant_id', 'project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'project_id', 'created_at']);
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
```

- [ ] **Step 4: Add the parameter**

In `app/Support/AuditLogger.php`, extend the signature and the insert:

```php
    public static function record(
        string $event,
        ?User $actor = null,
        array $context = [],
        ?string $subject = null,
        // ?int rather than ?Project: App\Support is cross-cutting and
        // AuditLog belongs to no module, so importing a Crm model here would
        // hang the shared layer off a domain one.
        ?int $projectId = null,
    ): AuditLog {
        return AuditLog::create([
            'event' => $event,
            'actor_id' => $actor?->id,
            'project_id' => $projectId,
            'subject' => $subject,
            'context' => self::sanitize($context),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
    }
```

- [ ] **Step 5: Run the tests**

Run: `pnpm php ./vendor/bin/pest tests/Feature/AuditLogTest.php`
Expected: PASS, including the four append-only tests that were already there.

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Support/AuditLogger.php tests/Feature/AuditLogTest.php
git commit -m "feat(audit): let an audit entry name the project it belongs to"
```

---

## Task 6: Nineteen files, twenty-four call sites, start passing a project

**Files:**
- Modify: `app/Crm/Actions/CaptureInquiry.php:82`, `BookProject.php:69`, `AdvanceLeadStage.php:42,68`, `AdvanceShootStatus.php:46,72`, `DesignatePrimaryShoot.php:40`
- Modify: `app/Crm/Listeners/BookProjectOnContractAndDeposit.php:97`
- Modify: `app/Crm/Http/Controllers/ProjectController.php:162`, `TaskController.php:54`, `ShotListItemController.php:39`, `FormInstanceController.php:51,86,112`
- Modify: `app/Quotes/Actions/SendQuote.php:59`, `AcceptQuote.php:54`, `ReviseQuote.php:63`, `app/Quotes/Http/Controllers/QuoteController.php:49,106`
- Modify: `app/Contracts/Actions/SignContract.php:57`, `app/Contracts/Http/Controllers/ContractController.php:70`
- Modify: `app/Payments/Actions/CreatePaymentPlan.php:60`, `UpdatePaymentPlan.php:42`, `MarkInstallmentPaid.php:49`
- Test: `tests/Feature/Crm/ProjectAuditTrailTest.php` (create)

**Interfaces:**
- Consumes: `AuditLogger::record(…, ?int $projectId)` from Task 5.
- Produces: `audit_logs` rows carrying `project_id` for the events Task 8 renders.

Every call site uses named arguments, so each edit is one added line: `projectId: …`. The relation chains, all verified against the models:

| Action | Expression |
|---|---|
| Quote actions and controller | `$quote->project_id` |
| Contract actions and controller | `$contract->project_id` |
| Shoot actions | `$shoot->project_id` |
| Lead actions | `$lead->project_id` |
| Payment plan actions | `$plan->job->project_id` |
| `MarkInstallmentPaid` | `$installment->plan->job->project_id` |
| Project, task, shot-list, form-instance controllers | `$project->id` |

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Crm/ProjectAuditTrailTest.php`:

```php
<?php

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Models\Shoot;
use App\Models\AuditLog;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentInstallment;
use App\Quotes\Actions\SendQuote;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('files a shoot status change under its project', function () {
    $shoot = Shoot::factory()->create();

    app(AdvanceShootStatus::class)($shoot, actor: staffMember());

    expect(AuditLog::where('event', 'shoot.status_changed')->sole()->project_id)
        ->toBe($shoot->project_id);
});

it('files a sent quote under its project', function () {
    $quote = Quote::factory()->create();

    app(SendQuote::class)($quote);

    expect(AuditLog::where('event', 'quote.sent')->sole()->project_id)
        ->toBe($quote->project_id);
});

it('files a paid installment under the project three relations away', function () {
    $installment = PaymentInstallment::factory()->create();

    app(MarkInstallmentPaid::class)(
        $installment, SettledVia::Transfer, '2026-06-21', null, staffMember()
    );

    expect(AuditLog::where('event', 'payment_installment.paid')->sole()->project_id)
        ->toBe($installment->plan->job->project_id);
});
```

If a factory or an action's argument list differs from the above, adjust the call — but never the assertion. The assertion is the requirement.

- [ ] **Step 2: Run it and watch it fail**

Run: `pnpm php ./vendor/bin/pest tests/Feature/Crm/ProjectAuditTrailTest.php`
Expected: FAIL — three failures, each `Failed asserting that null matches expected <int>`.

- [ ] **Step 3: Add the argument at all twenty-four call lines**

For each line in the Files list, add one named argument to the existing `AuditLogger::record(` call, using the expression from the table above. For example, in `app/Payments/Actions/MarkInstallmentPaid.php`:

```php
            AuditLogger::record(
                event: 'payment_installment.paid',
                actor: $actor,
                context: ['method' => $method->value, 'paid_on' => $paidOn, 'reference' => $reference],
                subject: $installment->public_id,
                projectId: $installment->plan->job->project_id,
            );
```

`MarkInstallmentPaid` reads three relations deep inside a transaction. Load them once before `DB::transaction(...)` opens:

```php
        $installment->loadMissing('plan.job');
```

- [ ] **Step 4: Run the tests**

Run: `pnpm php ./vendor/bin/pest tests/Feature/Crm/ProjectAuditTrailTest.php`
Expected: PASS.

- [ ] **Step 5: Run the whole suite**

Run: `pnpm php ./vendor/bin/pest`
Expected: PASS. Nothing else should move — the parameter is optional and every other call site is untouched.

- [ ] **Step 6: Confirm no unrelated module gained a project**

```bash
grep -rln 'projectId:' apps/studio/app | sort
```

Expected: the nineteen files in this task's Files list, plus roughly eight pre-existing
hits on DTOs that happen to carry a `projectId:` property (`TaskRowData`, `NextShootData`,
the `Integration` read models and so on). Those are named arguments to constructors, not
to `AuditLogger`; check the surrounding line before counting one as a miss. A hit under
`app/Http/`, `app/Settings/`, `app/Catalog/`, `app/Portal/` or `app/Documents/` that *is*
an `AuditLogger::record()` call means an event belonging to no project has been given one,
and it will surface in some project's feed.

`app/Notifications/NotificationService.php` was in this list when the plan was written and
has been removed. Its audit call sits in `auditSuppression(mixed $notifiable, Notification
$notification, string $channel)`, which has no project in scope and, by its own class
docblock, is deliberately domain-agnostic — it is the fan-out choke point every notification
leaves through, and it knows nothing about Crm. Deriving a project there would mean either
importing domain models into that service, the same layering violation `?int $projectId`
exists to avoid, or a duck-typed guess. The event it records is `notification.suppressed`,
delivery infrastructure rather than project history, and `lang/de/activity.php` in Task 8
has no label for it — so `ProjectActivity` would have dropped it anyway. The plan was
internally inconsistent: it told this task to tag the event and told Task 8 to discard it.

- [ ] **Step 7: Commit**

```bash
git add app tests/Feature/Crm/ProjectAuditTrailTest.php
git commit -m "feat(audit): file project events under their project"
```

---

## Task 7: Backfill the historical rows that can be placed

**Files:**
- Create: `database/migrations/2026_08_13_000200_backfill_audit_log_project_ids.php`
- Test: `tests/Feature/AuditLogBackfillTest.php` (create)

**Interfaces:**
- Consumes: the `project_id` column from Task 5.
- Produces: nothing new — it only fills existing rows.

`AcceptQuote` and `SignContract` already write `context: ['project' => $project->public_id]`. Those rows can be placed exactly. Every other historical row stays null: inferring a project from `subject` alone would guess, and a wrong entry in an append-only trail cannot be corrected.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AuditLogBackfillTest.php`:

```php
<?php

use App\Crm\Models\Project;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('places a historical row that names its project in context', function () {
    $project = Project::factory()->create();

    $id = DB::table('audit_logs')->insertGetId([
        'tenant_id' => config('tenancy.tenant_id'),
        'public_id' => 'al_backfill_one',
        'event' => 'quote.accepted',
        'context' => json_encode(['project' => $project->public_id]),
        'created_at' => now(),
    ]);

    (require database_path('migrations/2026_08_13_000200_backfill_audit_log_project_ids.php'))->up();

    expect(AuditLog::find($id)->project_id)->toBe($project->id);
});

it('leaves a historical row with no project in context alone', function () {
    $id = DB::table('audit_logs')->insertGetId([
        'tenant_id' => config('tenancy.tenant_id'),
        'public_id' => 'al_backfill_two',
        'event' => 'staff.login.succeeded',
        'context' => json_encode(['method' => 'password']),
        'created_at' => now(),
    ]);

    (require database_path('migrations/2026_08_13_000200_backfill_audit_log_project_ids.php'))->up();

    expect(AuditLog::find($id)->project_id)->toBeNull();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `pnpm php ./vendor/bin/pest tests/Feature/AuditLogBackfillTest.php`
Expected: FAIL — the migration file does not exist.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_13_000200_backfill_audit_log_project_ids.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only the rows that already name a project can be placed. AcceptQuote
        // and SignContract wrote context['project'] long before this column
        // existed; everything else would be a guess, and a wrong entry in an
        // append-only trail cannot be corrected afterwards.
        DB::statement(<<<'SQL'
            update audit_logs a
               set project_id = p.id
              from projects p
             where a.project_id is null
               and a.context ->> 'project' = p.public_id
               and a.tenant_id = p.tenant_id
        SQL);
    }

    public function down(): void
    {
        // Deliberately empty. Reversing would clear correctly-placed rows
        // alongside the backfilled ones, and there is no marker distinguishing
        // the two.
    }
};
```

- [ ] **Step 4: Run the tests**

Run: `pnpm php ./vendor/bin/pest tests/Feature/AuditLogBackfillTest.php`
Expected: PASS.

- [ ] **Step 5: Run it against the real database**

Run: `pnpm php php artisan migrate`
Then check how much was placed:

```bash
pnpm php php artisan tinker --execute="echo App\Models\AuditLog::whereNotNull('project_id')->count();"
```

Record the number in the pull request. Projects created before this change show a partial feed — that is expected and documented in the spec, not a bug to chase.

- [ ] **Step 6: Commit**

```bash
git add database/migrations tests/Feature/AuditLogBackfillTest.php
git commit -m "feat(audit): backfill the project of historical quote and contract events"
```

---

## Task 8: The activity presenter

**Files:**
- Create: `app/Crm/Support/ProjectActivity.php`
- Create: `lang/de/activity.php`
- Modify: `app/Crm/Http/Controllers/ProjectController.php` (the `show()` render array)
- Test: `tests/Feature/Crm/ProjectActivityTest.php`

**Interfaces:**
- Consumes: `audit_logs.project_id` from Tasks 5–7.
- Produces: `ProjectActivity::for(Project $project, int $limit = 25): array` returning a list of `['event' => string, 'label' => string, 'at' => string, 'actorName' => ?string]`, newest first. Task 10 renders exactly these four keys.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Crm/ProjectActivityTest.php`:

```php
<?php

use App\Crm\Models\Project;
use App\Crm\Support\ProjectActivity;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a project\'s events newest first', function () {
    $project = Project::factory()->create();

    AuditLogger::record('quote.sent', null, [], null, $project->id);
    $this->travel(1)->hours();
    AuditLogger::record('contract.signed', null, [], null, $project->id);

    $activity = ProjectActivity::for($project);

    expect($activity)->toHaveCount(2)
        ->and($activity[0]['event'])->toBe('contract.signed');
});

it('drops an event with no label rather than showing its key', function () {
    $project = Project::factory()->create();

    AuditLogger::record('quote.sent', null, [], null, $project->id);
    AuditLogger::record('some.internal.plumbing', null, [], null, $project->id);

    expect(ProjectActivity::for($project))->toHaveCount(1);
});

it('excludes events belonging to another project', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();

    AuditLogger::record('quote.sent', null, [], null, $other->id);

    expect(ProjectActivity::for($project))->toBeEmpty();
});

it('never exposes the request metadata an audit row carries', function () {
    $project = Project::factory()->create();

    AuditLogger::record('quote.sent', staffMember(), ['contact' => 'c_secret'], null, $project->id);

    expect(array_keys(ProjectActivity::for($project)[0]))
        ->toBe(['event', 'label', 'at', 'actorName']);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `pnpm php ./vendor/bin/pest tests/Feature/Crm/ProjectActivityTest.php`
Expected: FAIL — `Class "App\Crm\Support\ProjectActivity" not found`.

- [ ] **Step 3: Write the label map**

Create `lang/de/activity.php`. Terse noun phrases, no sentences. An event absent from this map is not shown at all, which is what makes the map the allow-list as well as the copy:

```php
<?php

return [
    'lead.captured' => 'Anfrage eingegangen',
    'lead.stage_advanced' => 'Phase gewechselt',
    'quote.sent' => 'Angebot gesendet',
    'quote.revised' => 'Angebot überarbeitet',
    'quote.accepted' => 'Angebot angenommen',
    'contract.sent' => 'Vertrag gesendet',
    'contract.signed' => 'Vertrag unterschrieben',
    'project.booked' => 'Projekt gebucht',
    'payment_plan.created' => 'Zahlungsplan angelegt',
    'payment_plan.updated' => 'Zahlungsplan geändert',
    'payment_installment.paid' => 'Zahlung erhalten',
    'shoot.status_changed' => 'Shooting-Status geändert',
    'shoot.primary_designated' => 'Haupttermin festgelegt',
    'form_instance.sent' => 'Fragebogen gesendet',
    'form_instance.completed' => 'Fragebogen ausgefüllt',
];
```

Cross-check every key against the `event:` strings at the twenty-four call sites from Task 6. A key that matches no call site is dead copy; a call site with no key is an event that will never appear.

- [ ] **Step 4: Write the presenter**

Create `app/Crm/Support/ProjectActivity.php`:

```php
<?php

namespace App\Crm\Support;

use App\Crm\Models\Project;
use App\Models\AuditLog;

/**
 * A project's audit rows as something a screen can render.
 *
 * The whitelist below is the point of this class. An audit row carries
 * ip_address, user_agent and a context whose contents vary per event and can
 * include contact identifiers — none of which belongs in a page prop. Returning
 * arrays rather than models means a column added to audit_logs later cannot
 * silently widen the payload.
 *
 * An event with no entry in lang/de/activity.php is dropped, not rendered raw:
 * the label map doubles as the allow-list.
 */
class ProjectActivity
{
    public static function for(Project $project, int $limit = 25): array
    {
        return AuditLog::query()
            ->where('project_id', $project->id)
            ->with('actor:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->filter(fn (AuditLog $log) => trans()->has("activity.{$log->event}"))
            ->map(fn (AuditLog $log) => [
                'event' => $log->event,
                'label' => __("activity.{$log->event}"),
                'at' => $log->created_at->toIso8601String(),
                'actorName' => $log->actor?->name,
            ])
            ->values()
            ->all();
    }
}
```

`AuditLog` has no `actor` relation yet. Add one to `app/Models/AuditLog.php`:

```php
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
```

with `use Illuminate\Database\Eloquent\Relations\BelongsTo;` and `use App\Models\User;` — the latter is the same namespace, so no import is needed for `User`.

- [ ] **Step 5: Run the tests**

Run: `pnpm php ./vendor/bin/pest tests/Feature/Crm/ProjectActivityTest.php`
Expected: PASS, all four.

- [ ] **Step 6: Hand it to the screen**

In `app/Crm/Http/Controllers/ProjectController.php`, add to the `Inertia::render('Projects/Show', [...])` array:

```php
            // Whitelisted by ProjectActivity, admin-only by omission: the
            // portal's own ProjectController does not render this key. An
            // internal audit trail on a client page would disclose staff
            // names and internal event vocabulary to the client.
            'activity' => ProjectActivity::for($project),
```

with `use App\Crm\Support\ProjectActivity;` in the imports.

- [ ] **Step 7: Commit**

```bash
git add app lang/de/activity.php tests/Feature/Crm/ProjectActivityTest.php
git commit -m "feat(projects): show a project's audit trail as an activity feed"
```

---

## Task 9: Hold the security boundary with tests

**Files:**
- Test: `tests/Feature/Crm/ProjectActivityTest.php` (extend)
- Test: `tests/Feature/Portal/PortalProjectTest.php` (extend, or create if absent)

**Interfaces:**
- Consumes: the `activity` prop from Task 8.
- Produces: nothing. This task only asserts.

Task 8's unit test covers the presenter's own output. This one covers the two boundaries at the HTTP layer, where a later refactor is most likely to widen them by accident.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Crm/ProjectActivityTest.php`:

```php
it('never ships request metadata in the admin project payload', function () {
    $project = Project::factory()->create();
    AuditLogger::record('quote.sent', staffMember(), ['contact' => 'c_secret'], null, $project->id);

    $this->actingAs(staffMember(), 'staff');
    $payload = json_encode(props("/auftraege/{$project->public_id}")['activity']);

    expect($payload)
        ->not->toContain('ip_address')
        ->not->toContain('user_agent')
        ->not->toContain('c_secret');
});
```

Create or extend `tests/Feature/Portal/PortalProjectTest.php`:

```php
it('does not hand the client an internal audit trail', function () {
    useSurface('portal');
    $project = Project::factory()->create();
    AuditLogger::record('quote.sent', staffMember(), [], null, $project->id);

    // …authenticate as the project's contact the way the other Portal suites do…

    expect(props("/projekt/{$project->public_id}"))->not->toHaveKey('activity');
});
```

Match the portal authentication and the route path to whatever the neighbouring Portal suites already do — those details differ per suite and must not be invented here.

- [ ] **Step 2: Run them**

Run: `pnpm php ./vendor/bin/pest tests/Feature/Crm/ProjectActivityTest.php tests/Feature/Portal/PortalProjectTest.php`
Expected: PASS. These assert a boundary Task 8 already built correctly — they fail only if someone later widens it.

- [ ] **Step 3: Run the guard suites that already exist**

Run: `pnpm php ./vendor/bin/pest tests/Feature/NoInternalIdsTest.php tests/Feature/AudienceMatrixTest.php`
Expected: PASS. The activity payload is the most likely new source of an internal id; `actorName` is a name and `at` is a timestamp, so it should stay clean.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature
git commit -m "test(projects): hold the activity feed's whitelist and its surface"
```

---

## Task 10: The project screen adopts the four components

**Files:**
- Modify: `resources/js/admin/pages/Projects/Show.vue:388-425` (the `.kv` block) and the surrounding right column
- Create: `tests/e2e/project-detail.spec.ts`

**Interfaces:**
- Consumes: `.tiles`, `.rail`, `.perf`, `.feed` from Task 4; the `activity` prop from Task 8.
- Produces: the markup pattern Task 11 copies into the remaining screens.

Hold the file next to `docs/superpowers/specs/2026-08-13-crm-visual-system-mockup.html` while working. The mockup is the acceptance criterion, not a reference.

- [ ] **Step 1: Write the failing e2e test**

Create `tests/e2e/project-detail.spec.ts`, following the login helper the existing specs use:

```ts
import { expect, test } from '@playwright/test'
import { loginAsStaff } from './helpers'

test('the project screen shows tiles, an action rail and an activity feed', async ({ page }) => {
  await loginAsStaff(page)
  await page.goto('/auftraege')
  await page.getByRole('link', { name: /Hochzeit/ }).first().click()

  await expect(page.locator('.fact').first()).toBeVisible()
  await expect(page.locator('.rail .railrow')).not.toHaveCount(0)
  await expect(page.locator('.feed .ev').first()).toBeVisible()
})
```

Check `tests/e2e/helpers.ts` for the actual exported login helper name and use that one.

- [ ] **Step 2: Run it and watch it fail**

Run: `pnpm --filter @studio/app exec playwright test project-detail`
Expected: FAIL — no `.fact` on the page.

- [ ] **Step 3: Replace the fact list with tiles**

In `Show.vue`, replace the `.kv` block at lines 393–418 with:

```vue
          <div class="tiles">
            <div class="fact">
              <div class="l">{{ $t('project.mainDateLabel') }}</div>
              <div class="v">
                {{ header.primaryDate
                    ? new Date(header.primaryDate).toLocaleDateString('de-DE')
                    : $t('board.openDate') }}
              </div>
              <div class="s">{{ header.type }}</div>
            </div>
            <div class="fact">
              <div class="l">{{ $t('project.position') }}</div>
              <div class="v">{{ header.position.label }}</div>
              <div class="s">{{ header.isBooked ? $t('project.booked') : $t('project.notBooked') }}</div>
            </div>
            <div :class="['tile', { ghost: !header.portalVisible }]">
              <div class="l">{{ $t('project.portalStatus') }}</div>
              <div class="v">
                {{ header.portalVisible ? $t('project.portalVisible') : $t('project.portalHidden') }}
              </div>
            </div>
          </div>
```

The `.fact.ghost` variant replaces the old `:class="{ tbd: … }"` grey-text treatment. Remove the now-unused `tbd` bindings in this block.

- [ ] **Step 4: Add the action rail**

Below the tiles, replace the portal-visibility `AlertDialogTrigger` button — and any other lone ghost button in this column — with a rail. Keep the `AlertDialog` wrapper; only the trigger's presentation changes:

```vue
          <div class="rail">
            <a class="railrow" :href="header.portalUrl" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24"><path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
              {{ $t('project.viewPortal') }}
            </a>
            <AlertDialog v-if="header.canManagePortalVisibility">
              <AlertDialogTrigger as-child>
                <button type="button" class="railrow danger">
                  <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                  {{ portalVisibilityLabel }}
                </button>
              </AlertDialogTrigger>
              <!-- …the existing AlertDialogContent, unchanged… -->
            </AlertDialog>
          </div>
```

`header.portalUrl` may not exist on `ProjectHeaderData`. If it does not, either add it there or drop that first `.railrow` — do not invent a prop and leave it undefined.

Add any new copy keys to `resources/js/locales/de.json` as short noun phrases.

- [ ] **Step 5: Add the perforation and the feed**

Below the rail:

```vue
          <div class="perf"></div>

          <div class="feedlab">{{ $t('project.activity') }}</div>
          <div class="feed">
            <div v-for="event in activity" :key="event.at + event.event" class="ev">
              <div class="dot"></div>
              <div>
                <div class="t">{{ event.label }}</div>
                <div class="w">
                  {{ new Date(event.at).toLocaleString('de-DE', {
                       day: '2-digit', month: 'short', year: 'numeric',
                       hour: '2-digit', minute: '2-digit',
                     }) }}
                  <template v-if="event.actorName"> · {{ event.actorName }}</template>
                </div>
              </div>
            </div>
          </div>
```

Declare the prop in the `defineProps` block, matching the presenter's contract exactly:

```ts
  activity: { event: string; label: string; at: string; actorName: string | null }[]
```

- [ ] **Step 6: Run the e2e test**

Run: `pnpm --filter @studio/app exec playwright test project-detail`
Expected: PASS.

- [ ] **Step 7: Typecheck**

Run: `pnpm lint`
Expected: no errors.

- [ ] **Step 8: Compare against the mockup**

Open the screen beside `docs/superpowers/specs/2026-08-13-crm-visual-system-mockup.html`. Tile label size, action rail spacing and feed rail must match. Attach a screenshot to the pull request — a mockup named in a story is an acceptance criterion.

- [ ] **Step 9: Commit**

```bash
git add resources/js tests/e2e/project-detail.spec.ts
git commit -m "feat(projects): rebuild the project screen on the new components"
```

---

## Task 11: The remaining screens

**Files:**
- Modify: `resources/js/portal/pages/Impressum.vue`, `resources/js/admin/pages/Settings/FormTemplates/Edit.vue`, `resources/js/admin/pages/Projects/Contract.vue`, `resources/js/admin/pages/Galleries/Ingest.vue` — the four remaining `.kv` consumers
- Modify: the fourteen remaining components carrying ghost buttons in `.cardhead`

**Interfaces:**
- Consumes: everything from Tasks 4 and 10.
- Produces: nothing. This is the sweep that removes the seam.

- [ ] **Step 1: Find what is left**

```bash
cd apps/studio/resources/js && grep -rln --include="*.vue" 'kvrow' .
```

Expected: four files (`Projects/Show.vue` is already done).

- [ ] **Step 2: Convert each `.kv` list to `.tiles`**

Use the exact markup from Task 10 Step 3 — label in `.l`, value in `.v`, optional sub-line in `.s`, `.fact.ghost` for a fact that is not known yet. Convert one file, run `pnpm lint`, look at the screen, then move to the next. Do not batch four files into one commit.

- [ ] **Step 3: Consolidate the ghost buttons**

```bash
grep -rln --include="*.vue" 'cardhead' .
```

For each file: where a card head carries two or more ghost buttons, move them into an `.rail` rail using the markup from Task 10 Step 4. Where a card head carries exactly one, leave it — a rail of one is a button with extra steps.

- [ ] **Step 4: Wire the late payment state**

`.paystatus.late` exists as a rule after Task 2, but nothing selects it — an overdue installment
still renders amber. The rule for "overdue" is already established by
`app/Payments/Actions/DetectOverdueInstallments.php`. Read that action and reuse its condition;
do not invent a second definition of overdue.

Write the failing test first, in `tests/Feature/Payments/PaymentPlanDataTest.php` (create it if
absent):

```php
it('marks an unpaid installment past its due date as late', function () {
    $installment = PaymentInstallment::factory()->create([
        'due_on' => now()->subDay()->toDateString(),
        'status' => PaymentInstallmentStatus::Pending,
    ]);

    expect(PaymentPlanData::fromModel($installment->plan->load('installments'))
        ->installments[0]->isLate)->toBeTrue();
});

it('does not mark a paid installment late, however old', function () {
    $installment = PaymentInstallment::factory()->create([
        'due_on' => now()->subYear()->toDateString(),
        'status' => PaymentInstallmentStatus::Paid,
    ]);

    expect(PaymentPlanData::fromModel($installment->plan->load('installments'))
        ->installments[0]->isLate)->toBeFalse();
});
```

Run it, watch it fail, add the `isLate` field to the installment Data object, then bind the class
where the rows render: `:class="['paystatus', row.isLate ? 'late' : row.status]"`. Adjust the Data
class and property names to whatever `app/Payments/Data/` actually uses — the assertions are the
requirement, the names around them are not.

- [ ] **Step 5: Run everything**

```bash
pnpm lint
pnpm php ./vendor/bin/pest
pnpm --filter @studio/app test
pnpm --filter @studio/app exec playwright test
```

Expected: all green.

- [ ] **Step 6: Walk the app**

Open every admin screen and the portal. Look for two things specifically: warm orange on anything that is not pressable, and a status chip that lost its meaning in the recolour. Both are misses from Task 2, and both are easier to see on a real screen than in a diff.

- [ ] **Step 7: Commit**

```bash
git add resources/js app tests
git commit -m "feat(admin): carry the new components across the remaining screens"
```

---

## Self-Review Notes

Checked against the spec, section by section:

- Part 1.1 base palette → Task 1. Part 1.2 gallery → Task 3. Part 1.3 reassignment table → Task 2, every row.
- Part 2, all four components → Task 4.
- Part 3.1 migration → Task 5. 3.2 signature → Task 5. 3.3 call sites → Task 6. 3.4 backfill → Task 7. 3.5 presenter → Task 8. 3.6 security boundary → Tasks 8 and 9.
- Part 4 component changes → Tasks 10 and 11.
- Part 5 testing → the test steps inside Tasks 5–11. The spec's "no automated visual regression" holds: Tasks 10 and 11 verify against the mockup by eye, with a screenshot on the pull request.

Two places where the plan deliberately says "check the existing code rather than trusting this file", because inventing the detail would be worse than naming the gap: the portal authentication in Task 9 Step 1, and `header.portalUrl` in Task 10 Step 4.
