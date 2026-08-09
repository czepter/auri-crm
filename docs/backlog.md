# Product Backlog

**Date:** 2026-08-08
**Parent:** [Product Vision](superpowers/specs/2026-08-07-photography-client-management-vision-design.md) · [Clickdummy](superpowers/specs/2026-08-07-clickdummy-design.md)
**Status:** Living document. Ordered by delivery sequence, not by value — the vision's §9 phasing already resolved that trade.

The vision defines five phases. This backlog decomposes them into epics and stories small enough for one sprint each. Sizes are relative (S ≈ a day, M ≈ 2–3 days, L ≈ a week of focused work), not commitments.

A story enters a sprint only when it has: a named screen (mockup or "no mockup, solved pattern"), a testable acceptance line, and no unresolved dependency above it.

---

## Ordering rule

Phases run in order because each one's schema is the next one's foundation, with one exception worth naming: **P1b (Sales groundwork) does not exist**. Where the vision says P1 and P2, this backlog splits P1 into P1a (foundation and staff auth) and P1b (the CRM proper), because P1a ships no business value on its own and its Definition of Done is entirely infrastructural. Shipping it as its own sprint keeps the first business-value sprint from carrying a week of scaffolding risk.

---

## P1a — Foundation & Staff Authentication · **Sprint 1**

Full task-level plan: [`plans/2026-08-07-p1a-foundation-and-staff-auth.md`](superpowers/plans/2026-08-07-p1a-foundation-and-staff-auth.md).
Sprint sheet: [`sprints/sprint-01.md`](sprints/sprint-01.md).

| # | Story | Size | Mockup |
|---|---|---|---|
| F-01 | Monorepo, pnpm workspace, Docker Compose (php + postgres + mailpit), CI pipeline | M | — |
| F-02 | Laravel scaffold; `APP_SURFACE` loads exactly one route file; unknown surface throws at boot | M | — |
| F-03 | Inertia + Vue 3 + Tailwind 4 + shadcn-vue on the admin surface, per-surface Vite entrypoint | M | tokens.css |
| F-04 | Schema conventions: `tenant_id` seam, `HasPublicId` (CUID2), route binding on `public_id` | M | — |
| F-05 | Staff users, roles enum, seeder | S | — |
| F-06 | Append-only audit log, DB-level privilege revocation documented | M | — |
| F-07 | Password login end to end, uniform failure response, rate limiting | M | — |
| F-08 | Mandatory TOTP enrollment + challenge + recovery codes | L | — |
| F-09 | `EnsureAudience` middleware and the route-group × audience matrix test | M | — |
| F-10 | Typed Inertia props (`spatie/laravel-data` → `generated.d.ts`), `NoInternalIdsTest` | M | — |

**Milestone:** a staff user signs in over TOTP against the admin container; nothing else exists.

---

## P1b — CRM Core · Sprints 2–4

The phase that replaces the spreadsheet. Every screen here exists as a mockup.

### Epic: Contacts and projects

| # | Story | Size | Mockup |
|---|---|---|---|
| C-01 | `Contact` CRUD, partial unique index on `(tenant_id, email) where email is not null` | M | — |
| C-02 | `Project` + `Lead` + `ProjectContact` pivot with roles (primary, partner, payer, planner, vendor) | L | 04-auftrag |
| C-03 | `Shoot` with `date`/`call_time`/`end_time` as `date`/`time` columns; partial unique index on `is_primary` | M | 04-shootings |
| C-04 | Manual lead capture form with contact typeahead and multi-shoot repeater | L | 03-anfrage-neu |
| C-05 | Availability check scanning `shoots.date` for non-lost projects, shown inline at capture | M | 03-anfrage-neu |
| C-06 | Project detail chrome: header with derived pipeline position, tab navigation | M | 04-auftrag |
| C-07 | Internal notes on the project overview (separate model, never a flag on `Message`) | S | 04-auftrag |

### Epic: Pipeline and board

