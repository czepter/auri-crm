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

Sequential integers would otherwise be enumerable in exactly the places that matter most here — a guest who can see `/gallery/41` can try `/gallery/42`, and a client who can see their own job ID learns roughly how many clients the studio has. CUID2 was chosen over ULID because ULIDs encode their creation timestamp in the first 48 bits, which would publish when each record was created.

Because the risk in this design is not the scheme but the discipline it demands, two mechanical guards are requirements rather than conventions:

1. The shared `HasPublicId` trait adds `id` to the model's hidden attributes and overrides `getRouteKeyName()` to return `public_id`, so route-model binding cannot silently bind on the integer.
2. A test walks every implemented endpoint's response body — JSON resources on the `api` surface and Inertia page props on `admin` and `portal`, both of which serialize as JSON — and fails if any key named `id` holds an integer. Passing a model straight into `Inertia::render` instead of wrapping it in a Data object is the ordinary way this leaks, and it should fail in CI rather than in review.

Internal identifiers may appear in server-side logs and the audit log's foreign keys. They must never appear in an API response, an Inertia prop, a URL, an email, or a filename.

### 3.1 CRM

- `Contact` — a person; role-agnostic
- `Lead` — inquiry, source, requested date, pipeline stage, lost reason
- `Job` — the shoot: type (wedding, portrait, other), dates, venue, status
- `JobContact` — pivot carrying role: primary, partner, payer, planner, vendor
- `CrewAssignment` — staff user ↔ job ↔ role

A wedding has many people attached to it with different roles: the couple books, a parent may pay, a planner needs the timeline, guests view the gallery. Modeling one `Job` with many typed contacts — rather than a `Client` with a date field — is what lets portrait and wedding work share a schema.

### 3.2 Sales

- `Package`, `PackageItem` — catalog
- `Quote` — line items, price snapshot at time of send
- `Contract` — rendered PDF, template version, merge data, `signed_at`, `signer_ip`, `user_agent`, `document_sha256`
- `PaymentPlan`, `PaymentInstallment` — due date, amount, Stripe references, status

**Snapshot rule:** quotes and contracts store price and terms snapshots; they never join live to `Package`. Changing a package price next year must not alter last year's contract.

### 3.3 Engagement

**Structured forms (studio → client)**
- `FormTemplate` — JSON schema of typed questions per shoot type
- `FormInstance` — job-scoped, with `sent_at` / `opened_at` / `completed_at` and a reminder schedule
- `FormAnswer` — one row per field, versioned so post-submit edits retain history
- `TimelineEntry`, `ShotListItem` — specialized forms; the timeline exports to PDF and ICS for crew

**Conversation (bidirectional, client may initiate)**
- `Thread` — job-scoped, subject, status (open / awaiting_client / resolved), `last_activity_at`
- `Message` — polymorphic author (staff `User` or `ClientAccess`), sanitized markdown body
- `MessageAttachment` — S3-backed, allowlisted MIME types, size-capped
- `ThreadParticipant` — per-participant `last_read_at`, driving unread badges on both sides

A client "simple question" creates a thread from the portal. Email notifications link back to the portal; inbound email replies are deliberately not parsed.

**Internal notes (staff only)**
- `InternalNote` — attaches to `Job`, `Lead`, `Contact`, or `Thread`; author, body, pinned flag

This is a separate table and model rather than an `is_internal` flag on `Message`. With a flag, every query needs a correct `where` clause and one missing scope leaks private commentary into the portal. With separate models, portal-facing route groups have no binding to `InternalNote` at all, and a test asserts no portal response schema references it.

**Tasks**
- `Task` — job-scoped, `audience` (internal or client), assignee, due date, status, source (manual, template, automation)
- `TaskTemplate` — a set applied on job creation by shoot type, with due dates relative to the shoot date (`-30d`, `+14d`)

Client-facing tasks render in the portal as a checklist — sign contract, complete timeline form, pay second installment — created and completed by domain events, so the client's to-do list is derived state rather than manual bookkeeping.

**Notifications** fan out through a single service: staff receive digests and realtime alerts, clients receive transactional email pointing at the portal. One choke point so quiet hours and rate limits are enforced in one place.

### 3.4 Media

- `Gallery` — job-scoped; name, cover, access policy, `expires_at`
- `GalleryVersion` — immutable published set
- `Asset` — original S3 key, checksum, EXIF, capture time, original filename
- `AssetDerivative` — size variant, S3 key, dimensions, bytes
- `GallerySection` — getting ready, ceremony, reception, and so on
- `Favorite` — per viewer identity
- `AssetComment` — anchored to an asset; emits Engagement events so it joins the same unread stream
- `DownloadEvent` — append-only: who, what, when, IP
- `ZipBuild` — async artifact with expiry

