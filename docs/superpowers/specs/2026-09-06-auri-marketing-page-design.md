# Auri marketing page — design

**Date:** 2026-09-06
**Status:** design, no issues filed yet — this is the reasoning; issues carry the scope once filed.

---

## 1. Why this exists now

The repository is `czepter/auri-crm` on GitHub, private, no license file, no description, `APP_NAME` still defaulting to `Laravel`. Going public needs a page to go public onto — right now there is nothing that tells a stranger what this is, who it's for, or how to run it. This is that page.

Inspiration: [opengym.duarte-santos.ch](https://opengym.duarte-santos.ch) — single-scroll open-source project page, dark theme, feature blocks with sub-cards, a screenshot carousel, an open-source stats section, self-host instructions. The shape transfers; the content doesn't — openGym is a single-user mobile/self-host tool with a live demo, Auri is a three-audience business tool (studio staff, booked clients, wedding guests) with real financial and personal data, so a few of openGym's sections have no equivalent here and are dropped rather than faked.

## 2. Scope

**In:** a new `apps/marketing` Astro app, four pages (`/`, `/features`, `/docs`, `/about`), German copy only, deploy wiring (compose service, README/architecture updates), a manual screenshot-capture pass against seeded local data.

**Out, named so the omissions are decisions:** a live in-browser demo (real client/financial data flows make a public demo instance its own project — seed isolation, reset cadence — not a page-design concern); English/i18n (product UI is German-only today, matching it); a mobile app section (none exists, unlike openGym's app-vs-self-host duality); a Discord/support-channel section (no such channel exists yet); an automated migration/import feature card (the `Migration` module has `PlanHistoricalImport`/`CommitHistoricalImport` actions in code, but per direct correction there is no working import run today — the page must not claim a feature that doesn't work); a tech-stack rationale on `/about` (the reader is a photographer, not a developer — why Laravel-plus-Astro belongs in `docs/architecture.md`, not here).

**License:** AGPL-3.0, decided over MIT specifically to close the SaaS-resale loophole — the same reasoning openGym gives for its own AGPL choice. A `LICENSE` file at repo root is part of going public, tracked here as a dependency of the "Quelloffen" section rather than owned by this design.

## 3. Why static, not SSR

`apps/gallery` is SSR because which `GalleryVersion` is current can change between two requests — the astro.config.mjs comment for that app says so directly. Nothing on the marketing page has that property: the copy, the screenshots, the feature list are all build-time facts. Giving this app a Node runtime and a `docker/marketing/Dockerfile` shaped like `docker/gallery/Dockerfile` would be provisioning a server process to solve a problem that doesn't exist here — `output: 'static'`, served by a plain `nginx:alpine`, is the whole runtime.

The one piece of the page that changes without a deploy — GitHub star/fork/issue counts on the "Quelloffen" section — doesn't need SSR either. openGym's own counts are live in the same static-feeling page, which means they're a client-side fetch against the GitHub API on load, not a server render. Same approach here: a small script tag, not a backend.

## 4. Repo layout

```
apps/marketing/              @studio/marketing
  astro.config.mjs            output: 'static', @tailwindcss/vite (same Tailwind 4 major as studio/gallery)
  src/
    pages/
      index.astro
      features.astro
      docs.astro
      about.astro
    components/                Nav, Hero, FeatureGrid, ScreenshotCarousel, SelfHostBlock, OpenSourceStats, Footer
    content/                    feature copy as plain .ts data, no CMS
  public/
    screenshots/                the four to five captures from §7
docker/marketing/
  Dockerfile                    nginx:alpine serving the built dist/, no Node in the runtime stage
```

Package name `@studio/marketing`, following `@studio/gallery` — the repo's public name is `auri-crm`/"Auri", the internal package scope stays `@studio/*` the way it already does for `studio-workspace` and `@studio/gallery`; renaming that scope is a separate, unrelated decision this design doesn't make.

## 5. Landing page (`/`) — sections

| # | Section | Content |
|---|---|---|
| 1 | Nav | Logo "Auri", anchors (Funktionen, Screenshots, Selbst hosten, Quelloffen), GitHub link with live star count, primary CTA → `#selbst-hosten` |
| 2 | Hero | Eyebrow "Auri", three-line headline, subcopy naming what it replaces (Pixieset/Studio Ninja + spreadsheets + WeTransfer), CTAs "Auf GitHub ansehen" / "Selbst hosten", hero screenshot |
| 3 | Warum | The tool-stack-replacement angle plus EU-Hosting/DSGVO as the concrete differentiator against Pixieset (US-hosted) |
| 4 | Funktionen (teaser) | Three condensed blocks — CRM, Kundenportal, Gästegalerie — same copy reused verbatim from the matching `/features` cards, link "Alle Funktionen →" |
| 5 | Screenshots | Carousel, four to five real captures (§7) |
| 6 | Selbst hosten | Condensed `docker compose up` path, link to `/docs` for the full walkthrough, EU-hosting fine print |
| 7 | Quelloffen | AGPL-3.0 callout, live GitHub stats (client-side fetch), links to repo/CONTRIBUTING/CHANGELOG |
| 8 | Footer | License/copyright, links to `/features`, `/docs`, `/about`, GitHub |

Audience: the photographer, not the self-hoster — self-hosting is framed as something to hand to a developer friend or run on Coolify, not a prerequisite skill, matching how openGym frames its own self-host path as the "homelabber" option rather than the default reader.

## 6. `/features`, `/docs`, `/about`

**`/features`** — full module list from `docs/architecture.md`'s domain table, translated into customer-facing cards, openGym's "and the rest of it" grid pattern:

| Card | From module |
|---|---|
| Anfragen & Board | `Crm` |
| Leistungspakete | `Catalog` |
| Angebote, online annehmbar | `Quotes` |
| Verträge mit digitaler Unterschrift | `Contracts` |
| Eine Vorlage, drei Dokumenttypen | `Documents` |
| Zahlungspläne & Stripe | `Payments` |
| Fortlaufende Rechnungsnummernkreise | `Numbering` |
| Mehrfach-Upload & Ableitungen | `Media` |
| Passwort- & PIN-geschützte Gästegalerie | `Gallery` |
| Eigenes Kundenportal | `Portal` |
| Nachrichten zwischen Studio und Kunde | `Engagement` |
| Automationen nach Ereignis oder Datum | `Automation` |
| Öffentliche API, Webhooks, MCP-Server | `Integration` |
| Portal-Funktionen einzeln abschaltbar | `Settings` |

`Migration` has no card — see §2.

**`/docs`** — the README's setup steps (`pnpm install` → `docker compose up -d` → `composer install` → `migrate --seed` → `dev-server.sh`) rendered for a browser instead of a terminal, plus the port-per-surface table. Framed as something to hand to whoever sets it up, not an interactive tutorial.

**`/about`** — origin story only: why this exists, why AGPL, why EU hosting isn't a footer line but a constraint. No architecture rationale (§2).

## 7. Screenshot capture

No screenshots exist anywhere in the repo today. Plan:

1. Seed one demo studio locally with data that reads as real — a handful of contacts/projects/shoots across board stages, one quote/contract/payment plan in a realistic state, one gallery with placeholder photos and a couple of favorites/comments — not `Faker`-default noise.
2. Capture four to five shots at a fixed viewport against the running dev stack (`dev-server.sh` for admin, `docker compose up -d portal api gallery` for the rest): admin board, portal overview, gallery grid, gallery detail with comments.
3. Drop the files into `apps/marketing/public/screenshots/`. One manual pass, not a pipeline — there's no recurring need to regenerate these until the UI they show changes materially.

## 8. Deploy wiring

- `docker-compose.yml`: new `marketing` service, static build served by `nginx:alpine`, `MARKETING_PORT` (default `4322`), no `depends_on` — the app is fully standalone, no Postgres/Redis/api dependency at all, unlike the gallery service.
- `docker-compose.coolify.yml`: matching entry, `${SERVICE_FQDN_MARKETING_80}`, following the existing per-service pattern.
- `README.md`: the "four surfaces" table gains the marketing site as a fifth row, or a short paragraph beneath it — worded as a fifth *sibling app*, not a fifth *surface*, since `Surface` in this codebase means an authenticated Laravel route file and this has neither authentication nor a Laravel dependency. Add it to the `apps/` tree listing too.
- `docs/architecture.md`: one line under "Surfaces" noting the marketing app exists and explicitly why it isn't one.
- Testing: `vitest run --passWithNoTests` scaffold, matching `apps/gallery/package.json`. No forced test beyond that — CLAUDE.md's "non-trivial logic ships with a test" rule doesn't reach markup and copy, and inventing logic to test would be exactly the speculative work CLAUDE.md rejects.

## 9. Open questions carried forward, not blocking

- `LICENSE` file content and repo visibility flip (private → public) are prerequisites for the "Quelloffen" section to be true, not part of this design — flagged so they aren't forgotten, not scoped here.
- Actual German copy (headlines, subcopy, card descriptions) is a content-writing pass against this structure, not fixed by this design — CLAUDE.md's no-AI-slop German copy rule applies directly when it's written.
