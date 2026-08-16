# Architecture

One Laravel application and one Astro application, in one repository,
deployed as several containers from two images. This document covers the
seams that cut across every feature — the surfaces, the module layout, the
layered authorization, the shared data conventions and the generated
artifacts. Everything vertical (queues, media, documents, imports) has its
own document; see the index at the end.

## Surfaces

A **surface** is a deployment of the Laravel application serving exactly one
audience. `APP_SURFACE` selects one route file at boot and no other is ever
registered:

```php
// App\Support\Surface::registerRoutes(), called from bootstrap/app.php
match ($surface) {
    self::API => Route::middleware('api')->prefix('api')->group($file),
    default   => Route::middleware('web')->group($file),
};
```

| Surface | Route file | Guard | Session cookie | Authenticated by |
|---|---|---|---|---|
| `admin` | `routes/admin.php` | `staff` | `studio_admin_session` | Password, plus opt-in TOTP for whoever has enrolled |
| `portal` | `routes/portal.php` | `portal` | `studio_portal_session` | Single-use magic link, 15 minutes |
| `api` | `routes/api.php` | `api` | — (bearer only) | Sanctum token on a Contact, an `ApiToken`, or a staff OAuth token |

Three consequences worth stating plainly, because they are the reason the
model exists:

1. **Absence, not middleware.** The admin routes are not merely guarded on
   the portal container — no controller is registered for them there. A
   misconfigured middleware cannot expose what was never routed. The same
   rule is why Horizon's auto-discovery is disabled in `composer.json` and
   its providers are registered by hand in `bootstrap/providers.php`, admin
   surface only.
2. **An unknown surface throws at boot**, not on the first request.
3. **Separate cookies per surface** (`Surface::COOKIES`). A `Contact` is not
   a `User` and must never become one; two audiences sharing a cookie name on
   one host is how one becomes the other, even if the guard would eventually
   refuse.

`SurfaceIsolationTest` and `AudienceMatrixTest` hold this: every route group
is asserted against every audience, so an unguarded route is a test failure
rather than an omission. Deliberate exceptions live in the matrix's own
`PUBLIC_ROUTES` list — the portal login, the calendar subscription feed, the
Stripe webhook — so each is a recorded decision.

