# CRM Visual System — Neutral Base, Warm Action Design

**Status:** approved for planning
**Mockup:** [`2026-08-13-crm-visual-system-mockup.html`](./2026-08-13-crm-visual-system-mockup.html) — open it in a browser before implementing; it is the visual target.
**Reference:** a Stripe-style invoice detail screen supplied by the studio owner: filled grey stat tiles, an icon-and-label action rail, a perforated divider, an activity timeline, no accent colour anywhere.

## Goal

Rebuild the admin surface — and the portal that inherits from it — so it reads
like the reference: a neutral grey page where the only warm colour is a button
you can press. Today's surface is warm throughout (`--hover:#F6F3F1`) and spends
its single accent, `#B44E22`, on two incompatible jobs at once: *act here* and
*something is wrong here*.

## Decisions Taken

Four decisions were settled against mockups before this spec was written. They
are inputs, not open questions.

1. **Neutral grey base.** Not "keep the warm palette and only change the
   shapes". The page matches the reference exactly.
2. **The accent stays warm, and is reserved for action.** `#B44E22` continues to
   mean *press this*. It never again marks a state.
3. **Status gets a full semantic set** — green done, amber due, red late. Not a
   single alarm colour, and not weight-only encoding. The lead board is read at
   a glance across six columns, and colour is what survives that.
4. **Whole admin app in one pass, portal included.** A partial conversion leaves
   a visible seam between screens, because `tokens.css` is shared by every
   surface.

## Non-Goals

- **No Tailwind translation.** `tokens.css` carries the design decisions as CSS,
  on purpose (see its own header comment). Utilities would drift from the
  mockups on the first unmirrored edit.
- **No dark mode for admin.** The gallery keeps its existing dark theme; nothing
  else gains one.
- **No new layout for Media, Gallery internals, or Kalender.** They are recoloured
  by the token change and gain the new components only where a tile, action rail
  or feed already fits. Their information architecture is untouched.
- **No redesign of the document editor's output.** `document-editor.css` styles
  the authoring surface, not the rendered PDF. Client-facing document design is a
  separate concern and stays as-is.

## Part 1 — Tokens

`mockups/_shared/tokens.css` stays the source of truth and is edited first;
`apps/studio/resources/css/tokens.css` is then ported to match, as its header
comment requires.

### 1.1 Base palette (`:root`)

```
--bg:#FFFFFF
--ink:#191919
--muted:#6B6B6B
--faint:#A3A3A3
--hover:#F5F5F4        /* fill AND hover — one surface, not two */
--line:#E9E9E7
--line-hover:#DCDCD9

--accent:#B44E22       --accent-bg:#FBF0EA     /* action only */

--ok:#3F6B4A           --ok-bg:#EDF3EE
--due:#8A6212          --due-bg:#FAF3E4        /* new */
--late:#B3261E         --late-bg:#FCF0EF       /* new */
```

`--hover` doing double duty as the tile fill is deliberate: the reference's grey
cards and its hover states are the same grey. Two tokens would invite them to
drift apart.

Spacing scale, radii, `--font` and the `[data-theme="portal"]` block are
unchanged. Portal inherits the neutral base automatically, which is what
"portal comes along" means in practice.

### 1.2 Gallery theme gains the semantic set

`[data-theme="gallery"]` currently overrides only the neutrals and the accent.
It therefore inherits `--ok:#3F6B4A` / `--ok-bg:#EDF3EE` from `:root` — a light
green panel on a `#0E0C0B` page. That is a latent bug today and becomes a
guaranteed one once `--due` and `--late` join. The gallery block gains dark
variants of all six:

```
--ok:#7FB98C     --ok-bg:#16211A
--due:#D9B25C    --due-bg:#241D0E
--late:#E88379   --late-bg:#2A1512
```

### 1.3 Reassigning every current `--accent` site

Every existing use is classified as action (keeps the accent), status (moves to
the semantic set), or neither (goes neutral). This table is exhaustive — it was
built from a grep across all seven stylesheets, and nothing outside it uses
`--accent`.

