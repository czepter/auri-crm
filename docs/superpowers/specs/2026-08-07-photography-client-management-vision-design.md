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

Print or product store · album designer · accounting and bookkeeping (CSV/DATEV export only) · Lightroom plugin · IMAP/email inbox sync · AI culling or tagging · native mobile apps (responsive web only) · qualified eIDAS signatures · multi-currency · public marketing site.

---

## 2. Architecture

Chosen approach: **Laravel API with three purpose-built frontends, one repository per deployable.**

### Repositories

| Repo | Stack | Deploy target | Audience |
|---|---|---|---|
| `studio-api` | Laravel 11, Postgres, Horizon, S3-compatible storage | Container; web and queue workers scaled separately | — |
| `studio-admin` | Nuxt 4 (SSR) | Private host, MFA and network-restricted | Staff |
| `studio-portal` | Nuxt 4 (SSR) | Public | Booked clients |
| `studio-gallery` | Astro (SSR + aggressive CDN caching) | Public edge | Clients and wedding guests |
| `studio-contracts` | OpenAPI spec, generated TypeScript client, shared enums | Private npm registry | Consumed by the three frontends |

Workspace layout — the parent directory holds the repos as ignored subdirectories and owns the documentation:

```
client-management-portal/          git repo: docs + workspace tooling
├── docs/superpowers/specs/        vision and per-phase specs
├── studio-api/                    own repo
├── studio-admin/                  own repo
├── studio-portal/                 own repo
├── studio-gallery/                own repo
└── studio-contracts/              own repo
```

### Rationale

Laravel supplies queues (Horizon), scheduling, mail, policies, PDF tooling, and Stripe integration as inherited infrastructure rather than assembled parts — roughly six months of plumbing that a Node backend would require building. The cost is two languages and no compile-time type sharing, bounded to a single OpenAPI → TypeScript codegen step.

Astro serves the gallery because gallery pages are read-heavy and image-dominated; shipping near-zero JavaScript matters when guests load a 1500-image gallery on venue wifi. Admin and portal are split into separate Nuxt apps because they have different authentication models, threat models, and release cadences; merging them saves setup once and creates a shared session surface permanently.

### Contract discipline

Polyrepo's real risk is contract drift. `studio-api` CI generates an OpenAPI document from its routes, diffs it against the committed spec in `studio-contracts`, and fails the build on undeclared change. The published TypeScript client is a pinned dependency the frontends bump deliberately.

Per-repo CI: lint → test → build → publish artifact → deploy on tag.

---

## 3. Domain model

Five bounded contexts, implemented as Laravel modules with separate namespaces. No cross-context database joins — contexts reference each other by ID and communicate through domain events.

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

Domains share a parent so cookie-based sessions work without CORS token juggling: `api.studio.tld`, `admin.studio.tld`, `my.studio.tld`, `gallery.studio.tld`.

| Audience | Surface | Credential | Session |
|---|---|---|---|
| `staff` | admin | Password + TOTP (mandatory) | Sanctum stateful cookie; 12 h idle, 7 d absolute |
| `client` | portal | Login form → magic link, email-bound, single-use, 15 min TTL | 30 d refresh cookie, revocable; expires 90 d after delivery |
| `guest` | gallery | Gallery password | 7 d gallery-scoped cookie, revoked on password rotation |
| `service` | Astro SSR → API | Read-only key, gallery-metadata scope | Cannot mint sessions, cannot read full-resolution keys |

The control that prevents cross-tier leaks is an **audience claim asserted at route-group middleware**, not per-controller checks. A `guest` token reaching a `client` or `staff` route is rejected before controller dispatch. Policies are too deep in the stack to serve as a first line of defense.

The Astro SSR server is a confused-deputy risk: it calls the API on behalf of anonymous visitors. It forwards the visitor's own guest token and holds nothing stronger than a read-only metadata key, so a template bug cannot become full data exposure.

Authorization uses Laravel policies over job ownership and crew assignment. A second shooter sees assigned jobs, not the whole book; an editor sees media, not payments. Roles are coarse; policies are the fine grain.

**Rejected:** social login, client passwords (magic links are simpler and harder to phish at this scale), shared studio accounts (they destroy the audit trail).

### Security requirements