| # | Story | Size | Mockup |
|---|---|---|---|
| P-01 | `Lead.stage` funnel with six stages plus `lost(reason)`; transitions emit events | M | 02-anfragen |
| P-02 | Lead board: six columns, card anatomy per clickdummy §card-anatomy | L | 02-anfragen |
| P-03 | `Shoot.status` forward-only pipeline, nine states, event per transition | M | 04-shootings |
| P-04 | Derived production board — the `distinct on` query, joined through `jobs` | M | 01-uebersicht |
| P-05 | Conversion: contract signed + deposit paid creates `Job`, moves shoots to `booked`, emits `ProjectBooked` | L | 04-auftrag |

### Epic: Staff working surface

| # | Story | Size | Mockup |
|---|---|---|---|
| S-01 | Dashboard: KPI row over four CRM domains | M | 01-uebersicht |
| S-02 | `CrewAssignment` per shoot with role, stacked avatars | M | 04-shootings |
| S-03 | `Task` + `TaskTemplate`, applied on transition into `booked`, offsets relative to that shoot's date | L | 04-auftrag |
| S-04 | Calendar view and ICS export for crew | M | — |
| S-05 | Staff invitation and password reset flows | M | — |
| S-06 | Role-based policies (second shooter sees assigned shoots; editor sees media, not payments) | M | — |

---

## P2 — Sales · Sprints 5–7

### Epic: Client access

| # | Story | Size | Mockup |
|---|---|---|---|
| A-01 | `portal` surface: route file, Vite entrypoint, theme layer, session cookie | M | 06-startseite |
| A-02 | `ClientAccess` contact-scoped magic links: single-use, 15 min, rate-limited, no enumeration | L | — |
| A-03 | Portal project switcher, visibility derived per request from `project_contacts` + `portal_visible` | M | 06-startseite |
| A-04 | Two-form revocation: hide project vs. revoke contact access, never one button | M | — |

### Epic: Quotes

| # | Story | Size | Mockup |
|---|---|---|---|
| Q-01 | `Package` + `PackageItem` catalog | M | — |
| Q-02 | `Quote` + `QuoteItem` with nullable `unit_price` (*Enthaltene Leistung*) | L | 04-angebot |
| Q-03 | Two-level discounts resolving to amounts; `net >= 0`; rounding once on the total | M | 04-angebot |
| Q-04 | VAT snapshot, `vat_mode`, §19 UStG note | M | 04-angebot |
| Q-05 | `AN-{year}-{counter}` numbering per tenant; `public_id` in every URL | S | 04-angebot |
| Q-06 | Send freezes the snapshot; revisions are new rows, predecessor `superseded` | M | 04-angebot |
| Q-07 | Portal acceptance with server-side validity re-check; records `accepted_ip` | M | 06-startseite |

### Epic: Contracts and money

| # | Story | Size | Mockup |
|---|---|---|---|
| M-00 | Queue runtime: Redis, Horizon, worker container in Compose and in the deploy | M | — |
| M-01 | `ContractTemplate` with merge fields, PDF via Gotenberg | L | 07-vertrag |
| M-02 | Simple electronic signature: typed name, explicit consent, SHA-256, IP, UA, template version | L | 07-vertrag |
| M-03 | `PaymentPlan` + `PaymentInstallment`, job-scoped, minor units | M | 04-auftrag |
| M-04 | Stripe Invoicing per installment | L | — |
| M-05 | Webhooks idempotent by event ID; `InstallmentPaid` / `InstallmentOverdue` | M | — |

M-00 is new, and it is P2's own bill rather than P3's. Gotenberg rendering and Stripe webhook handling both belong off the request cycle, so the queue runtime is needed here first; the [gallery spike](superpowers/specs/2026-08-09-p3-gallery-spike.md) §6 found P3 was silently inheriting it. Schedule it in sprint 7, ahead of M-01.

---

## P3 — Gallery · Sprints 8–11

**G-00 is done.** [`specs/2026-08-09-p3-gallery-spike.md`](superpowers/specs/2026-08-09-p3-gallery-spike.md) retires the four unknowns the vision's §9 gated this phase behind, and the verdict is *go with changes*. The table below is that verdict applied; the spike's §6 carries the reasoning for each change and §8 the sprint split.

**Hard entry gate: P3 cannot start before P1b C-02 and C-03 are merged.** `Asset.shoot_id` is a foreign key to a table that does not exist, and shoot-scoping is not retrofittable — a nullable placeholder column would quietly make "every frame from the reception" return the wrong rows.