Galleries are versioned, never mutated. When a photographer re-uploads corrected edits after 300 downloads have happened, an in-place mutation would invalidate the download audit and detach client favorites from what they actually favorited. Publishing creates `v2`; favorites and history stay pinned to the version they referenced, while clients always land on the latest.

### 3.5 Access

- `User` — staff
- `ClientAccess` — job-scoped, email-bound magic-link identity
- `GuestIdentity` — email, `verified_at`, `consent_source`, `retention_until`; deduped per gallery, reusable if the person returns
- `DownloadRequest` — gallery version, scope, requester, token hash, `expires_at`, `consumed_at`, IP
- `AuditLog` — append-only

### 3.6 Cross-context events

`ContractSigned`, `GalleryPublished`, `InstallmentPaid`, `InstallmentOverdue`, and similar. Listeners send mail, advance pipeline stages, and unlock galleries. CRM stays ignorant of Media internals, which is what allows Media to scale or split out later.

---

## 4. Identity and authentication

One host per surface: `admin.studio.tld`, `my.studio.tld`, `api.studio.tld`, `gallery.studio.tld`.

Admin and portal are Inertia surfaces served by Laravel itself, so they use ordinary session guards. Sanctum's stateful-SPA mode, the CSRF-cookie handshake, and cross-origin configuration disappear from both — Inertia's `XSRF-TOKEN` handling is the whole story. Sanctum survives only on `api.studio.tld`, where the gallery's guest tokens and service key live.

| Audience | Surface | Credential | Session |
|---|---|---|---|
| `staff` | admin (Inertia) | Password + TOTP (mandatory) | Laravel session cookie `studio_admin_session`, host-scoped; 12 h idle, 7 d absolute |
| `client` | portal (Inertia) | Login form → magic link, email-bound, single-use, 15 min TTL | Laravel session cookie `studio_portal_session`, host-scoped; 30 d, revocable; expires 90 d after delivery |
| `guest` | gallery (Astro → API) | Gallery password | Sanctum guest token, 7 d gallery-scoped, revoked on password rotation |
| `service` | Astro SSR → API | Read-only key, gallery-metadata scope | Cannot mint sessions, cannot read full-resolution keys |

Cross-tier leaks are prevented at **two independent layers**. First, the surface itself: a `staff` route does not exist in the portal or api container, so there is nothing for a client or guest credential to reach. Second, within a surface, the required guard is declared at route-group middleware rather than in per-controller checks, so a wrong-audience credential is rejected before controller dispatch. Policies are too deep in the stack to serve as a first line of defense; they are the fine grain, not the gate.

Because the cookies are host-scoped and named differently, a portal session is not merely rejected on the admin host — it is never transmitted to it.

The Astro SSR server is a confused-deputy risk: it calls the API on behalf of anonymous visitors. It forwards the visitor's own guest token and holds nothing stronger than a read-only metadata key, so a template bug cannot become full data exposure.

Authorization uses Laravel policies over job ownership and crew assignment. A second shooter sees assigned jobs, not the whole book; an editor sees media, not payments. Roles are coarse; policies are the fine grain.

**Rejected:** social login, client passwords (magic links are simpler and harder to phish at this scale), shared studio accounts (they destroy the audit trail).

### Security requirements

1. Every route group declares its required audience in middleware, verified by an explicit test matrix of every route group × every audience. A second test boots the application once per `APP_SURFACE` value and asserts that no other surface's routes are registered — the surface gate is a claim about the route table, so it is tested against the route table.
2. Magic links are self-service: the portal shows a login form where the client enters their email and receives a fresh link on demand. An expired link is therefore a re-request, not a support ticket, and a shared couple's inbox works unchanged since identity is email-bound, not person-bound. Links are single-use; consuming one invalidates all outstanding links for that address. The form responds identically for known and unknown emails (no account enumeration), and link requests are rate-limited per address and per IP. Store and compare hashes — never log link values.
3. Staff MFA is mandatory. The admin surface is additionally restricted at the network edge (IP allowlist or VPN) where practical, since it can export every client's data.
4. Session revocation is global and immediate. One "revoke all access" action per job kills client sessions, guest sessions, outstanding magic links, and unconsumed download links — the real scenario is a link forwarded to the wrong person.
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
      ├─ perceptual hash → duplicate detection within the gallery
      ├─ derivatives via libvips: thumb 400 / web 1600 / preview 2560
      ├─ blurhash placeholder (instant paint, no layout shift)
      └─ move original from quarantine to permanent prefix; write Asset + AssetDerivative