1. Every API route group declares its required audience in middleware, verified by an explicit test matrix of every route group × every audience.
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
  studio-api ──dispatch──▶ Horizon queue: media.ingest
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

| Repo | Strategy |
|---|---|
| `studio-api` | Pest; feature tests over real HTTP and Postgres. Audience matrix test covering every route group × every token audience. Policy tests per role. Stripe webhook fixtures replayed. Queue jobs tested against small image fixtures, asserting idempotency on re-run. |
| `studio-contracts` | CI generates OpenAPI from routes, diffs against the committed spec, fails on undeclared change; the generated TypeScript client must typecheck. |
| Frontends | Vitest for logic, Playwright for journeys. Required end-to-end coverage: inquiry → booked; contract signing; gallery password → PIN → email → download; and a negative test proving a guest cannot obtain full resolution. |
| Load | k6 against the gallery read path, targeting p95 under 400 ms for gallery HTML with images served from CDN, simulating 80 concurrent guests on publish day. |

Test-driven development throughout, and especially for access-control rules, where a test that passes by accident is worse than no test.

### Error handling

Ingest failures retry with exponential backoff and then surface in the admin "needs attention" tray with filename and reason — never silently dropped. Bounce and complaint webhooks mark a contact undeliverable and create a staff task, because a bounced gallery-ready email is a silent outage in which the client concludes they were ghosted. Stripe webhooks are idempotent and replayable. Public surfaces return generic errors with no stack traces and no signal distinguishing a wrong password from a nonexistent gallery.

### Observability

Sentry across all four applications. Structured JSON logs carrying one `X-Request-Id` minted at the edge and propagated through Astro and Nuxt SSR into API calls and queued jobs — this is what turns "the client says download failed" into a single query. Horizon queue-depth and failure-rate alerts, per-surface uptime checks, a storage and egress cost dashboard, and business metrics (leads, conversion rate, days to delivery).

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

| Phase | Scope | Milestone |
|---|---|---|
| **P0 Foundation** | `studio-api` skeleton, base schema with tenant seams, three-audience auth with the audience matrix test, `studio-contracts` and codegen, CI/CD across five repos, admin shell | Rails only; nothing user-visible |
| **P1 Gallery** | Media context, presigned multipart ingest, libvips derivative pipeline, gallery versioning, Astro gallery, password/PIN/email gates, ZIP builds, download audit, proofing with favorites and comments | Replaces WeTransfer and the incumbent gallery |
| **P2 CRM core** | Contacts, leads, jobs, pipeline board, public inquiry form, availability check, crew assignment, internal notes, tasks and templates, calendar/ICS | Replaces spreadsheets |
| **P3 Sales** | Packages, quotes, contract templates with PDF rendering and simple e-signature, payment plans, Stripe Invoicing and webhooks | Replaces the booking half of the incumbent |
| **P4 Engagement** | Form templates, instances and answers, timeline builder, shot lists, client threads, notification service, automation engine with seeded rules | Incumbent subscription can be cancelled |
| **P5 Migration** | Import historical clients, jobs and contracts; backfill or link legacy galleries; cutover runbook; parallel-run period | Subscription ends |
| **P6 SaaS** | Activate tenant seams, signup and onboarding, per-tenant branding and domains, subscription billing | Second business line |

**Why the gallery precedes the CRM:** the gallery holds all the technical unknowns — vips throughput, zip streaming at 30 GB, the interaction between signed URLs and CDN caching, and real egress costs. Discovering those in month six, after a CRM has been built on assumptions about them, is the expensive ordering. The CRM has no unknowns; it is forms over data. Since the incumbent subscription runs throughout the migration either way, "which phase teaches us most" is a better optimizer than "which phase saves money soonest."

The alternative ordering — CRM first — is defensible if daily administrative pain outweighs delivery pain. It trades away early risk discovery.

---

## 10. Success criteria

- **Delivery:** galleries published within one business day of edit completion; guest gallery p95 under 400 ms; no support tickets about failed downloads
- **Operations:** inquiry through contract-signed with no manual copy-paste between tools; days-to-delivery visible and trending down
- **Cost:** total infrastructure and tooling below the incumbent subscription at 100 weddings per year
- **Safety:** zero incidents of a guest obtaining full-resolution images, and zero incidents of internal notes appearing in the client portal
