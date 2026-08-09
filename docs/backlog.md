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
| M-01 | `ContractTemplate` with merge fields, PDF via Gotenberg | L | 07-vertrag |
| M-02 | Simple electronic signature: typed name, explicit consent, SHA-256, IP, UA, template version | L | 07-vertrag |
| M-03 | `PaymentPlan` + `PaymentInstallment`, job-scoped, minor units | M | 04-auftrag |
| M-04 | Stripe Invoicing per installment | L | — |
| M-05 | Webhooks idempotent by event ID; `InstallmentPaid` / `InstallmentOverdue` | M | — |

---

## P3 — Gallery · Sprints 8–11

Preceded by a technical spike (vips throughput, zip streaming at 30 GB, signed URLs vs. CDN caching, real egress cost) as the vision's §9 requires.

| # | Story | Size | Mockup |
|---|---|---|---|
| G-00 | Spike: retire the four unknowns before committing the phase plan | M | — |
| G-01 | `api` surface + Sanctum guest tokens + `packages/contracts` OpenAPI generation in CI | L | — |
| G-02 | Presigned S3 multipart ingest from the admin browser, quarantine prefix | L | 05-medien |
| G-03 | Ingest queue: checksum, EXIF, perceptual hash, libvips derivatives, blurhash; idempotent on SHA-256 | L | 05-medien |
| G-04 | "Needs attention" tray for failed assets | S | 05-medien |
| G-05 | `Gallery` with nullable `shoot_id`; `GalleryVersion` immutable publishing | L | 04-galerien |
| G-06 | Three gates: view password, download PIN, verified email — independently rotatable | L | 08-passwort, 11-pin, 12-email |
| G-07 | Rate limiting per IP *and* per gallery; uniform failure with consistent timing | M | 08-passwort |
| G-08 | Astro gallery: grid, srcset, AVIF/WebP, CDN cache per `gallery_version` | L | 09-raster |
| G-09 | Lightbox with comments rail | M | 10-lightbox |
| G-10 | `ZipBuild` streamed S3→S3, deduped by (version, scope, size), split above threshold | L | — |
| G-11 | `DownloadEvent` audit + signed 24 h links with bounded retrievals | M | 12-email |
| G-12 | Favorites and asset comments; Lightroom selection export | M | 09-raster |

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