| Site | File | Today | Becomes | Why |
|---|---|---|---|---|
| `.age.warn` | tokens.css:151 | accent | `--late` | an unanswered lead past its threshold |
| `.flag` | tokens.css:163 | accent | `--late` | scheduling conflict banner |
| `.stage .now` | tokens.css:215 | accent | `--ok` | the current phase is a state, not an alarm |
| `.stage .now.late` | tokens.css:215 | — | `--late` (new) | derived board position is overdue |
| `.bkrow i` | tokens.css:236 | accent | `--ok` | added value on a booking, a good number |
| `.chip.now` | tokens.css:243 | accent | `--due` | the shoot that is next up |
| `.sname .prim` | tokens.css:258 | accent | `--ink`, 600 | "primary shoot" is a label |
| `.paystatus.due` | tokens.css:293 | accent | `--due` | due, not yet late |
| `.paystatus.late` | tokens.css:293 | — | `--late` (new) | past its date, currently indistinguishable from due |
| `.taudience.client` | tokens.css:301 | accent | outline, `--muted` | a category, not a state — see below |
| `.bar > i.warn` | admin-extra.css:21 | accent | `--late` | a failing transfer |
| `.chip.fail` | admin-extra.css:37 | accent | `--late` | ingest rejected the file |
| `.rp` | admin-extra.css:53 | accent | `--hover`/`--muted` | "repeat client" is information |
| `.kpi b i` | admin-extra.css:95 | accent | `--late` | the overdue share of a KPI |
| `.dr .what i` | admin-extra.css:114 | accent | `--late` | why a dashboard row needs attention |
| `.ready i.no` | admin-extra.css:126 | accent | `--late` | a missing readiness mark |
| `.qreq` | admin-extra.css:179 | accent | `--hover`/`--muted` | "required" is a field property |
| `.doc-live.stale` | document-editor.css:27 | accent | `--due` | preview is pending, not broken |
| merge-field chip | document-editor.css:48 | accent-bg + `#8A3A18` | `--hover`/`--ink` | dynamic content, like inline code |
| `.doc-tool-input:focus` | document-editor.css:83 | accent | **keeps accent** | focus is interaction |
| `.doc-callout` | document-editor.css:100 | accent | **keeps accent** | brand emphasis inside a document |
| `--color-destructive` | app.css:39 | `var(--accent)` | `var(--late)` | see below |
| `--color-ring` | app.css:43 | accent | **keeps accent** | focus ring is interaction |
| `.err` | forms.css:35 | accent | `--late` | a validation failure |
| `.err` (gallery) | gallery.css:56,58 | accent | `--late` | same, on the dark theme |
| `.pmark i` | portal.css:33 | accent | **keeps accent** | marks a client action |
| consent checkbox | portal.css:110 | accent | **keeps accent** | interaction |

Two of these deserve their own note:

**`--color-destructive: var(--accent)`** (app.css:39). Destructive actions and
primary actions currently render in the same colour. Under the old rule that was
merely ambiguous; under the new one — accent means *press this* — a delete button
and a save button would be indistinguishable. It moves to `--late`.

**`.taudience.client`** distinguishes a client-visible task from an internal one.
Filling it with a status colour would claim a state it does not have, and both
audiences filled in neutral grey would be identical. It becomes an outlined chip
(`border:1px solid var(--line-hover)`, transparent background, `--muted` text)
against the filled `.taudience.internal`. The distinction survives greyscale.

## Part 2 — Four New Components

Added to `mockups/_shared/admin-extra.css`, then ported to
`apps/studio/resources/css/admin-extra.css`. Roughly sixty lines total. No
JavaScript, no assets, no dependencies.

### 2.1 `.tiles` / `.tile` — filled fact tiles

Replaces the `.kv` / `.kvrow` fact list — the key-on-the-left, value-on-the-right
rows used by the Buchung card in `Projects/Show.vue` and four other components. A
tile is `--hover` fill, `--r` radius, an uppercase 9.5px `--faint` label, a 16px
tabular value, and an optional 12.5px `--muted` sub-line.

`.bklist` / `.bkrow` — the mockups' two-column label-over-value grid — has no Vue
consumer at all; the app shipped `.kv` instead and the two have been drifting
since. Both mockup rules are deleted in favour of `.tiles`, which removes the
divergence rather than porting it forward.