```

Uploads never pass through Laravel. At roughly 30 GB per wedding, presigned S3 multipart directly from the admin browser makes the API a coordinator that issues part URLs and reacts to completion.

Jobs are idempotent, keyed on the SHA-256 of the original, because a 1200-file upload will partially fail sometimes and re-running must be safe. Failed assets appear in an admin "needs attention" tray, not a log file.

libvips is chosen over Imagick for roughly 4–8× the throughput at a fraction of the memory. Derivative workers run as a separately scaled deployment so a large ingest never starves request handling.

### Storage and tiering

```
originals/{job_uuid}/{asset_uuid}.jpg      hot 90 d → infrequent access → archive at delivery + 18 mo
derivatives/{asset_uuid}/{size}.webp|jpg   hot always, small
zips/{gallery_version}/{scope}-{size}.zip  lifecycle delete after 14 d
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

`Lead.stage` is a sales funnel: `new → contacted → quote_sent → contract_sent → booked`, with `lost(reason)` reachable from any stage. Date-availability checks on intake flag conflicts against existing jobs.

`Job.status` is a forward-only production pipeline: `booked → prep → shot → culling → editing → ready → delivered → archived`. Each transition emits a domain event. A job is created by conversion when the contract is signed and the deposit is paid.

Keeping these separate avoids a fourteen-state enum in which half the transitions are illegal.

### Automation engine

An `AutomationRule` is data, not a code path: trigger plus conditions plus actions.

- **Triggers:** domain events, or date offsets relative to the shoot date evaluated by a daily scheduler
- **Actions:** send templated email, create a form instance, create a task, advance a stage, notify staff
- **Auditing:** every firing writes an `AutomationRun` row recording rule, target, and outcome — without it, "why did the client get that email twice" is unanswerable
- **Guard:** an idempotency key per (rule, target) so a double scheduler run cannot double-send

Seeded rules: inquiry auto-reply · stale-lead nudges at 3 and 7 days · booking confirmation and welcome packet · timeline form at −30 d · payment reminder at −14 d · crew brief at −1 d · delivery email on publish · review request at +14 d · archive at +90 d.

No visual rule builder in v1. Rules are seeded from config and become editable once the five rules actually used are known.

### Payments

Each `PaymentInstallment` becomes a Stripe Invoice. Stripe owns the hosted payment page, card and SEPA handling, dunning email, VAT, and receipts — building EU tax handling and reminder logic in-house is months of work with tax-level consequences for getting it wrong.

Webhooks (`invoice.paid`, `invoice.payment_failed`) reconcile back to the installment and emit `InstallmentPaid`, which completes the client task and optionally unlocks the gallery. Webhook handling is idempotent by Stripe event ID. Stripe, not the local record, is the source of truth for payment state.

An optional configurable gate blocks final gallery download until the balance is settled.

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

### Error handling

Ingest failures retry with exponential backoff and then surface in the admin "needs attention" tray with filename and reason — never silently dropped. Bounce and complaint webhooks mark a contact undeliverable and create a staff task, because a bounced gallery-ready email is a silent outage in which the client concludes they were ghosted. Stripe webhooks are idempotent and replayable. Public surfaces return generic errors with no stack traces and no signal distinguishing a wrong password from a nonexistent gallery.

### Observability

Sentry across both applications, tagged by surface so admin, portal, and api errors are separable. Structured JSON logs carrying one `X-Request-Id` minted at the edge, propagated through Astro SSR into API calls and onward into queued jobs — this is what turns "the client says download failed" into a single query. Admin and portal need no propagation step, since the request that renders the page is the request that runs the query. Horizon queue-depth and failure-rate alerts, per-surface uptime checks, a storage and egress cost dashboard, and business metrics (leads, conversion rate, days to delivery).

### Data protection (DSGVO)

EU-region processing throughout, with a data processing agreement for every processor (storage, mail, Stripe, Sentry). Documented retention per entity — guest emails shortest, contracts at statutory retention. Subject access export and deletion exist as admin actions rather than manual SQL. A `ModelRelease` record per job tracks whether images may be used for marketing. The guest gallery ships functional cookies only, with no third-party analytics, so it needs no consent banner.

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
| **P1 CRM core** | Monorepo and CI, base schema with tenant seams, surface gating, staff auth (password + TOTP), Inertia admin surface with shadcn-vue, contacts, leads, jobs, pipeline board, manual lead entry with availability check, crew assignment, internal notes, tasks and templates, calendar/ICS | Replaces spreadsheets; in daily use |
| **P2 Sales** | Client auth (magic-link login form), Inertia portal surface, packages, quotes, contract templates with PDF rendering and simple e-signature, payment plans, Stripe Invoicing and webhooks | Replaces the booking half of the incumbent |
| **P3 Gallery** | Guest auth, the `api` surface, `packages/contracts` and OpenAPI generation, Media context, presigned multipart ingest, libvips derivative pipeline, gallery versioning, Astro gallery, password/PIN/email gates, ZIP builds, download audit, proofing with favorites and comments | Replaces WeTransfer and the incumbent gallery |
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