The gallery is not a fourth audience and no longer a separate app. P-08
(#227) folded the guest grid, its lightbox and the image bytes into the
portal surface, behind `gallery.gate:view` — the claim lives in that
surface's session, which is exactly why a separate origin could never check
it.

## Authorization, in four layers

Each layer answers a different question, and each runs earlier than the one
below it:

| Layer | Question | Where |
|---|---|---|
| Surface | Does this route exist in this process at all? | `config/surface.php` |
| Audience | Was this request authenticated by the guard this route expects? | `EnsureAudience` (`audience:staff\|portal\|api`) |
| Capability / scope | Is this action switched on, and was this token granted it? | `EnsurePortalCapability`, `EnsureApiTokenScope` |
| Policy | May *this* principal touch *this* record? | `app/Policies` |

Policies run too late to be a first line of defence — by then the request has
already been routed and the principal resolved — which is why the three
layers above them exist.

Other middleware in the global and per-route stacks:

| Middleware | Does |
|---|---|
| `AssignRequestId` | Mints/echoes `X-Request-Id`, prepended globally on all three surfaces |
| `EnsureNoStaffExists` | Gates `/einrichtung`: 404s the moment any staff user exists, same as a route that was never registered |
| `RequireStudioOnboarding` | Redirects GET requests to `/einrichtung/studio` until the studio's first-run settings wizard is completed |
| `EnsureIdempotentWrite` | Every api write route: claims an idempotency row *before* the route runs, replays the cached response |
| `EnsurePortalMaintenanceClosure` | Wraps the whole portal; the logo and the Impressum stay reachable from the closure screen |
| `EnsureGalleryGate` | Declares which of a gallery's gates (password, download PIN) a route needs already satisfied |
| `HandleInertiaRequests` | Per-surface root view, so each surface loads its own Vite entrypoint and theme |

`EnsurePortalCapability` refuses with **404, never 403** — a capability the
studio has switched off should look like a feature that does not exist. The
hidden button in the Vue component is the affordance half of the same rule.

## Domain modules

`apps/studio/app` is organised by domain, not by Laravel artifact type. There
is no top-level `app/Actions` or `app/Jobs` holding everything; each module
owns its own:

```
app/Crm/
  Actions/  Data/  Enums/  Events/  Http/  Listeners/  Models/  Support/
```

| Module | Owns |
|---|---|
| `Crm` | Contacts, projects, shoots, leads, the board, tasks, forms, timeline |
| `Catalog` | Packages and priced items |
| `Quotes` | Quote building, sending, acceptance |
| `Contracts` | Contract rendering and signature |
| `Documents` | Templates, merge fields, the shared vocabulary behind all three document types |
| `Payments` | Payment plans, installments, Stripe |
| `Numbering` | Document number ranges and allocation |
| `Media` | Ingest, multipart upload, derivatives, ZIP builds |
| `Gallery` | Galleries, versions, gates, favorites, comments, delivery |
| `Portal` | The client surface's own screens and notifications |
| `Engagement` | Message threads between studio and client |
| `Automation` | Rules that fire on events and on date offsets |
| `Integration` | The public integration API, API tokens, webhooks |
| `Migration` | Historical import, cutover snapshot and restore |
| `Settings` | Studio profile, portal capabilities, document settings |

Only genuinely cross-cutting things stay at the top level: `Http/Middleware`,
`Concerns`, `Support`, `Providers`, `Policies`, `Enums`, and the four models
that belong to no module (`User`, `Absence`, `StaffInvitation`, `AuditLog`).

Console commands live beside their module too, which is why
`bootstrap/app.php` names each one explicitly in `withCommands()` — nothing
in `app/Console/Commands` is auto-discovered, because that directory is not
used.

## Data conventions

**Split identity** (`App\Concerns\HasPublicId`). The bigint primary key stays
inside the database, carrying foreign keys and joins. A CUID2 in `public_id`
is the only identifier that ever leaves the server, and it is the route key.
The trait also adds `id` to `$hidden`. `NoInternalIdsTest` and `PublicIdTest`
assert that no internal id reaches a response payload.

**Dormant tenancy** (`App\Concerns\BelongsToTenant`). Every tenant-scoped
model gets a global `where tenant_id = config('tenancy.tenant_id')` scope and
has `tenant_id` filled on create. One studio today; the column exists so that
activating multi-tenancy is a data migration rather than a schema rewrite.
`TenantSeamTest` holds it.

**Append-only audit log** (`App\Models\AuditLog`). `UPDATED_AT` is null and
`updating` throws. The application-level guarantee is backed by DB-level
privilege revocation — see [studio/docs/database-grants.md](../apps/studio/docs/database-grants.md).

## Async and storage

Everything slow is a queued job on a named queue, supervised by Horizon:
`default`, `mail`, `render`, `media.ingest`, `media.zip`, each with its own
timeout in `config/horizon.php`. Two scheduled commands run daily (reaping
incomplete multipart uploads, sweeping date-offset automation rules), from a
dedicated scheduler container in a deployment. See
[studio/docs/queue.md](../apps/studio/docs/queue.md).

Object storage is Laravel's `s3` disk and nothing else — no
`StorageDriverInterface` over the abstraction Flysystem already provides.
Nothing in `app/` names a provider; only the Compose files' env values do,
which is what keeps MinIO (local), RustFS (deployed) and any hosted
S3-compatible bucket interchangeable. See
[studio/docs/media.md](../apps/studio/docs/media.md).

PDF rendering is Gotenberg over HTTP, called only from the `render` queue,
never from a request thread.

## Observability

`X-Request-Id` is minted at the edge if the caller did not send one, echoed
on the response, pushed into the log context, and — via
`ObservabilityServiceProvider` — stamped onto every queued job's payload as
it is pushed. The Astro gallery forwards the same header into its api calls
(the portal surface's own request id), so one id follows a guest's page
view through SSR, into Laravel, and out into a job.

Errors go to a self-hosted GlitchTip over the Sentry protocol — our own
infrastructure rather than a hosted processor, which is one fewer DSGVO
processor rather than one more. The SDK is fully inert until
`SENTRY_LARAVEL_DSN` is set, so a local stack sends nothing by default.
`SENTRY_RELEASE` is baked into the image from the git SHA at build time, so
an error is attributable to the deploy that shipped it.

## Generated artifacts

Two files are generated from the PHP source, committed, and diffed in CI. A
stale one fails the build:

| File | Generated by | Consumed by |
|---|---|---|
| `apps/studio/resources/js/types/generated.d.ts` | `php artisan typescript:transform` (from `spatie/laravel-data` objects) | The Vue components' Inertia props |
| `packages/contracts/openapi.json` | `php artisan scramble:export`, under `APP_SURFACE=api` | `packages/mcp`'s typed client, via `openapi-typescript` |

A contract nothing regenerates is a contract nobody can trust, which is why
both are drift-checked rather than merely produced once.

`packages/mcp` is an MCP server over that contract — stdio for local clients
that spawn it, streamable HTTP for remote use, with every HTTP request
requiring a bearer token the CRM's own `/oauth/token-info` accepts.

## Guardrail tests

Several tests exist to hold a convention rather than a feature. Breaking one
means a rule was crossed, not that a behaviour regressed:

| Test | Holds |
|---|---|
| `SurfaceIsolationTest` | Each surface registers only its own routes |
| `AudienceMatrixTest` | Every route group × every audience, with an explicit public list |
| `NoInternalIdsTest` | No internal primary key reaches a response |
| `TenantSeamTest` | The tenant scope is applied and `tenant_id` is filled |
| `AuditLogTest` | Audit rows cannot be updated |
| CI's drift checks | The two generated artifacts above are current |

## Further reading

[deployment.md](deployment.md) ·
[branching.md](branching.md) ·
[backlog.md](backlog.md) ·
[documents.md](documents.md) ·
[queue.md](../apps/studio/docs/queue.md) ·
[media.md](../apps/studio/docs/media.md) ·
[database-grants.md](../apps/studio/docs/database-grants.md) ·
[historical-import.md](../apps/studio/docs/historical-import.md) ·
[cutover-runbook.md](../apps/studio/docs/cutover-runbook.md)
