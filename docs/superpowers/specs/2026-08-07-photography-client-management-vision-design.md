# Product Vision — Photography Studio Client Management Platform

**Date:** 2026-08-07
**Status:** Approved vision. Parent document only — each phase gets its own spec and implementation plan.
**Scope:** CRM, client portal (contracts, questionnaires, payments, messaging), and client/guest photo gallery for a wedding and portrait photography studio.

---

## 1. Context and constraints

| Dimension | Decision |
|---|---|
| Tenancy | Single-tenant for the studio now, with tenant-shaped seams so SaaS is an activation rather than a rewrite |
| Replaces | A paid all-in-one (Pixieset / Studio Ninja class) **and** spreadsheets, email, WeTransfer |
| Integrates | Stripe for payments; no in-house invoicing engine or tax logic |
| Volume | ~50–100 weddings and ~200 portrait sessions per year; 500–1500 images per wedding; roughly 3–4 TB of originals per year |
| Team | Multi-user studio: owner, photographers, second shooters, editors, assistants |
| Region | EU hosting and EU processors throughout (DSGVO) |

### Non-goals

Print or product store · album designer · accounting and bookkeeping (CSV/DATEV export only) · Lightroom plugin · IMAP/email inbox sync · AI culling or tagging · native mobile apps (responsive web only) · qualified eIDAS signatures · multi-currency · public marketing site · SaaS productization (tenant seams are kept because they are cheap; launching as a product is a separate business decision).

---

## 2. Architecture

Chosen approach: **one Laravel application serving the staff and client surfaces through Inertia, a separate Astro application serving the guest gallery, in one monorepo.** One image, one surface per deploy.

### Applications

| App | Stack | Deploy target | Audience |
|---|---|---|---|
| `apps/studio` | Laravel 11, Inertia, Vue 3, shadcn-vue, Postgres, Horizon, S3-compatible storage | Three containers from one image — `APP_SURFACE=admin` (private, MFA and network-restricted), `APP_SURFACE=portal` (public), `APP_SURFACE=api` (public JSON, gallery-facing); queue workers scaled separately and load no routes at all | Staff, booked clients, and the gallery |
| `apps/gallery` | Astro (SSR + aggressive CDN caching) | Public edge | Clients and wedding guests |
| `packages/contracts` | Generated OpenAPI spec + TypeScript client | In-repo package, never published | Consumed by the gallery only |

```
client-management-portal/          single git repo
├── docs/superpowers/specs/        vision and per-phase specs
├── apps/
│   ├── studio/
│   │   ├── routes/                exactly one is loaded, chosen by APP_SURFACE
│   │   │   ├── admin.php          staff, Inertia
│   │   │   ├── portal.php         clients, Inertia
│   │   │   └── api.php            gallery-facing JSON
│   │   ├── resources/
│   │   │   ├── views/             app-admin.blade.php, app-portal.blade.php
│   │   │   └── js/
│   │   │       ├── admin/         Vite entrypoint and pages
│   │   │       ├── portal/        Vite entrypoint and pages
│   │   │       ├── ui/            shadcn-vue primitives, shared
│   │   │       └── types/         generated.d.ts
│   │   └── components.json        shadcn-vue registry
│   └── gallery/
└── packages/
    └── contracts/                 build artifact, regenerated in CI
```

### Surface separation

The three surfaces share a codebase but never share a process. `bootstrap/app.php` reads `config('surface.name')` and loads exactly one route file. The admin container does not merely deny portal routes — it has never registered them. Reaching client-portal code from the admin surface requires a deploy misconfiguration rather than a middleware typo, and the difference matters because middleware typos are the failure mode that actually occurs.

Session cookie name, session domain, CSP, rate limits, Vite entrypoint, and root Blade view are all per-surface. Blast radius, network restriction, and release cadence therefore remain per-surface properties, exactly as they were when these were separate applications — that isolation is a deploy property, not a repository property. Three containers of one small image cost less than the coordination machinery three codebases would demand.

### Rationale

Laravel supplies queues (Horizon), scheduling, mail, policies, PDF tooling, and Stripe integration as inherited infrastructure rather than assembled parts — roughly six months of plumbing that a Node backend would require building.

Inertia serves admin and portal because both are authenticated, non-indexed, forms-over-data surfaces. That is precisely the profile where a standalone SPA charges the most and returns the least: a JSON resource layer, CORS configuration, stateful-cookie negotiation, and a codegen step, all to render pages that no search engine will ever see and no anonymous visitor will ever load. Inertia deletes that boundary. Controllers return typed props; there is no API to version.

Astro keeps the gallery because that surface has the opposite profile — anonymous, read-heavy, image-dominated, and CDN-cached. Shipping near-zero JavaScript matters when guests load a 1500-image gallery on venue wifi, and Inertia page responses do not edge-cache the way static Astro HTML does. The gallery is consequently the only remaining consumer of the JSON API.

**What this costs.** Optimistic and offline-first interactions are off the table, and every navigation is a server round trip; Inertia partial reloads narrow this but do not remove it. Admin and portal now share a deployable artifact, so a bug in shared domain code reaches both — mitigated by separate processes, separate guards, and separate route files, not by repository layout.

### Contract discipline

Two boundaries, each mechanically enforced in CI.

**PHP → TypeScript (admin and portal).** Controllers pass `spatie/laravel-data` objects as Inertia props rather than arrays. `spatie/typescript-transformer` generates `resources/js/types/generated.d.ts` from those classes, and Vue pages type their props against it. Renaming a field in PHP fails `vue-tsc` in the same pull request. CI regenerates and fails on a stale committed file.

**PHP → OpenAPI → TypeScript (gallery).** CI regenerates the OpenAPI document from the API routes and the TypeScript client in `packages/contracts` on every run; a breaking change fails the gallery's typecheck in the pull request that introduced it. No publishing, no pinning, no drift window.

One CI pipeline with per-app jobs: lint → test → build → deploy changed apps on tag.

### Component system