`.tile.ghost` — dashed `--line` border, no fill — is the empty state, used where
a fact is not known yet. The old `.kvrow .tbd` grey-text treatment is dropped in
its favour, because an empty tile keeps the grid rhythm that loose grey text
breaks.

### 2.2 `.rail` / `.railrow` — the action rail

Replaces the ghost buttons currently scattered through `.cardhead` across
fifteen page components. One vertical list: 17px stroked icon, 12px gap, label,
7px radius, `--hover` on hover. `.railrow.danger` shifts its text to `--late` on
hover only — a destructive option should not shout while it sits unused.

The rail is not a new button component. `resources/js/ui/button` keeps its
variants; `.railrow` is a layout for a list of links, the way `.nav` already is in
the sidebar.

It is named `.rail` because `.acts` — the obvious name, and the one this spec
first used — is taken twice over. `mockups/_shared/gallery.css` defines `.acts`
as a horizontal centred row with `.act` items, and `apps/studio/resources/css/
admin-extra.css` defines `.note .head .acts` for a note's edit/delete pair, which
`Projects/Show.vue` renders. That second one is the dangerous of the two: it sets
neither `flex-direction` nor `margin`, so a bare `.acts` rule would reach past it
and stand the note's buttons on end. One class name meaning two layouts is the
same failure as one colour meaning two states, which is what this whole document
is about. The `-row` suffix follows the file's existing `.trow` / `.srow` /
`.payrow` / `.qbrow`.

### 2.3 `.perf` — perforated divider

The reference's dotted tear line, as a repeating radial gradient:

```css
.perf { height:8px; margin:var(--s5) 0; opacity:.75;
  background:radial-gradient(circle at 3px 6px, transparent 2.6px,
             var(--line) 2.7px 3.2px, transparent 3.3px) repeat-x;
  background-size:8px 8px; }
```

No SVG, no image, and it recolours with the token like every other line.

### 2.4 `.feed` / `.ev` — activity list

A dot rail plus event rows: 13.5px title, 12px `--faint` tabular timestamp. The
connector is a `::before` on each row, suppressed on the last one — the same
technique `.tlrail` already uses for the shooting-day timeline, so the two read
as one family. `.ev.pending` renders a future or unreached event in `--faint`
with a `--hover` placeholder bar where its timestamp will go.

## Part 3 — Activity Feed Data

The feed needs per-project events. `audit_logs` already records them — around
eighty `AuditLogger::record()` call sites across every module — but the table
has no project column, so the rows cannot be queried for one project.

### 3.1 Migration

```php
$table->foreignId('project_id')->nullable()->after('actor_id')
      ->constrained('projects')->nullOnDelete();
$table->index(['tenant_id', 'project_id', 'created_at']);
```

`nullOnDelete` rather than a cascade: `audit_logs` is append-only, and its model
throws on `deleting`. A cascade would try to delete rows the model refuses to
delete.

### 3.2 `AuditLogger::record()` gains a project

```php
public static function record(
    string $event,
    ?User $actor = null,
    array $context = [],
    ?string $subject = null,
    ?int $projectId = null,
): AuditLog
```

`?int`, not `?Project`. `App\Support` is cross-cutting and `AuditLog` is one of
the four models that belong to no module (`docs/architecture.md`); importing
`App\Crm\Models\Project` there would hang the cross-cutting layer off a domain
module. Callers pass `projectId: $quote->project_id`. Internal ids never leave
the server — `HasPublicId` puts `id` in `$hidden` — so this stays inside the
split-identity convention.

Every existing call site uses named arguments, so a new trailing optional
parameter breaks none of them.

### 3.3 Call sites that gain a project

Roughly twenty of the eighty:

`Crm/Actions`: `CaptureInquiry`, `BookProject`, `AdvanceLeadStage`,
`AdvanceShootStatus`, `DesignatePrimaryShoot`.
`Crm/Listeners`: `BookProjectOnContractAndDeposit`.
`Crm/Http/Controllers`: `ProjectController`, `TaskController`,
`ShotListItemController`, `FormInstanceController`.
`Quotes`: `SendQuote`, `AcceptQuote`, `ReviseQuote`, `QuoteController`.
`Contracts`: `SignContract`, `ContractController`.
`Payments`: `CreatePaymentPlan`, `UpdatePaymentPlan`, `MarkInstallmentPaid`.
`Notifications`: `NotificationService`.