**Accepted degradation:** P3 completes without P2's payment schema. `Gallery.requires_settled_balance` ships defaulting false with an `InstallmentPaid` listener that stays inert until M-05 delivers the event. If P2 slips, galleries simply cannot be payment-gated.

| # | Story | Size | Mockup |
|---|---|---|---|
| ~~G-00~~ | ~~Spike: retire the four unknowns before committing the phase plan~~ — **done** | M | — |
| G-01a | Media infrastructure: S3 driver, MinIO in Compose, `media.ingest` / `media.zip` queues with per-queue timeouts | M | — |
| G-02 | Presigned S3 multipart ingest from the admin browser, quarantine prefix | L | 05-medien |
| G-03 | Ingest queue: checksum, EXIF, perceptual hash, libvips derivatives, blurhash; idempotent on SHA-256. Ladder includes an 800 px rung; derivatives chain from a single decode; AVIF pinned to `effort=2`; 4 workers at `VIPS_CONCURRENCY=1` | L | 05-medien |
| G-01 | `api` surface + Sanctum guest tokens + `packages/contracts` OpenAPI generation in CI | L | — |
| G-05 | `Gallery` with nullable `shoot_id`; `GalleryVersion` immutable publishing | L | 04-galerien |
| G-04 | "Needs attention" tray for failed assets | S | 05-medien |
| G-08 | Astro gallery: grid, srcset, AVIF/WebP. Images cached immutably under a version-scoped path; HTML `no-store`; publishing versions rather than invalidates | L | 09-raster |
| G-06 | Three gates: view password, download PIN, verified email — independently rotatable, and held at three different layers (origin session / edge token TTL / storage presign) | L | 08-passwort, 11-pin, 12-email |
| G-07 | Rate limiting per IP *and* per gallery; uniform failure with consistent timing | M | 08-passwort |
| G-10 | `ZipBuild` streamed S3→S3, deduped by (version, scope, size), split at 3.9 GiB, ZIP64 explicitly disabled, fixed 16 MiB multipart parts | L | — |
| G-11 | `DownloadEvent` audit + signed 24 h links bounded by **distinct requester-days**, presigned direct to storage so Range resumes survive | M | 12-email |
| G-09 | Lightbox with favorite toggle | M | 10-lightbox |

Six changes are worth naming, because each is a claim the spike had to measure or read a vendor spec to make:

- **G-01a is new.** The infrastructure P3 assumed simply does not exist — Compose has `php`, `postgres`, `mailpit`, and `composer.json` has no Sanctum and no S3 driver. The queue-runtime half of it moved up to P2 as [M-00](#epic-contracts-and-money).
- **G-01 moved out of sprint 8.** Its only consumers are G-06 and G-08; ingest is an admin-surface flow. Shipping it first buys two sprints of an OpenAPI client nothing typechecks against.
- **G-08's cache line was wrong.** Gallery HTML sits behind a session cookie the edge cannot validate, so it is `no-store`. Only the images cache, and their key is version-scoped by path already — so there is nothing to purge on publish.
- **G-11 had a correctness bug.** A 12–30 GB download resumes over HTTP Range, and a resumed transfer is a second request. Counting retrievals per request means two reconnects burn the budget — the exact failure bounded retrievals exist to prevent, and it breaks the promise 12-email already makes ("Bricht der Download ab, starten Sie ihn einfach neu"). Note also that `Storage::download()` drops `Accept-Ranges`, silently turning every resume into a restart.
- **G-09 loses its comments rail** and **G-12 moves to P4.** `AssetComment` emits Engagement events into the unread stream, which is E-06/E-07. Building it in P3 means building it twice.
- **The phase did not fit.** Six L, five M and one S is roughly 43 working days against roughly 40 available. It fits only with those last two moves.

### Sprint split

| Sprint | Stories | Milestone |
|---|---|---|
| 8 — Ingest | G-01a, G-02, G-03 | 30 GB uploads reach a quarantine prefix; derivatives, EXIF, hashes and blurhashes land within the hour |
| 9 — Publishing | G-01, G-05, G-04 | a gallery version is published and immutable; failed assets are visible and re-runnable |
| 10 — Gates and grid | G-08, G-06, G-07 | a guest enters a password and browses 1,200 images from the edge, p95 under 400 ms |
| 11 — Delivery | G-10, G-11, G-09 | password → PIN → email → multi-part download, audited end to end |

Ordering inside sprint 10 matters: G-08 must scaffold the Astro application before G-06 has a password screen to render. Sprint 11's ordering does not. The end-to-end Playwright journey the vision's §8 requires belongs in **sprint 11's** Definition of Done, not sprint 10's — the negative test proving a guest cannot obtain full resolution is only meaningful once the third tier exists.

**Lead-time item:** provision the B2 EU Central account and the bunny.net pull zone, and sign both DPAs, during sprint 8. Sprint 10 cannot start without them.

### Open before P3 starts

Four of the five are cheap; the fifth is not an experiment.

| # | Question | How it closes | When |
|---|---|---|---|
| 1 | The studio's real upstream bandwidth — the only thing between same-day and next-day delivery, and it varies 40× | One `speedtest-cli --json` from the edit workstation plus one timed 30 GB trial upload | Before sprint 8 — it decides whether G-02 needs a resumable-upload UI or a progress bar |
| 2 | ZIP64 support in Explorer, Finder, iOS Files, Files by Google — undocumented by all three vendors | Build a 5 GB and a 3.9 GiB archive, open both on four devices | One afternoon. If 5 GB works, a 30 GB delivery drops from eight parts to three |
| 3 | Real CDN cache-hit ratio under publish-day load — the whole egress argument rests on 80 guests sharing one cached object | The k6 run vision §8 already requires, against a real bunny zone | Sprint 10 |
| 4 | bunny token auth disables IPv6 zone-wide; an IPv6-only NAT64 guest would see a gallery that never loads | Reach a token-authenticated zone from a NAT64 mobile network | Before sprint 10 closes |
| 5 | **Retention policy for originals.** Not an experiment — a studio decision. Without one the vision's own §10 cost criterion fails around year 3, and no provider choice fixes it | How long originals are kept, whether that promise enters the contract, whether replication covers all of them or the most recent N years | Before P3 ships — it determines the prefix layout and lifecycle rules G-01a creates, and moving 15 TB later is not free |

The clickdummy's own open question — **whether three gates is one too many** (clickdummy §5, screen 11) — survives this spike untouched. It needs a real client on a real phone.

---

## P4 — Engagement · Sprints 12–14

| # | Story | Size | Mockup |
|---|---|---|---|
| E-01 | `FormTemplate` builder | L | 13-fragebogen-builder |
| E-02 | `FormInstance` per project, seeded from a template, freely personalised | L | 04-frageboegen |
| E-03 | `FormAnswer` versioned so post-submit edits retain history | M | 04-frageboegen |
| E-04 | `TimelineEntry` shoot-scoped, PDF + ICS export | L | 04-shootings |
| E-05 | `ShotListItem` shoot-scoped | M | — |
| E-06 | `Thread` / `Message` / `ThreadParticipant`, client may initiate | L | 04-kommunikation |
| E-07 | Notification service as the single fan-out choke point | M | — |
| E-08 | `AutomationRule` engine with per-shoot vs. primary-shoot anchors, `AutomationRun` audit, idempotency key | L | — |
| E-09 | Seeded rules from config | M | — |
| G-12 | Favorites and asset comments; Lightroom selection export — moved from P3, sprint 12 | M | 09-raster, 10-lightbox |

G-12 arrives with the lightbox comments rail cut from G-09. Both depend on `AssetComment` emitting into the unread stream E-06 and E-07 build, so they schedule alongside those two rather than ahead of them.

---

## P5 — Migration · Sprints 15–16

| # | Story | Size |
|---|---|---|
| X-01 | Import historical contacts, projects, jobs, contracts | L |
| X-02 | Backfill or link legacy galleries | L |
| X-03 | Cutover runbook and parallel-run period | M |
| X-04 | DSGVO subject-access export and deletion as admin actions | M |

---

## Cross-cutting, every sprint

These are not stories. They are the Definition of Done, and a sprint is not done without them:

- Tests written before implementation; the audience matrix grows with every new route group
- No integer identifier in any Inertia prop, JSON body, URL, email, or filename
- Every table carries `tenant_id` and `public_id` from its first migration
- Conventional Commits; CI green on every job
- Timestamps UTC; `Shoot.date` stays a `date` column