shadcn-vue is copied into the repository rather than installed, so `resources/js/ui` holds one copy of the primitives and both surfaces import it. The two surfaces diverge through Tailwind CSS-variable themes only — admin dense and utilitarian, portal spacious and studio-branded. A single registry means an accessibility fix to a dialog lands in both surfaces at once; a token swap means the client never sees an interface that looks like an internal tool.

---

## 3. Domain model

Five bounded contexts, implemented as Laravel modules with separate namespaces. No cross-context database joins — contexts reference each other by ID and communicate through domain events.

### 3.0 Identifier convention

Every table has two identifiers. An auto-incrementing `bigint` primary key stays internal and carries all foreign keys and joins. A unique `char(24)` `public_id` holding a CUID2 is the only identifier that leaves the server — in an API response, an Inertia prop, a URL, an email, or a filename.

Sequential integers would otherwise be enumerable in exactly the places that matter most here — a guest who can see `/gallery/41` can try `/gallery/42`, and a client who can see their own project ID learns roughly how many clients the studio has. CUID2 was chosen over ULID because ULIDs encode their creation timestamp in the first 48 bits, which would publish when each record was created.

Because the risk in this design is not the scheme but the discipline it demands, two mechanical guards are requirements rather than conventions:

1. The shared `HasPublicId` trait adds `id` to the model's hidden attributes and overrides `getRouteKeyName()` to return `public_id`, so route-model binding cannot silently bind on the integer.
2. A test walks every implemented endpoint's response body — JSON resources on the `api` surface and Inertia page props on `admin` and `portal`, both of which serialize as JSON — and fails if any key named `id` holds an integer. Passing a model straight into `Inertia::render` instead of wrapping it in a Data object is the ordinary way this leaks, and it should fail in CI rather than in review.

Internal identifiers may appear in server-side logs and the audit log's foreign keys. They must never appear in an API response, an Inertia prop, a URL, an email, or a filename.

### 3.1 CRM

- `Contact` — a person; role-agnostic. `email` is nullable and unique per tenant where present
- `Project` — the relationship, from inquiry to archive; owns contacts, conversation, and shoots. `portal_visible` controls whether it appears in the client portal
- `Lead` — the pursuit: `source`, `stage`, `lost_reason`, inquiry text. One per project, created at inquiry
- `Job` — the commitment: `type` (wedding, portrait, other), `booked_at`, `archived_at`. At most one per project, created at conversion
- `Shoot` — one shooting occasion: `date`, `call_time`, `end_time`, `kind`, `label`, venue, `status`, `is_primary`, `sort_order`
- `ProjectContact` — pivot carrying role: primary, partner, payer, planner, vendor
- `CrewAssignment` — staff user ↔ **shoot** ↔ role

A wedding has many people attached to it with different roles: the couple books, a parent may pay, a planner needs the timeline, guests view the gallery. Modeling one `Project` with many typed contacts — rather than a `Client` with a date field — is what lets portrait and wedding work share a schema.

**Portal access is a person's, not a project's.** `ClientAccess` is contact-scoped (§3.5), so a repeat client — a portrait sitting in 2025, a wedding in 2027 — signs in once and switches projects, and each partner signs in as themselves rather than sharing one project identity. What a signed-in contact may see is derived rather than stored:

```sql
select p.* from projects p
join project_contacts pc on pc.project_id = p.id
where pc.contact_id = :authenticated_contact
  and p.portal_visible;
```

`portal_visible` defaults to false. A raw inquiry must not surface in a portal the moment it arrives; `ProjectBooked` flips it true, staff can flip it earlier to share a quote, and flipping it back retires a finished project from the portal without destroying anyone's login.

`Contact.email` is nullable and carries a partial unique index (`unique (tenant_id, email) where email is not null`). A couple sharing one inbox is one contact holding that address; a partner with their own address is a second contact; a contact with no address — a venue coordinator recorded for reference — simply has no portal access, which is honest, since there is no way to send them a link. The alternative, letting an address repeat, makes a magic link resolve to more than one person and leaves "who signed this contract" without an answer.

**Three entities, because the client relationship outlives both the pursuit and the booking.** `Lead` is the pursuit, `Job` is the commitment, `Project` is the relationship that spans them. The conversation, the client's portal login, the contacts, and the requested dates all begin at "hello" and continue to archive; anchoring them to `Lead` loses them at booking, and anchoring them to `Job` means they do not exist for the weeks when the client is actually deciding.

The cost is real and worth naming: `Lead` is thin, and "show me this client" hops `Project → Lead` to read the funnel stage. The alternative — collapsing `Lead` into `Job` so one row spans inquiry to archive — trades that hop for a table named `Job` that mostly holds unbooked inquiries. Three explicit entities keep each name honest.

**Booking and shoot are different units.** One project routinely spans an engagement shoot, the wedding day, the reception, and an after-wedding shoot, separated by as much as a year. `Shoot` is the production unit — a date, a place, a crew, and its own pipeline. A portrait project is the degenerate case: one shoot.

Neither `Project` nor `Job` carries a pipeline status. A project whose engagement shoot is delivered while the wedding day is unshot has no single pipeline state, and inventing one produces a status that is wrong for most of a wedding's eighteen-month life. Board state is derived from the shoots (§7).

**Shoots hang off `Project`, not `Job`**, so a requested date and a booked date are the same row in two states rather than two records kept in sync. `Shoot.status` therefore begins at `requested`; conversion moves a project's shoots to `booked`. Date-availability checks scan `shoots.date` for projects that are not lost — one indexed scan, and the row checked at intake is the row later booked.

**Exactly one shoot per project is primary**, enforced by a partial unique index (`unique (project_id) where is_primary`). The primary shoot is the headline occasion — the wedding day — and is what project-level automations and payment schedules anchor to.

`is_primary` is set at intake, not derived. The inquiry form has one required main date and an optional repeater for additional shoots; the main field's row is the primary one, and staff can change it. A derivation rule — earliest date, or the row whose kind matches the project type — would be wrong for exactly the case that motivates the model, since the engagement shoot is the earliest date and is never the headline.

