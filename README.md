# Studio — photography client management platform

A CRM, a client portal and a guest photo gallery for a wedding and portrait
photography studio, replacing a paid all-in-one (Pixieset / Studio Ninja
class) plus the spreadsheets, mail threads and WeTransfer links around it.
Single-tenant for one studio, with tenant-shaped seams throughout so SaaS
would be an activation rather than a rewrite. EU hosting and EU processors
throughout, because DSGVO is a constraint on the architecture and not a page
in the footer.

The full reasoning lives in the [product vision](docs/superpowers/specs/2026-08-07-photography-client-management-vision-design.md);
what is built and what is next lives in the [backlog](docs/backlog.md).

## The four surfaces

One Laravel application serves three of them and an Astro application serves
the fourth. A surface is a deployment, not a route prefix: `APP_SURFACE`
picks exactly one route file at boot (`App\Support\Surface`), so the admin
routes are not merely guarded on the portal container — they have no
controller behind them there at all. An unknown value throws before the first
request.

| Surface | `APP_SURFACE` | Routes | Audience | Reachability |
|---|---|---|---|---|
| Admin | `admin` | `apps/studio/routes/admin.php` | Studio staff | Private, mandatory TOTP |
| Portal | `portal` | `apps/studio/routes/portal.php` | Booked clients | Public, magic-link sign-in |
| API | `api` | `apps/studio/routes/api.php` | The gallery, integrations, the MCP server | Public JSON |
| Gallery | — | `apps/gallery` (Astro SSR) | Clients and wedding guests | Public |

Each Laravel surface signs its sessions with its own cookie name
(`Surface::COOKIES`) so a staff session and a client session can never be
mistaken for one another on a shared host.

## Layout

```
apps/
  studio/        Laravel 13 · Inertia · Vue 3 · Tailwind 4 · shadcn-vue
                 Postgres · Redis/Horizon · S3-compatible object storage
  gallery/       Astro 7, server-rendered — the guest gallery viewer
packages/
  contracts/     openapi.json, generated from the api surface's routes
  mcp/           MCP server over the integration API (stdio or HTTP)
docker/
  php/           local development image
  app/           production image for the three Laravel surfaces
  gallery/       production image for the Astro gallery
docs/            architecture, deployment, backlog, sprints, specs
```

`apps/studio/app` is organised by domain rather than by Laravel artifact type
— `Crm`, `Quotes`, `Contracts`, `Payments`, `Media`, `Gallery`, `Portal`,
`Engagement`, `Automation`, `Integration`, `Migration`, `Settings`,
`Documents`, `Catalog`, `Numbering` — each holding its own `Actions/`,
`Data/`, `Http/`, `Models/` and so on. See [docs/architecture.md](docs/architecture.md).

## Getting started

Requirements: Docker, Node 24 (`.nvmrc`) and pnpm 10 (pinned by
`package.json`'s `packageManager` field — use `corepack enable`). No local PHP
is needed: every PHP process in this repository runs in a container.

```sh
pnpm install
cp apps/studio/.env.example apps/studio/.env
docker compose up -d                        # postgres, redis, minio, mailpit, gotenberg, worker (Horizon), glitchtip
pnpm php composer install
pnpm php php artisan key:generate
pnpm php php artisan migrate --seed
```

`pnpm php` is `docker compose run --rm php` — the `php` service is a task
container, not a server, so every artisan call goes through it.

Then start the admin surface with hot module reloading:

```sh
./dev-server.sh                             # Laravel on :8000, Vite for HMR, Ctrl+C stops both
```

In `local` only, `POST /dev-login` bypasses password and TOTP for inspection;
`DevLoginController` is registered nowhere else and aborts on the same
condition.

The other surfaces are services of their own, so the three that a deploy runs
separately also run separately here — a route that leaked across surfaces
shows up as a 200 on the wrong port:

```sh
docker compose up -d portal api gallery      # portal :8001, api :8002, Astro gallery :4321
```

The gallery reaches Laravel over the api surface container to container
(`API_URL=http://api:8000`), never from the guest's browser, and installs its
own linux `node_modules` inside the container rather than reusing the host's.

Ports are variables (`APP_PORT`, `PORTAL_PORT`, `API_PORT`, `GALLERY_PORT`,
`POSTGRES_PORT`, `MINIO_PORT`, `GOTENBERG_PORT`, `GLITCHTIP_PORT`) so several
git worktrees of this repository can run their own stack at once; set them in
a root `.env` per worktree.

| Service | Where | What for |
|---|---|---|
| Portal | http://localhost:8001 | The client-facing surface (`routes/portal.php`) |
| API | http://localhost:8002 | `routes/api.php`, and what the gallery renders against |
| Gallery | http://localhost:4321 | Guest-facing Astro SSR, `/g/:galleryId` |
| Mailpit | http://localhost:8025 | Every mail the app sends locally |
| MinIO console | http://localhost:9001 | The `media` bucket (`studio` / `studio-media-secret`) |
| Horizon | `/horizon` on the admin surface | Queue supervisors and failed jobs |
| GlitchTip | `http://localhost:${GLITCHTIP_PORT:-8080}` | Error tracking, inert until `SENTRY_LARAVEL_DSN` is set |

## Tests

```sh
pnpm php ./vendor/bin/pest                  # Pest — feature and unit
pnpm lint                                   # vue-tsc, astro check, tsc across the workspace
pnpm test                                   # vitest across the workspace
pnpm build
pnpm --filter @studio/app exec playwright test   # end-to-end, needs a built admin bundle
```

Two artifacts are generated, never hand-edited, and CI fails on drift:

```sh
pnpm php php artisan typescript:transform   # apps/studio/resources/js/types/generated.d.ts
docker compose run --rm -e APP_SURFACE=api php \
  php artisan scramble:export --path=../../packages/contracts/openapi.json
```

Scramble introspects the api surface's own routes, which is why the second
one needs `APP_SURFACE=api` — under any other surface those routes are not
registered and the export would come back empty.

## Contributing

`feat/*` → `dev` → `main`, enforced by the `branch-rules` CI job. Read
[docs/branching.md](docs/branching.md) before cutting a branch — `main` only
ever moves by merging `dev`, a `hotfix/*` or a `release/*`.

CI (`.github/workflows/ci.yml`) runs three test jobs behind a paths filter, so
a docs-only change does not boot Postgres.

## Documentation

| Document | Covers |
|---|---|
| [architecture.md](docs/architecture.md) | Surfaces, modules, the cross-cutting seams — start here |
| [deployment.md](docs/deployment.md) | Coolify, the GHCR image pipeline, what to configure by hand |
| [branching.md](docs/branching.md) | The branching model and branch protection |
| [backlog.md](docs/backlog.md) | Phases P1a–P6, epics, stories · [sprints](docs/sprints) |
| [studio/docs/queue.md](apps/studio/docs/queue.md) | Horizon supervisors, named queues, retrying a failed job |
| [studio/docs/media.md](apps/studio/docs/media.md) | Object storage, ingest, derivatives, ZIP builds, delivery |
| [documents.md](docs/documents.md) | Document templates, merge fields, the PDF render path |
| [studio/docs/database-grants.md](apps/studio/docs/database-grants.md) | DB-level privilege revocation for the audit log |
| [studio/docs/historical-import.md](apps/studio/docs/historical-import.md) | CSV import of the studio's existing data |
| [studio/docs/cutover-runbook.md](apps/studio/docs/cutover-runbook.md) | The go-live sequence, its snapshot and its rollback |
