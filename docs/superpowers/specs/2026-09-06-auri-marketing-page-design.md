# Auri marketing page — design

**Date:** 2026-09-06
**Status:** design, no issues filed yet — this is the reasoning; issues carry the scope once filed.

---

## 1. Why this exists now

The repository is `czepter/auri-crm` on GitHub, private, no license file, no description, `APP_NAME` still defaulting to `Laravel`. Going public needs a page to go public onto — right now there is nothing that tells a stranger what this is, who it's for, or how to run it. This is that page.

Inspiration: [opengym.duarte-santos.ch](https://opengym.duarte-santos.ch) — single-scroll open-source project page, dark theme, feature blocks with sub-cards, a screenshot carousel, an open-source stats section, self-host instructions. The shape transfers; the content doesn't — openGym is a single-user mobile/self-host tool with a live demo, Auri is a three-audience business tool (studio staff, booked clients, wedding guests) with real financial and personal data, so a few of openGym's sections have no equivalent here and are dropped rather than faked.

## 2. Scope

**In:** a new `apps/marketing` Astro app, four pages (`/`, `/features`, `/docs`, `/about`), German copy only, deploy wiring (compose service, README/architecture updates), a manual screenshot-capture pass against seeded local data.

**Out, named so the omissions are decisions:** a live in-browser demo (real client/financial data flows make a public demo instance its own project — seed isolation, reset cadence — not a page-design concern); English/i18n (product UI is German-only today, matching it); a mobile app section (none exists, unlike openGym's app-vs-self-host duality); a Discord/support-channel section (no such channel exists yet); an automated migration/import feature card (the `Migration` module has `PlanHistoricalImport`/`CommitHistoricalImport` actions in code, but per direct correction there is no working import run today — the page must not claim a feature that doesn't work); a tech-stack rationale on `/about` (the reader is a photographer, not a developer — why Laravel-plus-Astro belongs in `docs/architecture.md`, not here); `CONTRIBUTING.md`/`CHANGELOG.md` (neither exists — the Quelloffen section links only to the repo and the license, not to files that don't exist on their own schedule yet); a logo mark, custom favicon, or designed OG-image (v1 ships wordmark-only — "Auri" as text, a generic favicon, a text-based OG card; an actual mark is a design decision this spec doesn't make); rebranding `APP_NAME` away from its `"Laravel"` default in the running product (the marketing site says "Auri", the product itself doesn't yet — that's a separate, later task, not silently folded into a marketing-page ticket); a "no telemetry" claim (the stack does support optional self-hosted GlitchTip error monitoring — true, but nobody asked for this claim, and it sits one skeptical read away from contradicting the page's own client-side GitHub-API fetch); a live numeric GitHub-stats widget (see §5, the repo has 0 stars/0 forks today — the widget ships once there's a real number, not on day one); a purchased domain (none exists yet — see §8).

**License:** AGPL-3.0, decided over MIT specifically to close the SaaS-resale loophole — the same reasoning openGym gives for its own AGPL choice. Applies to both repos now (§4) — the product (`czepter/auri-crm`) and the marketing site itself (`czepter/auri-crm-site`), for consistency across everything the org publishes. A `LICENSE` file at each repo's root, and flipping `auri-crm` from private to public, are **hard prerequisites** the marketing page's deploy is gated behind — not concurrent work, not a "ships first, catches up later." The two repos are split for ownership/CI reasons only (§4); in real-world timing they still go public together, so a visitor who lands on the marketing site can always actually clone the product it's marketing. A public page asserting "open source, AGPL-3.0" while the product repo is still private is the same defect as claiming a test passes without having run it.

## 3. Why static, not SSR

`apps/gallery` is SSR because which `GalleryVersion` is current can change between two requests — the astro.config.mjs comment for that app says so directly. Nothing on the marketing page has that property: the copy, the screenshots, the feature list are all build-time facts. Giving this app a Node runtime and a `docker/marketing/Dockerfile` shaped like `docker/gallery/Dockerfile` would be provisioning a server process to solve a problem that doesn't exist here — `output: 'static'`, served by a plain `nginx:alpine`, is the whole runtime.

The one piece of the page that changes without a deploy — GitHub star/fork/issue counts on the "Quelloffen" section — doesn't need SSR either. openGym's own counts are live in the same static-feeling page, which means they're a client-side fetch against the GitHub API on load, not a server render. Same approach here: a small script tag, not a backend.

## 4. Repo layout

**A dedicated repo, `czepter/auri-crm-site`, not `apps/marketing` inside `auri-crm`.** Not the original plan — revised because the marketing site is meant to be able to go live on its own release cadence, independent of the product repo's own CI/build/PR flow, even though in practice both still flip public at the same real-world moment (§2). Splitting it out also means it never touches the `studio-workspace` pnpm workspace, `@studio/*` package scope, or the product's own Vitest/lint pipeline — it has none of those dependencies, so it shouldn't share their config surface just because it happens to live in the same place today.

```
czepter/auri-crm-site/          (own repo, own git history, own GitHub Actions)
  astro.config.mjs               output: 'static', @tailwindcss/vite (same Tailwind 4 major as auri-crm's studio/gallery, so the two don't drift onto two different design systems for no reason)
  package.json                   plain package.json, name "auri-crm-site" — no pnpm workspace, no @studio/* scope
  LICENSE                        AGPL-3.0, matching auri-crm's own
  src/
    pages/
      index.astro
      features.astro
      docs.astro
      about.astro
    components/                  Nav, Hero, FeatureGrid, ScreenshotCarousel, SelfHostBlock, OpenSourceStats, Footer
    content/                     feature copy as plain .ts data, no CMS
  public/
    screenshots/                 the four to five captures from §7 — copied in by hand after being captured from the (private, at capture time) auri-crm dev stack; there is no automated cross-repo pipeline for this, and building one for a one-time asset drop would be exactly the speculative infrastructure CLAUDE.md rejects
```

No `docker/marketing/Dockerfile` in `auri-crm` — the repo's own Dockerfile (`nginx:alpine` serving the built `dist/`, no Node in the runtime stage) lives at `czepter/auri-crm-site`'s root instead (§8).

## 5. Landing page (`/`) — sections

| # | Section | Content |
|---|---|---|
| 1 | Nav | Logo "Auri" (wordmark, no mark), anchors (Funktionen, Screenshots, Selbst hosten, Quelloffen), plain GitHub link (no star count in v1 — see row 7), primary CTA → `#selbst-hosten` |
| 2 | Hero | Eyebrow "Auri", three-line headline, subcopy naming what it replaces (Pixieset/Studio Ninja + spreadsheets + WeTransfer), CTAs "Auf GitHub ansehen" / "Selbst hosten", hero screenshot: **the admin board** — the audience is the photographer evaluating the tool for themselves, not their client, so the portal/gallery views stay in the screenshot carousel further down and don't compete for the hero slot |
| 3 | Warum | The tool-stack-replacement angle plus EU-Hosting/DSGVO as the concrete differentiator against Pixieset (US-hosted) |
| 4 | Funktionen (teaser) | Three condensed blocks — CRM, Kundenportal, Gästegalerie — same copy reused verbatim from the matching `/features` cards, link "Alle Funktionen →" |
| 5 | Screenshots | Carousel, four to five real captures (§7) |
| 6 | Selbst hosten | Condensed `docker compose up` path, link to `/docs` for the full walkthrough, EU-hosting fine print |
| 7 | Quelloffen | AGPL-3.0 callout, link to the repo. **No numeric stats widget in v1** — the repo has 0 stars/0 forks on day one, and zero next to "look how open-source this is" undercuts the section it's meant to support. The live GitHub-stats fetch (client-side, hides silently rather than falling back to a stale build-time number on API rate-limit) is a follow-up once there's a real count to show; adding it later is a component/copy toggle, not a redesign. No CONTRIBUTING/CHANGELOG links — neither file exists yet (§2) |
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

No screenshots exist anywhere in the repo today. `apps/studio/database/seeders/DemoCrmSeeder.php` already builds a realistic CRM/quote/contract/payment/gallery cast through models — but it exists for a specific internal UI mockup narrative (its own docblock ties it to "mockup 02"), with client names and scenario details written for internal review, not for a public screenshot that lives on the internet indefinitely. Reusing it would couple what ships publicly forever to whatever that seeder is doing for internal demos next sprint. Plan:

1. Write a small dedicated seeder (`apps/studio/database/seeders/MarketingScreenshotSeeder.php` or similar, Laravel-side since it needs real models — `apps/marketing` has no database access) with data that reads as real but is written specifically to be shown publicly: a handful of contacts/projects/shoots across board stages, one quote/contract/payment plan in a realistic state, one gallery with placeholder photos and a couple of favorites/comments.
2. Capture four to five shots at a fixed viewport against the running dev stack (`dev-server.sh` for admin, `docker compose up -d portal api gallery` for the rest): admin board (the hero shot, §5), portal overview, gallery grid, gallery detail with comments.
3. Drop the files into `apps/marketing/public/screenshots/`. One manual pass, not a pipeline — there's no recurring need to regenerate these until the UI they show changes materially.

## 8. Deploy wiring

Because `czepter/auri-crm-site` is its own repo rather than a service inside `auri-crm`'s compose files (§4), it deploys as its **own standalone Coolify application** with its own git source — not a new entry in `docker-compose.yml`/`docker-compose.coolify.yml`, and not part of the existing `auri-crm` Coolify project (verified live: that project currently bundles `admin`, `portal`, `api`, `gallery`, `mcp` plus support containers as one compose-based service). A plain static site with its own Dockerfile doesn't belong wedged into that bundle just because it's related in subject matter.

- **New Coolify application**, source = `czepter/auri-crm-site`, build = the repo's own `Dockerfile` (`nginx:alpine`, no build args shared with `auri-crm`).
- **Domain:** none exists anywhere yet — every currently deployed `auri-crm` service sits on a Coolify-generated subdomain under the shared `coolify3.czpt.de` wildcard. The new application ships the same way at first (its own generated subdomain); canonical URLs, the OG-image `url` field, and any cross-links from `auri-crm`'s own README swap to a real domain once one is bought — that purchase is the user's decision, not discoverable or choosable by this design.
- **In `auri-crm` itself:** no `docker/marketing/`, no compose service, no `apps/marketing` — the only touch points are a short mention in `README.md` (a link to the marketing site once it has a URL) and, optionally, one line in `docs/architecture.md` noting a marketing site exists in a separate repo and isn't a `Surface` — worded that way because `Surface` means an authenticated Laravel route file, and this has neither authentication nor a Laravel dependency, in either repo.
- Testing (in `auri-crm-site`): `vitest run --passWithNoTests` scaffold, matching the pattern `apps/gallery/package.json` already uses. No forced test beyond that — CLAUDE.md's "non-trivial logic ships with a test" rule doesn't reach markup and copy, and inventing logic to test would be exactly the speculative work CLAUDE.md rejects. `auri-crm-site` gets its own minimal CI (build + lint), independent of `auri-crm`'s pipeline.
- **Launch gate, explicit:** the marketing site must not go live in front of real visitors until (a) `auri-crm` is flipped from private to public, (b) `LICENSE` (AGPL-3.0) exists at both repos' roots. Both are prerequisites the implementation plan should carry as blocking steps, not follow-up work — see §2.

## 9. Follow-ups, explicitly out of scope here

- **Domain purchase.** Nobody owns one yet; §8 covers how the design tolerates that gap until one exists.
- **`CONTRIBUTING.md` / `CHANGELOG.md`** — for `auri-crm`, the product repo. Real content (PR process, coding standards pointer; a backfilled history), on their own schedule — not a marketing-page dependency (§2).
- **A logo/brand mark.** v1 is wordmark-only; an actual mark is its own design decision.
- **`APP_NAME` rebrand.** The running product still defaults to `"Laravel"`; making it say "Auri" touches emails, PDFs, admin chrome — a separate, later task (§2).
- **The live GitHub-stats widget.** Built once `auri-crm` has a real star count worth showing (§5 row 7) — the client-side fetch and its rate-limit fallback are specified here so implementing it later doesn't need a second design pass.
- **`czepter/auri-crm-site`'s own repo hygiene** (its own minimal README describing what it is and how to run it locally) — small, but it's a repo-setup task once the repo exists, not part of this design's content.
- Actual German copy (headlines, subcopy, card descriptions) is a content-writing pass against this structure, not fixed by this design — CLAUDE.md's no-AI-slop German copy rule applies directly when it's written.