### 3.2 Sales

- `Package`, `PackageItem` — the catalog; a starting point, never a live dependency
- `Quote` (Angebot) — **lead-scoped**; the offer actually made to this client
- `QuoteItem` — one line of that offer, priced or included
- `Contract` — **job-scoped**; rendered PDF, template version, merge data, `signed_at`, `signer_ip`, `user_agent`, `document_sha256`
- `PaymentPlan`, `PaymentInstallment` — **job-scoped**; due date, amount, Stripe references, status

A quote is an artifact of the pursuit and belongs to the `Lead`; a lost lead keeps its quotes as the record of what was offered. A contract and its payment plan are artifacts of the commitment and belong to the `Job`, which does not exist until there is one.

**Snapshot rule:** quotes and contracts store price and terms snapshots; they never join live to `Package`. Changing a package price next year must not alter last year's contract.

### 3.2.1 Packages and the Angebot

**A quote is derived from a package, then extended to the client.** Staff pick a catalog package, which copies its items into the quote, and then edit freely — add the second shooter, drop the album, extend coverage by two hours. From the moment of copying there is no link back: `Quote.derived_from_package_id` records provenance for reporting ("which package do people start from") and is never read for pricing. Every wedding is quoted slightly differently, so a catalog that must express every variation as a configurable option becomes a configurator nobody maintains. A template plus free editing costs one copy step and no rules.

**`QuoteItem.unit_price` is nullable, and that is what makes the German Angebot shape work.** A priced line contributes to the total. A null-priced line is an *Enthaltene Leistung* — listed so the client sees it, not separately charged. Selling a flat-rate package is therefore one priced line at €2,400 plus a dozen inclusions at null, and selling an itemised quote is the same table with prices on every row. No second concept.

```
Package        name, public description, internal notes, is_active,
               default_validity_days, sort_order
PackageItem    label, description, quantity, unit_price (nullable), sort_order

Quote          lead_id, derived_from_package_id (nullable, provenance only)
               number            Angebotsnummer, e.g. AN-2026-0042
               status            draft → sent → accepted | declined | superseded
               valid_until       date
               sent_at, accepted_at, accepted_by_contact_id, accepted_ip
               discount_label    nullable, e.g. "Winterrabatt"
               discount_percent  numeric(5,2) nullable — display only
               discount_amount   bigint default 0 — resolved, authoritative
               subtotal_net, net, tax_rate, tax_amount, gross
                                 snapshot, minor units, EUR
QuoteItem      quote_id, label, description, quantity,
               unit_price (nullable), sort_order
               discount_percent  numeric(5,2) nullable — display only
               discount_amount   bigint default 0 — resolved, authoritative
               line_net
```

**Discounts exist at both levels, absolute or relative, and always resolve to an amount.** A line may be discounted and the quote as a whole may be discounted; both store `discount_percent` for the label the client reads ("10 % Rabatt") and `discount_amount` for the arithmetic. The amount is authoritative in every case — an absolute discount leaves `discount_percent` null, and a relative one sets both. Nothing downstream ever multiplies by a percentage, which is what keeps the total on the rendered Angebot equal to the total in the database to the cent.

Order of operations, which matters because VAT is charged on what the client actually pays:

```
line_net      = quantity × unit_price − line.discount_amount
subtotal_net  = Σ line_net
net           = subtotal_net − quote.discount_amount
tax_amount    = round(net × tax_rate)          half up, once, on the total
gross         = net + tax_amount
```

The discount comes off the net and VAT is computed after it, never before. Rounding happens once on the total rather than per line, so a twelve-line quote cannot drift a few cents away from its own sum.

Two constraints, because this is a money path: `net >= 0`, so a discount can never exceed what is being discounted, and a line discount is only valid where `unit_price` is not null — an *Enthaltene Leistung* has no price to reduce.

While a quote is `draft`, a percentage recomputes its amount on every edit. On send, everything freezes with the rest of the snapshot. Changing a sent quote means issuing a new one, so there is no path where a stored percentage and a stored amount can disagree.

**Expiry is derived, not stored.** A quote is expired when `valid_until < today`; there is no `expired` status and no nightly job to set one. Acceptance re-checks validity server-side, so a stale link cannot be used to accept last spring's price.

**Revisions are new rows.** "Can we drop the album?" produces a second `Quote`; the first becomes `superseded`. The offer history is the row history, which is what answers "what did we actually offer them in March". No versioning machinery, no `supersedes_id` — creation order is enough.

**Portal visibility follows status.** The client sees exactly one quote: the `sent` one while a decision is pending, then the `accepted` one as the standing record of what was agreed, with its inclusions listed. Drafts, declined, and superseded quotes never leave the admin surface.