Auth, Settings, Catalog, Staff, Portal-access and MFA events stay `null`. They
are not project events and must not appear in a project's feed.

### 3.4 Backfill

A one-time migration sets `project_id` for the historical rows whose `context`
already carries the project's public id — `AcceptQuote` and `SignContract` both
write `context: ['project' => …]` today. Every other historical row keeps
`null`. Inferring a project from `subject` alone would guess, and a wrong entry
in an append-only audit trail cannot be corrected.

Consequence, stated plainly so nobody reports it as a bug: projects created
before this change show a partial feed.

### 3.5 Presentation

`App\Crm\Support\ProjectActivity` — the Crm module owns the timeline per the
module table in `docs/architecture.md`. It loads the project's rows newest-first,
maps each `event` key to a label from a new `lang/de/activity.php`, and drops
rows whose key has no label rather than rendering a raw `quote.accepted` at the
user. Labels are terse German noun phrases — "Angebot gesendet", not a sentence.

### 3.6 Security boundary

An `audit_logs` row carries `ip_address`, `user_agent`, and a `context` whose
contents vary per event and can include contact identifiers. None of that
belongs in a page prop.

- `ProjectActivity` serialises a whitelist and nothing else:
  `{event, label, at, actor_name}`. It returns arrays, never the model, so a
  later column addition cannot silently widen the payload.
- The feed is exposed only by `App\Crm\Http\Controllers\ProjectController`.
  `App\Portal\Http\Controllers\ProjectController` does not receive it. An
  internal audit trail on a client-facing page would disclose staff names, IP
  addresses and internal event vocabulary to the client.

Both rules are asserted by tests (Part 5), in the spirit of the existing
`NoInternalIdsTest`.

## Part 4 — Component Changes

Markup work is small; most of the change is the token file. Counts are from a
grep over `apps/studio/resources/js`.

| Change | Files |
|---|---|
| `.kv` / `.kvrow` fact lists → `.tiles` | 5 |
| `.cardhead` ghost buttons → the `.rail` | 15, one to three lines each |
| `.perf` + `.feed` added | 2 (`Projects/Show.vue`, `Contacts/Edit.vue`) |
| `.chip` variants recoloured | 22, CSS-only — no markup edit |
| `paystatus` gains a `late` state | 1, plus the controller that derives it |

`Projects/Show.vue` is the screen the mockup depicts and the one to build first;
it exercises all four new components at once.

## Part 5 — Testing

Commands run through Docker, per the existing plans: `pnpm php ./vendor/bin/pest`,
`pnpm --filter @studio/app test`, `pnpm --filter @studio/app exec playwright test`,
`pnpm lint`.

- **Pest** — `project_id` is written by each of the twenty converted call sites;
  the backfill migration assigns exactly the rows with a project in `context` and
  leaves the rest null; `ProjectActivity` drops unlabelled event keys.
- **Pest, security** — the admin project payload contains no `ip_address`,
  `user_agent` or `context` key; the portal project payload contains no activity
  key at all. These two are the boundary from §3.6 and are not optional.
- **Playwright** — the project detail screen renders tiles, action rail and feed,
  and the feed's newest entry matches the most recent audit row.
- **Visual** — no automated regression suite. The mockup beside this spec is the
  reference; screens are compared against it by eye, as with the clickdummy.

## Sequencing

1. `mockups/_shared/tokens.css` and `admin-extra.css` — palette plus the four
   components. The mockups are the source of truth and change first.
2. Port both into `apps/studio/resources/css/`. The app is now recoloured; no
   Vue file has been touched.
3. Migration, `AuditLogger` signature, the twenty call sites, backfill.
4. `ProjectActivity`, `lang/de/activity.php`, the admin `ProjectController` prop.
5. `Projects/Show.vue` — tiles, action rail, perforation, feed.
6. The remaining Vue components, screen by screen.

Steps 1–2 are independently shippable: the app is fully recoloured and coherent
before any markup moves.