**Money is stored in minor units** (`bigint` cents, EUR only per §1's non-goals). Stripe's API speaks minor units, so this removes a conversion at the single boundary where money leaves the system — the boundary where a rounding bug would be a charging bug.

**VAT is snapshot display, not tax logic.** A tenant setting carries `vat_mode` (`standard` or `small_business`) and `vat_rate`, defaulting to 19%. The quote stores the rate it was sent under alongside the computed tax and gross. Under `small_business` the rate is zero and the rendered Angebot carries the required §19 UStG note. What is actually charged remains Stripe's concern — this is what the document must show, which is a different problem, and the one that has to be right at the moment the client reads it.

**`Quote.number` is a business document number and deliberately sequential** (`AN-{year}-{counter}` per tenant), which sits in tension with §3.0's rule against enumerable identifiers. The tension resolves by separating the two: the quote's URL and every API reference use its `public_id` CUID2, while `number` exists only on the rendered document and in staff search. A sequential Angebotsnummer discloses roughly how many quotes the studio has issued this year — a disclosure every German business makes on every document it sends, and not the same risk class as a guessable URL.

**Acceptance hands off to the contract.** Accepting records `accepted_at`, `accepted_by_contact_id`, and `accepted_ip`, and advances `Lead.stage` to `quote_accepted`. `Contract` is then rendered from the accepted quote and carries `quote_id`, so the signed document and the accepted offer are traceable to each other. Acceptance is not a signature and is not treated as one; its evidentiary weight comes later, from §7's signing flow.

**Packages do not touch shoots.** `PackageItem` has no `shoot_kind`, and accepting a quote creates no shoots and changes none. The shoots a client requested at inquiry stay exactly as requested, and staff reconcile coverage against the accepted quote by reading both. Stated plainly, the cost: a client who requested a fourth shoot the accepted package does not cover is caught by a human noticing, not by the system. Automating that check means teaching the catalog about shoot kinds, and it is worth doing only once the catalog has stabilised enough to be worth teaching.

**Not built:** client-selectable options or add-ons in the portal (staff send one offer, tailored), coupon codes and automatic discount rules (discounts are a number staff type, not a rules engine), multi-currency, and any recomputation of totals after send — a sent quote is immutable, and changing it means issuing a new one.

### 3.3 Engagement

**Structured forms (studio → client)**
- `FormTemplate` — JSON schema of typed questions per shoot type
- `FormInstance` — project-scoped, with `sent_at` / `opened_at` / `completed_at` and a reminder schedule
- `FormAnswer` — one row per field, versioned so post-submit edits retain history
- `TimelineEntry`, `ShotListItem` — **shoot-scoped** specialized forms; the timeline exports to PDF and ICS for crew

A running order belongs to a shooting day, not to a project. The wedding day has a timeline; the engagement shoot has a shot list and no timeline at all.

**Conversation (bidirectional, client may initiate)**
- `Thread` — project-scoped, subject, status (open / awaiting_client / resolved), `last_activity_at`
- `Message` — polymorphic author (staff `User` or `Contact`), sanitized markdown body
- `MessageAttachment` — S3-backed, allowlisted MIME types, size-capped
- `ThreadParticipant` — polymorphic (`User` or `Contact`) with per-participant `last_read_at`, driving unread badges on both sides

A client "simple question" creates a thread from the portal. Email notifications link back to the portal; inbound email replies are deliberately not parsed.

Authorship is a `Contact`, not a `ClientAccess`. `ClientAccess` is a credential — it can be revoked and reissued, and it may not exist at all for a contact who never signed in. A message is written by a person and must still name them a year after their login was revoked, so the author points at the person and the credential stays out of the domain record entirely.

**Internal notes (staff only)**
- `InternalNote` — attaches to `Project`, `Lead`, `Job`, `Contact`, or `Thread`; author, body, pinned flag

This is a separate table and model rather than an `is_internal` flag on `Message`. With a flag, every query needs a correct `where` clause and one missing scope leaks private commentary into the portal. With separate models, portal-facing route groups have no binding to `InternalNote` at all, and a test asserts no portal response schema references it.

**Tasks**
- `Task` — project-scoped with a nullable `shoot_id`, `audience` (internal or client), assignee, due date, status, source (manual, template, automation)
- `TaskTemplate` — a set applied when a shoot enters `booked`, selected by shoot kind, with due dates relative to that shoot's date (`-30d`, `+14d`)

Templates fire on the transition into `booked`, not on shoot creation. Shoots exist from inquiry in the `requested` state, and generating a prep checklist for an inquiry that may never convert would fill the task list with work for weddings that are not happening. A shoot added to an already-booked project gets its templates the moment it is booked, by the same rule.

`shoot_id` is nullable because the two kinds genuinely differ: "sign the contract" belongs to the project, "pack the second body for the reception" belongs to a day. A template set applied per shoot means a four-shoot wedding gets four rounds of prep tasks on four schedules, which is the actual work.

Client-facing tasks render in the portal as a checklist — sign contract, complete timeline form, pay second installment — created and completed by domain events, so the client's to-do list is derived state rather than manual bookkeeping.

**Notifications** fan out through a single service: staff receive digests and realtime alerts, clients receive transactional email pointing at the portal. One choke point so quiet hours and rate limits are enforced in one place.

### 3.4 Media

- `Gallery` — project-scoped with a nullable `shoot_id`; name, cover, access policy, `expires_at`, `requires_settled_balance`
- `GalleryVersion` — immutable published set
- `Asset` — **shoot-scoped**; original S3 key, checksum, EXIF, capture time, original filename
- `AssetDerivative` — size variant, S3 key, dimensions, bytes
- `GallerySection` — getting ready, ceremony, reception, and so on
- `Favorite` — per viewer identity
- `AssetComment` — anchored to an asset; emits Engagement events so it joins the same unread stream
- `DownloadEvent` — append-only: who, what, when, IP
- `ZipBuild` — async artifact with expiry

Galleries are versioned, never mutated. When a photographer re-uploads corrected edits after 300 downloads have happened, an in-place mutation would invalidate the download audit and detach client favorites from what they actually favorited. Publishing creates `v2`; favorites and history stay pinned to the version they referenced, while clients always land on the latest.

**Gallery grouping is the client's choice, expressed by one nullable column.** `Gallery.shoot_id` set means a gallery for that shoot; null means a gallery for the whole project. A client wanting one gallery per shoot gets four rows with `shoot_id` set; a client wanting everything together gets one row with it null; a client wanting the engagement shoot separate and the rest combined gets one of each. The column is a label and an upload default, not a membership rule — assets are assigned to gallery versions explicitly, so a gallery covering exactly two of four shoots needs no schema at all and uses `GallerySection` to group internally.

What a separate gallery actually buys is separate access control and separate delivery timing: the engagement gallery ships in March with its own password, its own expiry, and its own audience, months before the wedding gallery exists. That is why the split lives on `Gallery` rather than being expressed only through sections.

`Asset.shoot_id` is where the real association lives, because an import belongs to one shooting day. Composing a gallery is then a query over a shoot rather than manual sorting of 1500 files, and "every frame from the reception" stays answerable however the client chose to group their galleries.

`requires_settled_balance` makes §7's payment gate per-gallery. Blocking the engagement gallery in March because the wedding balance is outstanding would be absurd; blocking the final wedding gallery is the point.

### 3.5 Access

- `User` — staff
- `ClientAccess` — **contact-scoped**, email-bound magic-link identity; one per person, not one per project
- `GuestIdentity` — email, `verified_at`, `consent_source`, `retention_until`; deduped per gallery, reusable if the person returns
- `DownloadRequest` — gallery version, scope, requester, token hash, `expires_at`, `consumed_at`, IP
- `AuditLog` — append-only

### 3.6 Cross-context events

`ProjectBooked`, `ContractSigned`, `ShootDelivered`, `GalleryPublished`, `InstallmentPaid`, `InstallmentOverdue`, and similar. Listeners send mail, advance pipeline stages, and unlock galleries. CRM stays ignorant of Media internals, which is what allows Media to scale or split out later.

`ProjectBooked` is the conversion event: it carries the new `Job` and the shoots that moved from `requested` to `booked`. Task templates and date-offset automations subscribe to it, and it sets `Project.portal_visible` if staff have not already.

---

## 4. Identity and authentication

One host per surface: `admin.studio.tld`, `my.studio.tld`, `api.studio.tld`, `gallery.studio.tld`.

Admin and portal are Inertia surfaces served by Laravel itself, so they use ordinary session guards. Sanctum's stateful-SPA mode, the CSRF-cookie handshake, and cross-origin configuration disappear from both — Inertia's `XSRF-TOKEN` handling is the whole story. Sanctum survives only on `api.studio.tld`, where the gallery's guest tokens and service key live.

| Audience | Surface | Credential | Session |
|---|---|---|---|
| `staff` | admin (Inertia) | Password + TOTP (mandatory) | Laravel session cookie `studio_admin_session`, host-scoped; 12 h idle, 7 d absolute |
| `client` | portal (Inertia) | Login form → magic link, bound to one `Contact` by email, single-use, 15 min TTL | Laravel session cookie `studio_portal_session`, host-scoped; 30 d, revocable; expires 90 d after the last visible project is archived |
| `guest` | gallery (Astro → API) | Gallery password | Sanctum guest token, 7 d gallery-scoped, revoked on password rotation |
| `service` | Astro SSR → API | Read-only key, gallery-metadata scope | Cannot mint sessions, cannot read full-resolution keys |

Cross-tier leaks are prevented at **two independent layers**. First, the surface itself: a `staff` route does not exist in the portal or api container, so there is nothing for a client or guest credential to reach. Second, within a surface, the required guard is declared at route-group middleware rather than in per-controller checks, so a wrong-audience credential is rejected before controller dispatch. Policies are too deep in the stack to serve as a first line of defense; they are the fine grain, not the gate.

Because the cookies are host-scoped and named differently, a portal session is not merely rejected on the admin host — it is never transmitted to it.

The Astro SSR server is a confused-deputy risk: it calls the API on behalf of anonymous visitors. It forwards the visitor's own guest token and holds nothing stronger than a read-only metadata key, so a template bug cannot become full data exposure.

Staff authorization uses Laravel policies over project ownership and crew assignment. A second shooter sees assigned shoots, not the whole book; an editor sees media, not payments. Roles are coarse; policies are the fine grain.

**Rejected:** social login, client passwords (magic links are simpler and harder to phish at this scale), shared studio accounts (they destroy the audit trail).

### Security requirements

1. Every route group declares its required audience in middleware, verified by an explicit test matrix of every route group × every audience. A second test boots the application once per `APP_SURFACE` value and asserts that no other surface's routes are registered — the surface gate is a claim about the route table, so it is tested against the route table.
2. Magic links are self-service: the portal shows a login form where the client enters their email and receives a fresh link on demand. An expired link is therefore a re-request, not a support ticket. A link resolves to exactly one `Contact`, guaranteed by the partial unique index on `(tenant_id, email)` — a couple sharing an inbox shares one contact record, and a partner wanting their own sign-in provides their own address. Links are single-use; consuming one invalidates all outstanding links for that address. The form responds identically for known and unknown emails (no account enumeration), and link requests are rate-limited per address and per IP. Store and compare hashes — never log link values.

   A signed-in contact sees the projects they are attached to and that are marked `portal_visible`, evaluated per request from `project_contacts`. The session carries an identity, never a project list — otherwise removing someone from a project would leave their existing session still holding it.
3. Staff MFA is mandatory. The admin surface is additionally restricted at the network edge (IP allowlist or VPN) where practical, since it can export every client's data.
4. Revocation is immediate, and it comes in two forms that are not interchangeable. **Hiding a project** (`portal_visible = false`) removes it from every participant's portal at the next request, along with its galleries, magic links, and unconsumed download links; sessions survive, because a planner cut from one project must not be signed out of another. **Revoking a contact's access** deletes their `ClientAccess`, kills their sessions everywhere, and invalidates every outstanding link for that address — this is the control for a link that reached the wrong person. Both write to the audit log, and the admin UI must not present them as one button, since staff reaching for the second one are handling an incident.
5. All authentication events — success, failure, lockout, revocation, PIN rotation, download-link consumption — write to an append-only audit log that staff cannot edit and that outlives the record it describes.
6. Guest capabilities are derived server-side from stored gallery policy. Size, scope, and expiry are never read from client input.

---

## 5. Gallery access model

Three independent gates:

| Gate | Secret | Grants |
|---|---|---|
| View | `Gallery.view_password_hash` (nullable) | Browse the gallery and view web-size images; sets a signed, expiring gallery-session cookie |
| Download | `Gallery.download_pin_hash`, independently rotatable | Unlocks the download action for the session; re-prompted after rotation |
| Delivery | Verified email address | Receives the actual download link, expiring and bound to that address |

Separating *seeing* from *taking* matters because the couple will share the password with eighty guests but not the PIN, and rotating an over-shared PIN must not evict everyone from viewing.

**Flow:** open link → password → browse → request download → PIN → enter email → server queues or reuses a `ZipBuild` → email delivers a signed URL → click writes a `DownloadEvent` with email, IP, scope, and timestamp.

Additional gallery fields: `guest_download_size` (none | web | full), `client_download_size`, `pin_rotated_at`, `expires_at`.

### Security requirements

A download PIN of 4–6 digits is only 10,000–1,000,000 combinations and is brute-forceable in seconds unprotected. Throttling is therefore a requirement, not a hardening extra.

1. Rate-limit password and PIN attempts per IP **and** per gallery — roughly five attempts, then exponential backoff; lock PIN entry and alert the photographer after about twenty failures.
2. Return a uniform failure response with consistent timing. Never let the response distinguish a wrong password from a nonexistent gallery.
3. Email-delivered download URLs are signed, expire after 24 h, and are not derivable from the gallery URL. Each link permits a bounded number of retrievals (default 3) rather than one, since a single-use link breaks on a failed 30 GB transfer; exhausting the count requires requesting a fresh link by email.
4. Full-resolution originals are only ever served through signed URLs minted after gate checks. Guests default to web size, and the guest tier must be structurally incapable of requesting full size — enforced from stored policy, never from a request parameter.
5. Emails collected at the download gate carry an explicit purpose statement and a retention period, with photographer-accessible export and deletion for DSGVO requests.

---

## 6. Media pipeline and delivery

### Ingest

```
admin browser ──presigned multipart──▶ S3 (originals bucket, quarantine prefix)
      │ completion callback
      ▼
  apps/studio ──dispatch──▶ Horizon queue: media.ingest
      ├─ verify checksum and size; reject non-image MIME
      ├─ extract EXIF (capture time, camera, lens) → default sort order
      ├─ perceptual hash → duplicate detection within the shoot
      ├─ derivatives via libvips: thumb 400 / web 1600 / preview 2560
      ├─ blurhash placeholder (instant paint, no layout shift)
      └─ move original from quarantine to permanent prefix; write Asset + AssetDerivative
```

Uploads never pass through Laravel. At roughly 30 GB per wedding, presigned S3 multipart directly from the admin browser makes the API a coordinator that issues part URLs and reacts to completion.

Jobs are idempotent, keyed on the SHA-256 of the original, because a 1200-file upload will partially fail sometimes and re-running must be safe. Failed assets appear in an admin "needs attention" tray, not a log file.

libvips is chosen over Imagick for roughly 4–8× the throughput at a fraction of the memory. Derivative workers run as a separately scaled deployment so a large ingest never starves request handling.

### Storage and tiering

```
originals/{project_public_id}/{shoot_public_id}/{asset_public_id}.jpg
                                             hot 90 d → infrequent access → archive at delivery + 18 mo
derivatives/{asset_public_id}/{size}.webp|jpg  hot always, small
zips/{gallery_version_public_id}/{scope}-{size}.zip
                                             lifecycle delete after 14 d
```

Object storage is zero-egress (Cloudflare R2 or Backblaze B2 behind Bunny). Egress, not storage, is the dominant cost — one wedding gallery shared with eighty guests can move more bytes in a weekend than its originals occupy. With zero-egress storage the yearly bill is storage-shaped; on conventional S3 it is traffic-shaped and unpredictable.

### Delivery

Astro renders the gallery shell with a responsive `srcset` of web and preview sizes, AVIF with WebP fallback.

Signed URLs and CDN caching work against each other, since a per-request signature is a unique cache key that forces every guest back to origin. The tiers are therefore split:

- **Web size** is served from the CDN at an unguessable immutable UUID path with `Cache-Control: public, max-age=31536000`. This is a capability URL — whoever holds it can view that one image. Acceptable for web size, explicitly not for originals.
- **Full resolution** is served only via short-TTL signed URLs minted after the PIN and email gates, marked `no-store` and never edge-cached.

Gallery HTML is cached per `gallery_version` and purged on publish.

### ZIP builds

Streamed S3 → S3 using zip64 with no local disk, so memory stays flat regardless of gallery size. Builds are deduped by `(gallery_version, scope, size)` — eighty guests requesting the same set build it once and share links to one object. Since a 30 GB single archive is hostile to consumer connections, builds split automatically by section above a size threshold and the delivery email lists the parts.

### Lightroom seam

No plugin in v1. Selection export produces a plain filename list and optional `.lrsmartcol` for pasting into a Lightroom filter — cheap, no plugin maintenance, and it covers the actual workflow.

---

## 7. Journey, automation, payments, contracts

### Two state machines

`Lead.stage` is a sales funnel: `new → contacted → quote_sent → quote_accepted → contract_sent → booked`, with `lost(reason)` reachable from any stage. `quote_accepted` exists as its own stage because "they said yes, send the contract" is a distinct thing for staff to act on, and folding it into `quote_sent` hides the one moment in the funnel where the studio is the bottleneck. Date-availability checks at intake scan `shoots.date` for projects whose lead is not lost.

`Shoot.status` is a forward-only production pipeline: `requested → booked → prep → shot → culling → editing → ready → delivered → archived`. Each transition emits a domain event.

Keeping these separate avoids a fourteen-state enum in which half the transitions are illegal.

**Conversion** happens when the contract is signed and the deposit is paid. It creates the `Job` under the existing `Project` and moves that project's shoots from `requested` to `booked`. Nothing is copied and nothing is retyped: a shoot is created at inquiry with the date the client asked for, and booking changes its state rather than producing a second record. The availability check that ran at intake ran against the row that is now booked.

**The pipeline is per shoot, not per project.** The four shoots of a wedding run their pipelines independently and months apart — the engagement shoot reaches `delivered` while the wedding day is still `booked`.

Board state is therefore derived rather than stored:

```sql
select distinct on (p.id) p.id, s.status, s.date
from projects p
join jobs j on j.project_id = p.id          -- unbooked projects excluded here
join shoots s on s.project_id = p.id
where s.status not in ('delivered', 'archived')
order by p.id, s.date;
```

The join to `jobs` is what keeps `requested` shoots off the production board: a project that has not converted has no job to join to. One clause, one place, rather than a `where` guard repeated at every site that reads shoots.

A project sits in the column of its **earliest incomplete shoot**, sorted within that column by that shoot's date, and leaves the board when every shoot is delivered. The board shows one expandable card per project — client and money context stay visible — with its shoots as sub-rows.

That a card can move backwards is correct, not a defect. When the engagement shoot is delivered, the wedding day becomes earliest-incomplete and the project returns to `booked`. The column answers "what is the next thing this project needs", which is the question the board exists to answer.

Nothing about this is denormalised. There is no rollup column to drift, no event needed to maintain it, and no reconciliation job — the board is a query.

### Automation engine

An `AutomationRule` is data, not a code path: trigger plus conditions plus actions.

- **Triggers:** domain events, or date offsets relative to a `Shoot.date` evaluated by a daily scheduler
- **Actions:** send templated email, create a form instance, create a task, advance a stage, notify staff
- **Auditing:** every firing writes an `AutomationRun` row recording rule, target, and outcome — without it, "why did the client get that email twice" is unanswerable
- **Guard:** an idempotency key per (rule, shoot) so a double scheduler run cannot double-send

Each rule declares whether it anchors to **its own shoot** or to the **primary shoot**. Per-shoot rules fire once per shooting day: a four-shoot wedding gets four crew briefs at −1 d, which is right, because there are four days to brief for. Booking-level rules anchor to the primary shoot and fire once: one payment reminder at −14 d, not four.

Getting this wrong is the difference between a client receiving one balance reminder and four, so the anchor is a required field on the rule rather than a default.

Seeded rules, with anchors: inquiry auto-reply · stale-lead nudges at 3 and 7 days · booking confirmation and welcome packet · timeline form at −30 d *(per shoot)* · payment reminder at −14 d *(primary)* · crew brief at −1 d *(per shoot)* · delivery email on publish · review request at +14 d *(primary)* · archive at +90 d after the last shoot is delivered *(booking)*.

No visual rule builder in v1. Rules are seeded from config and become editable once the five rules actually used are known.

### Payments

Each `PaymentInstallment` becomes a Stripe Invoice. Stripe owns the hosted payment page, card and SEPA handling, dunning email, VAT, and receipts — building EU tax handling and reminder logic in-house is months of work with tax-level consequences for getting it wrong.

Webhooks (`invoice.paid`, `invoice.payment_failed`) reconcile back to the installment and emit `InstallmentPaid`, which completes the client task and optionally unlocks the gallery. Webhook handling is idempotent by Stripe event ID. Stripe, not the local record, is the source of truth for payment state.

An optional gate blocks gallery download until the balance is settled. It is set per gallery via `Gallery.requires_settled_balance`, defaulting to off. Payment plans run against the `Job`, so a project-wide gate would hold back an engagement gallery in March over a balance not due until September.

### Contracts

A `ContractTemplate` with merge fields renders to PDF via headless Chrome (Gotenberg). The client reviews it in the portal, types their full name, and ticks an explicit consent checkbox; a signature block is appended, the PDF is hashed with SHA-256 and stored immutably alongside `signed_at`, `signer_ip`, `user_agent`, and the exact template version. Both parties are emailed the countersigned PDF.

**Legal standing:** this is a *simple electronic signature* under eIDAS — admissible as evidence in the EU and standard practice for photography contracts, but not a qualified electronic signature and without its presumption of authenticity. The audit trail is what gives it evidentiary weight, which is why those fields are requirements rather than conveniences. Qualified signatures, if ever needed, are an integration with a trust service provider, not something to build.

---

## 8. Non-functional requirements

### Testing

| Target | Strategy |
|---|---|
| `apps/studio` (PHP) | Pest; feature tests over real HTTP and Postgres. Audience matrix test covering every implemented route group × every credential audience, growing as audiences ship. Policy tests per role. Stripe webhook fixtures replayed. Queue jobs tested against small image fixtures, asserting idempotency on re-run. |
| Surface isolation | The application is booted once per `APP_SURFACE` value and the registered route list asserted to contain that surface's routes and no other's. This is the test that makes the surface gate a guarantee rather than a convention. |
| Inertia prop types | `spatie/typescript-transformer` regenerates `generated.d.ts` every CI run; a stale committed file fails the build. `vue-tsc` then typechecks every admin and portal page against it, so renaming a field in a PHP Data object fails in the pull request that renamed it. |
| `packages/contracts` | Regenerated every CI run from the `api` surface's routes; the generated TypeScript client must typecheck against the gallery, so a breaking API change fails in the pull request that made it. |
| Vue and Astro | Vitest for logic, Playwright for journeys. Playwright drives the Laravel server directly for admin and portal — there is no separate frontend server to orchestrate. Required end-to-end coverage: inquiry → booked; contract signing; gallery password → PIN → email → download; and a negative test proving a guest cannot obtain full resolution. |
| Load | k6 against the gallery read path, targeting p95 under 400 ms for gallery HTML with images served from CDN, simulating 80 concurrent guests on publish day. |

Test-driven development throughout, and especially for access-control rules, where a test that passes by accident is worse than no test.

### Dates and times

All timestamps are stored in UTC and `Europe/Berlin` is a display concern — with one deliberate exception. `Shoot.date` is a SQL `date` column, not `timestamptz`, and `Shoot.call_time` and `end_time` are `time` columns beside them.

A wedding happens on a calendar date in a place, not at an instant. Storing a bare date as a timestamp means picking a time to represent midnight, and a UTC round-trip on that value moves the date by a day on one side of a DST boundary. Every consumer here — board sorting, availability conflicts, automation offsets, the `−1 d` crew brief — wants calendar arithmetic. A `date` column has no instant to misplace.

### Error handling

Ingest failures retry with exponential backoff and then surface in the admin "needs attention" tray with filename and reason — never silently dropped. Bounce and complaint webhooks mark a contact undeliverable and create a staff task, because a bounced gallery-ready email is a silent outage in which the client concludes they were ghosted. Stripe webhooks are idempotent and replayable. Public surfaces return generic errors with no stack traces and no signal distinguishing a wrong password from a nonexistent gallery.

### Observability

Sentry across both applications, tagged by surface so admin, portal, and api errors are separable. Structured JSON logs carrying one `X-Request-Id` minted at the edge, propagated through Astro SSR into API calls and onward into queued jobs — this is what turns "the client says download failed" into a single query. Admin and portal need no propagation step, since the request that renders the page is the request that runs the query. Horizon queue-depth and failure-rate alerts, per-surface uptime checks, a storage and egress cost dashboard, and business metrics (leads, conversion rate, days to delivery).

### Data protection (DSGVO)

EU-region processing throughout, with a data processing agreement for every processor (storage, mail, Stripe, Sentry). Documented retention per entity — guest emails shortest, contracts at statutory retention. Subject access export and deletion exist as admin actions rather than manual SQL. A `ModelRelease` record per project tracks whether images may be used for marketing. The guest gallery ships functional cookies only, with no third-party analytics, so it needs no consent banner.

### Backup and recovery

One data class is unrecoverable: the originals. A lost lead is an annoyance; a lost wedding ends the business. Backup design follows that asymmetry.

Postgres uses point-in-time recovery with daily base backups plus WAL, with restores drilled quarterly. Object storage has versioning enabled plus cross-account replication for the `originals/` prefix specifically. Derivatives are regenerable and therefore excluded from replication. Documented targets: RPO 1 h and RTO 4 h for the database; effectively RPO 0 for originals.

### SaaS seams (dormant in v1)

Every table carries a `tenant_id` holding a single constant value. Storage prefixes are namespaced per tenant. Settings and branding resolve through a tenant-scoped config service rather than environment variables. Cache and queue keys are tenant-prefixed. Mail from-domain resolves per tenant. The cost now is small and mechanical; retrofitting later touches every query in the system.

---

## 9. Phasing

Each phase gets its own spec, its own implementation plan, and its own build cycle. Nothing is built directly from this document.

There is no standalone foundation phase. Rails ship with the first phase that needs them: the monorepo, CI, base schema, the surface mechanism, and **staff** auth arrive with P1, which deploys the `admin` surface alone. Lead capture is manual in P1 — staff enter inquiries received by phone or email — so nothing in that phase needs to be reachable from the open internet, and the network-restricted admin container is the whole deployment. **Client** auth and the `portal` surface arrive with P2 (contract signing needs them). **Guest** auth, the `api` surface, and `packages/contracts` all arrive with P3, when the Astro gallery becomes the first consumer of the JSON API; before that the `api` surface has neither a consumer nor a generated client. The audience matrix test grows with each phase and never covers hypothetical audiences.

A public inquiry form is deferred rather than abandoned. When it returns it brings two properties nothing else in the system has: it is the only surface that is anonymous *and* writes to the database, so it is the only place needing spam defense (honeypot, timing check, per-IP rate limit); and it is the only genuine cross-origin case, posting from the studio's existing marketing site to `api.studio.tld`. Both are reasons to ship it deliberately in its own phase rather than as a footnote to P1.

| Phase | Scope | Milestone |
|---|---|---|
| **P1 CRM core** | Monorepo and CI, base schema with tenant seams, surface gating, staff auth (password + TOTP), Inertia admin surface with shadcn-vue, contacts, projects with leads and multiple requested shoots, conversion to job, derived pipeline board, manual lead entry with availability check over shoot dates, per-shoot crew assignment, internal notes, tasks and templates, calendar/ICS | Replaces spreadsheets; in daily use |
| **P2 Sales** | Contact-scoped client auth (magic-link login form), Inertia portal surface with a project switcher, package catalog, Angebot derived from a package and customised, quote acceptance in the portal, VAT snapshot and §19 UStG handling, contract templates with PDF rendering and simple e-signature, payment plans, Stripe Invoicing and webhooks | Replaces the booking half of the incumbent |
| **P3 Gallery** | Guest auth, the `api` surface, `packages/contracts` and OpenAPI generation, Media context with shoot-scoped assets and per-shoot or per-booking galleries, presigned multipart ingest, libvips derivative pipeline, gallery versioning, Astro gallery, password/PIN/email gates, ZIP builds, download audit, proofing with favorites and comments | Replaces WeTransfer and the incumbent gallery |
| **P4 Engagement** | Form templates, instances and answers, timeline builder, shot lists, client threads, notification service, automation engine with seeded rules | Incumbent subscription can be cancelled |
| **P5 Migration** | Import historical clients, jobs and contracts; backfill or link legacy galleries; cutover runbook; parallel-run period | Subscription ends |

**Why the CRM precedes the gallery:** the sharpest daily pain is spreadsheets, email, and copy-paste — CRM pain — while the incumbent's gallery is its best-solved feature and keeps running throughout. The CRM is also the lowest-risk phase (forms over data, no technical unknowns), which matters for a side-build: if the project stalls after P1, it still leaves a working spreadsheet replacement in daily use rather than a half-tuned media pipeline. The gallery's genuine unknowns — vips throughput, zip streaming at 30 GB, signed URLs versus CDN caching, real egress costs — are deferred knowingly as the price of shipping usable value early, and P3's spec begins with a short technical spike to retire them before committing to the phase plan.

**SaaS is not a phase.** The tenant seams below stay because they are cheap; turning them on — signup, onboarding, per-tenant branding, subscription billing, support, and vendor-grade compliance — is a separate business decision to be made on its own merits, outside this document's scope.

---

## 10. Success criteria

- **Delivery:** galleries published within one business day of edit completion; guest gallery p95 under 400 ms; no support tickets about failed downloads
- **Operations:** inquiry through contract-signed with no manual copy-paste between tools; days-to-delivery visible and trending down
- **Cost:** total infrastructure and tooling below the incumbent subscription at 100 weddings per year
- **Safety:** zero incidents of a guest obtaining full-resolution images, and zero incidents of internal notes appearing in the client portal
