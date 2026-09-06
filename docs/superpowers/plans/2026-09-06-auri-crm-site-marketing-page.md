# Auri marketing site (`czepter/auri-crm-site`) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a static German-language marketing site for Auri (the open-source photography CRM) at `czepter/auri-crm-site`: a landing page, `/features`, `/docs`, `/about`, deployable as its own Coolify application.

**Architecture:** Astro 7, `output: 'static'`, Tailwind 4 via `@tailwindcss/vite` — no server runtime, no database, no dependency on the `auri-crm` monorepo at build or run time. Served by a plain `nginx:alpine` container. Lives in its own repo so it can release on its own cadence, independent of `auri-crm`'s CI (though both still flip public together at real launch — see Global Constraints).

**Tech Stack:** Astro 7, Tailwind 4, TypeScript, Vitest (logic only, no component-render tests — matches `apps/gallery`'s own testing convention: `.astro` markup is untested, `.ts` logic gets Vitest).

**Spec:** [docs/superpowers/specs/2026-09-06-auri-marketing-page-design.md](../specs/2026-09-06-auri-marketing-page-design.md) (in `auri-crm`, this repo) — read it alongside this plan; this plan does not repeat its reasoning, only its decisions.

## Global Constraints

- Copy is **German only**. No AI-slop copy: short native nouns, no reassurance ("...ist trotzdem möglich"), no system narration, no restating a control's own label, no rule-of-three enumeration for rhythm. Every string in this plan is written to that bar already — do not "improve" it into something longer.
- `output: 'static'` in `astro.config.mjs`. No SSR adapter, no Node runtime in the production image.
- Tailwind 4, same major version as `apps/gallery`/`apps/studio` in `auri-crm` (`^4.0.0`), via `@tailwindcss/vite`.
- License: **AGPL-3.0**, `LICENSE` file at repo root, from the very first commit.
- **No** `CONTRIBUTING.md`/`CHANGELOG.md` links in this site — neither exists yet in `auri-crm`.
- **Wordmark only.** No logo mark. Favicon is a plain generated one (see Task 2), no custom brand asset.
- **No** live GitHub star/fork count widget. The "Quelloffen" section links to the repo with no numbers attached.
- **No** "no telemetry" claim anywhere in the copy.
- Hero screenshot is the **admin board** — not the portal, not the gallery.
- Screenshot seed data comes from a **new, dedicated seeder** in `auri-crm` (`apps/studio/database/seeders/MarketingScreenshotSeeder.php`), not `DemoCrmSeeder`.
- Domain is **not yet purchased**. Every URL, canonical tag, and OG `url` field uses a placeholder (`https://auri-crm-site.example` — see Task 2) marked with a single `// TODO(domain)` comment at its one definition site, so swapping it later is a one-line change, not a grep-and-replace across the codebase.
- This site must not be pointed at by real traffic until `auri-crm` itself is public and both repos have their `LICENSE` files — that gate is operational (flipping GitHub visibility, buying a domain), not a task in this plan.

---

## File structure

```
czepter/auri-crm-site/            (new local clone, sibling to this worktree's parent — Task 1)
  package.json
  astro.config.mjs
  tsconfig.json
  vitest.config.ts
  LICENSE
  .gitignore
  README.md
  Dockerfile
  src/
    layouts/
      BaseLayout.astro             <head>, meta, favicon, global.css import, slot
    components/
      Nav.astro
      Footer.astro
      Hero.astro
      FeatureCard.astro            title + description, `size` prop ("lg" | "sm")
      SelfHostSteps.astro          the docker-compose command sequence, shared by landing + /docs
      ScreenshotCarousel.astro
    content/
      features.ts                  14 feature entries + getFeature(key) + getLandingTeaserFeatures()
      features.test.ts
    styles/
      global.css                   Tailwind import + dark theme tokens
    pages/
      index.astro
      features.astro
      docs.astro
      about.astro
  public/
    favicon.svg
    screenshots/                   populated by Task 9-10

apps/studio/database/seeders/       (in THIS repo, auri-crm — Task 9 only)
  MarketingScreenshotSeeder.php
```

---

### Task 1: Repo scaffold

**Files:**
- Create (in a new local clone at `~/www/auri-crm-site`, cloned from `git@github.com:czepter/auri-crm-site.git`): `package.json`, `astro.config.mjs`, `tsconfig.json`, `vitest.config.ts`, `.gitignore`, `LICENSE`, `README.md`, `src/pages/index.astro`

**Interfaces:**
- Produces: an Astro project that builds to `dist/`, importable by every later task in this plan.

- [ ] **Step 1: Clone the empty repo**

```bash
cd ~/www
git clone git@github.com:czepter/auri-crm-site.git
cd auri-crm-site
```

- [ ] **Step 2: Write `package.json`**

```json
{
  "name": "auri-crm-site",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "astro dev",
    "build": "astro build",
    "preview": "astro preview",
    "lint": "astro check",
    "test": "vitest run --passWithNoTests"
  },
  "dependencies": {
    "astro": "^7.2.0"
  },
  "devDependencies": {
    "@astrojs/check": "^0.9.10",
    "@tailwindcss/vite": "^4.0.0",
    "tailwindcss": "^4.0.0",
    "typescript": "^6.0.3",
    "vitest": "^4.1.10"
  }
}
```

- [ ] **Step 3: Write `astro.config.mjs`**

```js
import { defineConfig } from 'astro/config'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  output: 'static',
  vite: { plugins: [tailwindcss()] },
})
```

- [ ] **Step 4: Write `tsconfig.json`**

```json
{
  "extends": "astro/tsconfigs/strict"
}
```

- [ ] **Step 5: Write `vitest.config.ts`**

```ts
import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    environment: 'node',
    include: ['src/**/*.{test,spec}.ts'],
  },
})
```

- [ ] **Step 6: Write `.gitignore`**

```
node_modules/
dist/
.astro/
```

- [ ] **Step 7: Write `LICENSE`**

Fetch the canonical AGPL-3.0 text and save verbatim:

```bash
curl -s https://www.gnu.org/licenses/agpl-3.0.txt -o LICENSE
```

- [ ] **Step 8: Write `README.md`**

```markdown
# Auri — marketing site

The landing page for [Auri](https://github.com/czepter/auri-crm), an
open-source CRM, client portal and guest gallery for photography studios.

## Develop

    pnpm install
    pnpm dev

## Build

    pnpm build   # -> dist/
```

- [ ] **Step 9: Write a stub `src/pages/index.astro`**

```astro
---
---
<html lang="de">
  <body>
    <h1>Auri</h1>
  </body>
</html>
```

- [ ] **Step 10: Install and build**

```bash
corepack enable
pnpm install
pnpm build
```

Expected: `dist/index.html` exists and contains `<h1>Auri</h1>`.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "chore: scaffold Astro static site"
git push -u origin main
```

---

### Task 2: Base layout, Nav, Footer, dark theme

**Files:**
- Create: `src/styles/global.css`, `src/layouts/BaseLayout.astro`, `src/components/Nav.astro`, `src/components/Footer.astro`, `public/favicon.svg`
- Modify: `src/pages/index.astro`

**Interfaces:**
- Produces: `BaseLayout` — props `{ title: string; description: string }`, renders `<head>` + `<slot />` wrapped by `<Nav />` and `<Footer />`.
- Consumes (later tasks): every page imports `BaseLayout` from `../layouts/BaseLayout.astro`.

- [ ] **Step 1: Write `src/styles/global.css`**

```css
@import "tailwindcss";

:root {
  --bg: #0b0b0c;
  --fg: #f5f5f4;
  --muted: #8a8a8e;
  --accent: #7fffa0;
  --border: #232326;
}

body {
  background: var(--bg);
  color: var(--fg);
  font-family: ui-sans-serif, system-ui, sans-serif;
}
```

- [ ] **Step 2: Write `public/favicon.svg`** (plain generated mark, wordmark-only per Global Constraints — a single letter, not a logo)

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
  <rect width="32" height="32" rx="6" fill="#0b0b0c"/>
  <text x="16" y="22" font-family="ui-sans-serif,system-ui,sans-serif" font-size="18" font-weight="700" fill="#7fffa0" text-anchor="middle">A</text>
</svg>
```

- [ ] **Step 3: Write `src/components/Nav.astro`**

```astro
---
const links = [
  { href: '/#funktionen', label: 'Funktionen' },
  { href: '/#screenshots', label: 'Screenshots' },
  { href: '/#selbst-hosten', label: 'Selbst hosten' },
  { href: '/#quelloffen', label: 'Quelloffen' },
]
---
<nav class="flex items-center justify-between px-6 py-4 border-b border-[var(--border)]">
  <a href="/" class="font-bold text-lg">Auri</a>
  <div class="hidden md:flex gap-6 text-sm text-[var(--muted)]">
    {links.map((l) => <a href={l.href} class="hover:text-[var(--fg)]">{l.label}</a>)}
  </div>
  <div class="flex items-center gap-4 text-sm">
    <a href="https://github.com/czepter/auri-crm" class="hover:text-[var(--fg)]">GitHub</a>
    <a href="/#selbst-hosten" class="rounded-full bg-[var(--accent)] text-black px-4 py-2 font-medium">Selbst hosten</a>
  </div>
</nav>
```

- [ ] **Step 4: Write `src/components/Footer.astro`**

```astro
---
const year = new Date().getFullYear()
---
<footer class="px-6 py-8 border-t border-[var(--border)] text-sm text-[var(--muted)] flex flex-wrap gap-4 justify-between">
  <span>Auri · AGPL-3.0 · © {year}</span>
  <div class="flex gap-4">
    <a href="/features">Funktionen</a>
    <a href="/docs">Docs</a>
    <a href="/about">About</a>
    <a href="https://github.com/czepter/auri-crm">GitHub</a>
  </div>
</footer>
```

- [ ] **Step 5: Write `src/layouts/BaseLayout.astro`**

```astro
---
import Nav from '../components/Nav.astro'
import Footer from '../components/Footer.astro'
import '../styles/global.css'

interface Props {
  title: string
  description: string
}
const { title, description } = Astro.props
// TODO(domain): swap once a real domain is bought
const siteUrl = 'https://auri-crm-site.example'
---
<html lang="de">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{title}</title>
    <meta name="description" content={description} />
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <meta property="og:title" content={title} />
    <meta property="og:description" content={description} />
    <meta property="og:url" content={siteUrl} />
    <meta property="og:type" content="website" />
  </head>
  <body>
    <Nav />
    <main>
      <slot />
    </main>
    <Footer />
  </body>
</html>
```

- [ ] **Step 6: Wire the stub page through the layout**

```astro
---
import BaseLayout from '../layouts/BaseLayout.astro'
---
<BaseLayout title="Auri" description="Auri — offene CRM-Software für Fotostudios.">
  <h1 class="text-4xl font-bold px-6 py-24">Auri</h1>
</BaseLayout>
```

- [ ] **Step 7: Build and verify**

```bash
pnpm build
grep -q 'Selbst hosten' dist/index.html && grep -q 'AGPL-3.0' dist/index.html && echo OK
```

Expected: `OK`

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: base layout, nav, footer"
```

---

### Task 3: Feature content data

**Files:**
- Create: `src/content/features.ts`, `src/content/features.test.ts`

**Interfaces:**
- Produces: `type Feature = { key: string; title: string; description: string }`, `FEATURES: Feature[]`, `getFeature(key: string): Feature`, `getLandingTeaserFeatures(): Feature[]` (returns the `crm`, `portal`, `galerie` entries, in that order).
- Consumes (later tasks): `/features` page renders all of `FEATURES`; landing page's Funktionen section calls `getLandingTeaserFeatures()`.

- [ ] **Step 1: Write the failing test**

```ts
// src/content/features.test.ts
import { describe, expect, it } from 'vitest'
import { FEATURES, getFeature, getLandingTeaserFeatures } from './features'

describe('features', () => {
  it('has fourteen entries, no migration/import card', () => {
    expect(FEATURES).toHaveLength(14)
    expect(FEATURES.find((f) => f.key === 'migration')).toBeUndefined()
  })

  it('getFeature returns the matching entry', () => {
    expect(getFeature('crm').title).toBe('Anfragen & Board')
  })

  it('getFeature throws on an unknown key', () => {
    expect(() => getFeature('nope')).toThrow()
  })

  it('getLandingTeaserFeatures returns crm, portal, galerie in order', () => {
    expect(getLandingTeaserFeatures().map((f) => f.key)).toEqual(['crm', 'portal', 'galerie'])
  })
})
```

- [ ] **Step 2: Run it, confirm it fails**

```bash
pnpm vitest run src/content/features.test.ts
```

Expected: FAIL — `features.ts` doesn't exist yet.

- [ ] **Step 3: Write `src/content/features.ts`**

```ts
export interface Feature {
  key: string
  title: string
  description: string
}

export const FEATURES: Feature[] = [
  { key: 'crm', title: 'Anfragen & Board', description: 'Ein Kanban-Board für jede Anfrage, von der ersten Nachricht bis zum gebuchten Termin.' },
  { key: 'catalog', title: 'Leistungspakete', description: 'Pakete und Einzelposten, einmal angelegt und in jedem Angebot wiederverwendbar.' },
  { key: 'quotes', title: 'Angebote, online annehmbar', description: 'Der Kunde nimmt online an – keine Unterschrift per Rückmail nötig.' },
  { key: 'contracts', title: 'Verträge mit digitaler Unterschrift', description: 'Vertrag erstellen, Link verschicken, Unterschrift kommt zurück.' },
  { key: 'documents', title: 'Eine Vorlage, drei Dokumenttypen', description: 'Angebot, Vertrag und Rechnung teilen sich dieselben Textbausteine.' },
  { key: 'payments', title: 'Zahlungspläne & Stripe', description: 'Raten anlegen, Fälligkeiten im Blick, Zahlung per Stripe.' },
  { key: 'numbering', title: 'Fortlaufende Rechnungsnummernkreise', description: 'Nummern laufen durch, auch über mehrere Jahre und Dokumenttypen hinweg.' },
  { key: 'media', title: 'Mehrfach-Upload & Ableitungen', description: 'Hunderte Fotos auf einmal hochladen, Vorschauen entstehen automatisch.' },
  { key: 'galerie', title: 'Passwort- & PIN-geschützte Gästegalerie', description: 'Zugriff über Passwort, Download nur mit eigener PIN.' },
  { key: 'portal', title: 'Eigenes Kundenportal', description: 'Der Kunde sieht Angebot, Vertrag, Zahlungsplan und Galerie an einem Ort.' },
  { key: 'engagement', title: 'Nachrichten zwischen Studio und Kunde', description: 'Ein Gesprächsfaden pro Auftrag, sichtbar für beide Seiten.' },
  { key: 'automation', title: 'Automationen nach Ereignis oder Datum', description: 'Regel einmal anlegen: bei Buchung oder X Tage vor dem Termin.' },
  { key: 'integration', title: 'Öffentliche API, Webhooks, MCP-Server', description: 'Eigene Integrationen bauen, oder eine KI direkt an die Daten lassen.' },
  { key: 'settings', title: 'Portal-Funktionen einzeln abschaltbar', description: 'Zahlungen, Nachrichten oder Galerie – jede Funktion für sich an- oder ausschaltbar.' },
]

export function getFeature(key: string): Feature {
  const found = FEATURES.find((f) => f.key === key)
  if (!found) throw new Error(`Unknown feature key: ${key}`)
  return found
}

export function getLandingTeaserFeatures(): Feature[] {
  return ['crm', 'portal', 'galerie'].map(getFeature)
}
```

- [ ] **Step 4: Run the test, confirm it passes**

```bash
pnpm vitest run src/content/features.test.ts
```

Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: feature content data"
```

---

### Task 4: Landing page — Hero, Warum, Funktionen teaser

**Files:**
- Create: `src/components/Hero.astro`, `src/components/FeatureCard.astro`
- Modify: `src/pages/index.astro`

**Interfaces:**
- Produces: `FeatureCard` — props `{ title: string; description: string; size?: 'lg' | 'sm' }` (default `'sm'`), reused unchanged by Task 6's `/features` grid.
- Consumes: `getLandingTeaserFeatures` from Task 3.

- [ ] **Step 1: Write `src/components/FeatureCard.astro`**

```astro
---
interface Props {
  title: string
  description: string
  size?: 'lg' | 'sm'
}
const { title, description, size = 'sm' } = Astro.props
---
<article class={size === 'lg' ? 'p-8 rounded-xl border border-[var(--border)]' : 'p-5 rounded-lg border border-[var(--border)]'}>
  <h3 class={size === 'lg' ? 'text-xl font-semibold mb-2' : 'font-semibold mb-1'}>{title}</h3>
  <p class="text-[var(--muted)] text-sm">{description}</p>
</article>
```

- [ ] **Step 2: Write `src/components/Hero.astro`**

```astro
---
---
<section class="px-6 pt-20 pb-16 text-center">
  <p class="text-[var(--muted)] text-sm mb-4">Auri</p>
  <h1 class="text-5xl md:text-6xl font-bold leading-tight">
    Deine Aufträge.<br />
    Deine Galerien.<br />
    <span class="text-[var(--muted)]">Deine Daten.</span>
  </h1>
  <p class="mt-6 text-lg text-[var(--muted)] max-w-xl mx-auto">
    Ersetzt Pixieset und Studio Ninja. Ein CRM, ein Kundenportal, eine Gästegalerie – auf einem Server in der EU.
  </p>
  <div class="mt-8 flex gap-4 justify-center">
    <a href="https://github.com/czepter/auri-crm" class="rounded-full bg-[var(--accent)] text-black px-6 py-3 font-medium">Auf GitHub ansehen</a>
    <a href="/#selbst-hosten" class="rounded-full border border-[var(--border)] px-6 py-3 font-medium">Selbst hosten</a>
  </div>
  <img
    src="/screenshots/admin-board.png"
    alt="Auri-Board mit Anfragen, Aufträgen und Terminen"
    class="mt-16 rounded-xl border border-[var(--border)] mx-auto max-w-4xl w-full"
  />
</section>
```

Note: `admin-board.png` doesn't exist until Task 10 — the `<img>` 404s harmlessly in dev until then; this is expected and resolved by Task 10, not a bug to fix now.

- [ ] **Step 3: Assemble the top of `src/pages/index.astro`**

```astro
---
import BaseLayout from '../layouts/BaseLayout.astro'
import Hero from '../components/Hero.astro'
import FeatureCard from '../components/FeatureCard.astro'
import { getLandingTeaserFeatures } from '../content/features'

const teaser = getLandingTeaserFeatures()
---
<BaseLayout title="Auri — offenes CRM für Fotostudios" description="Auri ersetzt Pixieset und Studio Ninja: CRM, Kundenportal und Gästegalerie, selbst gehostet in der EU.">
  <Hero />

  <section class="px-6 py-16 max-w-3xl mx-auto text-center">
    <h2 class="text-3xl font-bold mb-4">Kein Abo. Kein US-Server.</h2>
    <p class="text-[var(--muted)]">
      Wunschtermine in einer Tabelle, Verträge per WeTransfer, Rechnungen in einem zweiten Tool.
      Auri bündelt Anfragen, Verträge, Zahlungen und die Gästegalerie an einem Ort –
      auf einem Server in der EU, den das Studio selbst betreibt.
    </p>
  </section>

  <section id="funktionen" class="px-6 py-16">
    <h2 class="text-3xl font-bold text-center mb-10">Funktionen</h2>
    <div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
      {teaser.map((f) => <FeatureCard title={f.title} description={f.description} size="lg" />)}
    </div>
    <p class="text-center mt-8">
      <a href="/features" class="text-[var(--accent)]">Alle Funktionen →</a>
    </p>
  </section>
</BaseLayout>
```

- [ ] **Step 4: Build and verify**

```bash
pnpm build
grep -q 'Kein Abo' dist/index.html && grep -q 'Alle Funktionen' dist/index.html && echo OK
```

Expected: `OK`

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: landing hero, warum, funktionen teaser"
```

---

### Task 5: Landing page — Selbst hosten, Quelloffen

**Files:**
- Create: `src/components/SelfHostSteps.astro`
- Modify: `src/pages/index.astro`

**Interfaces:**
- Produces: `SelfHostSteps` — props `{ compact?: boolean }` (default `false`); `compact` renders only the command block, non-compact adds a one-line caption per step. Reused unchanged by Task 7's `/docs` page with `compact={false}`.

- [ ] **Step 1: Write `src/components/SelfHostSteps.astro`**

```astro
---
interface Props {
  compact?: boolean
}
const { compact = false } = Astro.props

const steps = [
  { cmd: 'pnpm install', caption: 'Abhängigkeiten installieren' },
  { cmd: 'docker compose up -d', caption: 'Postgres, Redis, Storage starten' },
  { cmd: 'pnpm php composer install', caption: 'PHP-Abhängigkeiten installieren' },
  { cmd: 'pnpm php php artisan migrate --seed', caption: 'Datenbank aufsetzen' },
  { cmd: './dev-server.sh', caption: 'Auri starten' },
]
---
<div class="rounded-lg border border-[var(--border)] bg-black/30 p-4 font-mono text-sm">
  {steps.map((s) => (
    <div class="py-1">
      <span>$ {s.cmd}</span>
      {!compact && <span class="text-[var(--muted)] ml-3"># {s.caption}</span>}
    </div>
  ))}
</div>
```

- [ ] **Step 2: Add the two sections to `src/pages/index.astro`**, right before `</BaseLayout>`:

```astro
  <section id="selbst-hosten" class="px-6 py-16 max-w-2xl mx-auto">
    <h2 class="text-3xl font-bold mb-4">In einer Stunde selbst gehostet.</h2>
    <p class="text-[var(--muted)] mb-6">Docker, Node 24 und pnpm – kein weiterer Dienst nötig.</p>
    <SelfHostSteps compact />
    <p class="mt-4 text-sm text-[var(--muted)]">
      EU-Server, volle Kontrolle über die Daten.
      <a href="/docs" class="text-[var(--accent)]">Zur Installationsanleitung →</a>
    </p>
  </section>

  <section id="quelloffen" class="px-6 py-16 max-w-2xl mx-auto text-center">
    <h2 class="text-3xl font-bold mb-4">AGPL-3.0 – jeder Fork bleibt offen.</h2>
    <p class="text-[var(--muted)] mb-6">
      Der Code ist öffentlich, jede Ableitung bleibt es auch.
      Keine Enterprise-Version, keine Paywall für Funktionen.
    </p>
    <a href="https://github.com/czepter/auri-crm" class="rounded-full bg-[var(--accent)] text-black px-6 py-3 font-medium">Auf GitHub ansehen</a>
  </section>
```

And add the import at the top:

```astro
import SelfHostSteps from '../components/SelfHostSteps.astro'
```

- [ ] **Step 3: Build and verify**

```bash
pnpm build
grep -q 'jeder Fork bleibt offen' dist/index.html && grep -c 'docker compose up -d' dist/index.html
```

Expected: match found, count ≥ 1.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: landing selbst-hosten and quelloffen sections"
```

---

### Task 6: `/features` page

**Files:**
- Create: `src/pages/features.astro`

**Interfaces:**
- Consumes: `FEATURES` from Task 3, `FeatureCard` from Task 4.

- [ ] **Step 1: Write `src/pages/features.astro`**

```astro
---
import BaseLayout from '../layouts/BaseLayout.astro'
import FeatureCard from '../components/FeatureCard.astro'
import { FEATURES } from '../content/features'
---
<BaseLayout title="Funktionen — Auri" description="Alle Funktionen von Auri: CRM, Angebote, Verträge, Zahlungen, Kundenportal und Gästegalerie.">
  <section class="px-6 py-16">
    <h1 class="text-4xl font-bold text-center mb-10">Alle Funktionen</h1>
    <div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
      {FEATURES.map((f) => <FeatureCard title={f.title} description={f.description} />)}
    </div>
  </section>
</BaseLayout>
```

- [ ] **Step 2: Build and verify**

```bash
pnpm build
grep -c 'rounded-lg border' dist/features/index.html
```

Expected: 14 (one per feature card).

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: features page"
```

---

### Task 7: `/docs` page

**Files:**
- Create: `src/pages/docs.astro`

**Interfaces:**
- Consumes: `SelfHostSteps` from Task 5, called with `compact={false}`.

- [ ] **Step 1: Write `src/pages/docs.astro`**

```astro
---
import BaseLayout from '../layouts/BaseLayout.astro'
import SelfHostSteps from '../components/SelfHostSteps.astro'

const ports = [
  { surface: 'Admin', port: '8000' },
  { surface: 'Portal', port: '8001' },
  { surface: 'API', port: '8002' },
  { surface: 'Gallery', port: '4321' },
]
---
<BaseLayout title="Installation — Auri" description="Auri selbst hosten: Voraussetzungen, Setup, Ports pro Surface.">
  <section class="px-6 py-16 max-w-2xl mx-auto">
    <h1 class="text-4xl font-bold mb-8">Installation</h1>

    <h2 class="text-xl font-semibold mb-2">Voraussetzungen</h2>
    <p class="text-[var(--muted)] mb-6">Docker, Node 24, pnpm 10. Kein lokales PHP nötig – jeder PHP-Prozess läuft im Container.</p>

    <h2 class="text-xl font-semibold mb-2">Setup</h2>
    <div class="mb-6">
      <SelfHostSteps />
    </div>

    <h2 class="text-xl font-semibold mb-2">Ports pro Surface</h2>
    <table class="w-full text-sm mb-6">
      <thead>
        <tr class="text-left text-[var(--muted)]"><th>Surface</th><th>Port</th></tr>
      </thead>
      <tbody>
        {ports.map((p) => (
          <tr class="border-t border-[var(--border)]">
            <td class="py-2">{p.surface}</td>
            <td class="py-2">{p.port}</td>
          </tr>
        ))}
      </tbody>
    </table>

    <p class="text-[var(--muted)]">
      Die vollständige Anleitung, inklusive Coolify-Deploy, steht im
      <a href="https://github.com/czepter/auri-crm" class="text-[var(--accent)]">Repository</a>.
    </p>
  </section>
</BaseLayout>
```

- [ ] **Step 2: Build and verify**

```bash
pnpm build
grep -q 'Ports pro Surface' dist/docs/index.html && echo OK
```

Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: docs page"
```

---

### Task 8: `/about` page

**Files:**
- Create: `src/pages/about.astro`

- [ ] **Step 1: Write `src/pages/about.astro`**

```astro
---
import BaseLayout from '../layouts/BaseLayout.astro'
---
<BaseLayout title="Über Auri" description="Warum Auri existiert: für ein echtes Fotostudio gebaut, quelloffen, in der EU gehostet.">
  <section class="px-6 py-16 max-w-2xl mx-auto">
    <h1 class="text-4xl font-bold mb-8">Über Auri</h1>
    <p class="text-[var(--muted)] mb-4">
      Auri ist für ein einzelnes Fotostudio entstanden – als Ersatz für Pixieset, Studio Ninja
      und die Tabelle, die zwischen beiden vermittelt hat. Kein Werkzeug hat gepasst, ohne
      selbst zum zweiten Job zu werden.
    </p>
    <p class="text-[var(--muted)] mb-4">
      Der Code ist quelloffen unter AGPL-3.0, weil ein Studio seine Kunden- und Vertragsdaten
      nicht einem Anbieter überlassen sollte, der morgen die Preise ändert oder abschaltet.
      Jede Ableitung bleibt offen – auch eine gehostete Version müsste ihren Code teilen.
    </p>
    <p class="text-[var(--muted)]">
      EU-Hosting ist keine Fußnote, sondern Bedingung: Kundendaten, Verträge und Zahlungen
      bleiben auf einem Server, den das Studio selbst wählt.
    </p>
  </section>
</BaseLayout>
```

- [ ] **Step 2: Build and verify**

```bash
pnpm build
grep -q 'AGPL-3.0' dist/about/index.html && echo OK
```

Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: about page"
```

---

### Task 9: Screenshot seed data (in `auri-crm`, this repo)

**Files:**
- Create: `apps/studio/database/seeders/MarketingScreenshotSeeder.php`

**Interfaces:**
- Produces: a runnable `db:seed --class=MarketingScreenshotSeeder` against a local dev database, independent of `DemoCrmSeeder` and `E2eSeeder`.

- [ ] **Step 1: Write the seeder**, following `DemoCrmSeeder`'s pattern of building through models/factories rather than raw inserts (`apps/studio/database/seeders/DemoCrmSeeder.php` is the reference for the actions/factories available — read it before writing this one). Content is written to be shown publicly indefinitely: no real names, a small realistic cast.

```php
<?php

namespace Database\Seeders;

use App\Contracts\Models\Contract;
use App\Crm\Actions\BookProject;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Gallery\Models\Gallery;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data for the auri-crm-site screenshot captures — written to be shown
 * publicly indefinitely, so it does not reuse DemoCrmSeeder's internal
 * mockup cast (which carries names and a scenario written for internal
 * review, not the public internet).
 */
class MarketingScreenshotSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $leads = [
            ['Anna Voss', 'wedding', LeadStage::New, 'Hof Bergmann', 'Lüneburg'],
            ['Familie Reuter', 'family', LeadStage::Contacted, null, 'Hamburg'],
            ['Jonas Klee', 'portrait', LeadStage::QuoteSent, 'Studio', 'Hamburg'],
        ];

        foreach ($leads as [$name, $type, $stage, $venue, $city]) {
            $contact = Contact::factory()->create(['name' => $name]);
            Lead::factory()->for($contact)->create([
                'type' => ProjectType::from($type),
                'stage' => $stage,
                'source' => LeadSource::Referral,
                'venue' => $venue,
                'city' => $city,
            ]);
        }

        $bookedContact = Contact::factory()->create(['name' => 'Melissa & Jan Ahrens']);
        $project = BookProject::run(
            Lead::factory()->for($bookedContact)->create([
                'type' => ProjectType::Wedding,
                'stage' => LeadStage::Booked,
            ]),
            date: Carbon::parse('2026-10-17'),
        );

        Shoot::factory()->for($project)->create([
            'kind' => ShootKind::Main,
            'status' => ShootStatus::Scheduled,
            'starts_at' => Carbon::parse('2026-10-17 14:00'),
        ]);

        $quote = Quote::factory()->for($project)->create(['status' => QuoteStatus::Accepted]);
        Contract::factory()->for($project)->rendered()->create();
        PaymentPlan::factory()->for($project->job)->create(['total' => 220000]);

        Gallery::factory()->for($project)->create([
            'title' => 'Melissa & Jan',
            'published_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Run it against the local dev database**

```bash
pnpm php php artisan db:seed --class=MarketingScreenshotSeeder
```

Expected: exits 0, no errors. If a factory state or action signature doesn't match (this codebase moves — check the actual current signatures of `BookProject::run`, `Quote`/`Contract`/`PaymentPlan`/`Gallery` factories before assuming the above is exact), fix the seeder to match what's actually there rather than what's written here — this file is a starting point, not a contract the rest of the codebase must bend to.

- [ ] **Step 3: Commit**

```bash
git add apps/studio/database/seeders/MarketingScreenshotSeeder.php
git commit -m "feat(studio): add marketing screenshot seeder"
```

---

### Task 10: Capture and wire in real screenshots

**Files:**
- Create (in `auri-crm-site`): `public/screenshots/admin-board.png`, `public/screenshots/portal.png`, `public/screenshots/galerie-grid.png`, `public/screenshots/galerie-detail.png`, `src/components/ScreenshotCarousel.astro`
- Modify (in `auri-crm-site`): `src/pages/index.astro`

**Interfaces:**
- Consumes: the seeded data from Task 9, running dev stack from `auri-crm`.
- Produces: `ScreenshotCarousel` — props `{ shots: { src: string; alt: string; caption: string }[] }`.

- [ ] **Step 1: Start the dev stack in `auri-crm`** (this repo)

```bash
./dev-server.sh &
docker compose up -d portal api gallery
```

- [ ] **Step 2: Capture four screenshots** at a fixed viewport (1600×1000) using the Browser tool, against the seeded data from Task 9:
  - Admin board (`localhost:8000`, the Kanban board with the three leads + the booked project) → save as `admin-board.png`
  - Portal overview (`localhost:8001`, the booked client's own view) → `portal.png`
  - Gallery grid (`localhost:4321`, "Melissa & Jan" gallery) → `galerie-grid.png`
  - Gallery detail view with the comment/favorite UI visible → `galerie-detail.png`

- [ ] **Step 3: Copy the four files into `auri-crm-site/public/screenshots/`**

- [ ] **Step 4: Write `src/components/ScreenshotCarousel.astro`**

```astro
---
interface Shot {
  src: string
  alt: string
  caption: string
}
interface Props {
  shots: Shot[]
}
const { shots } = Astro.props
---
<div class="flex gap-4 overflow-x-auto px-6 pb-4 snap-x">
  {shots.map((s) => (
    <figure class="shrink-0 snap-center w-full max-w-lg">
      <img src={s.src} alt={s.alt} class="rounded-xl border border-[var(--border)]" />
      <figcaption class="text-center text-sm text-[var(--muted)] mt-2">{s.caption}</figcaption>
    </figure>
  ))}
</div>
```

- [ ] **Step 5: Add the screenshots section to `src/pages/index.astro`**, between Funktionen and Selbst-hosten:

```astro
  <section id="screenshots" class="py-16">
    <h2 class="text-3xl font-bold text-center mb-10">Screenshots</h2>
    <ScreenshotCarousel shots={[
      { src: '/screenshots/admin-board.png', alt: 'Auri-Board mit Anfragen und Aufträgen', caption: 'Board' },
      { src: '/screenshots/portal.png', alt: 'Kundenportal mit Angebot, Vertrag und Galerie', caption: 'Kundenportal' },
      { src: '/screenshots/galerie-grid.png', alt: 'Gästegalerie als Rasteransicht', caption: 'Galerie' },
      { src: '/screenshots/galerie-detail.png', alt: 'Galerie-Detailansicht mit Favoriten und Kommentaren', caption: 'Detailansicht' },
    ]} />
  </section>
```

And add the import: `import ScreenshotCarousel from '../components/ScreenshotCarousel.astro'`

- [ ] **Step 6: Build and verify**

```bash
pnpm build
ls dist/screenshots/*.png | wc -l
```

Expected: `4`

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: real screenshots, carousel section"
```

---

### Task 11: Dockerfile and `auri-crm` touch points

**Files:**
- Create (in `auri-crm-site`): `Dockerfile`
- Modify (in `auri-crm`, this repo): `README.md`, `docs/architecture.md`

**Interfaces:** none — this is deploy config and documentation, no code interface.

- [ ] **Step 1: Write `auri-crm-site/Dockerfile`**

```dockerfile
# syntax=docker/dockerfile:1

FROM node:24-alpine AS build
WORKDIR /build
COPY package.json pnpm-lock.yaml ./
RUN corepack enable && pnpm install --frozen-lockfile
COPY . .
RUN pnpm build

FROM nginx:alpine
COPY --from=build /build/dist /usr/share/nginx/html
```

- [ ] **Step 2: Generate `pnpm-lock.yaml`** if it doesn't already exist from Task 1's `pnpm install`, and commit it (it wasn't committed yet — check `.gitignore` from Task 1 doesn't exclude it, it shouldn't).

- [ ] **Step 3: Build the image locally to verify**

```bash
cd ~/www/auri-crm-site
docker build -t auri-crm-site .
docker run --rm -p 8080:80 auri-crm-site &
curl -s http://localhost:8080 | grep -q 'Auri' && echo OK
docker stop $(docker ps -q --filter ancestor=auri-crm-site)
```

Expected: `OK`

- [ ] **Step 4: Commit in `auri-crm-site`**

```bash
git add -A
git commit -m "feat: production Dockerfile"
git push
```

- [ ] **Step 5: In `auri-crm` (this repo), add a one-line mention to `README.md`** — find the "four surfaces" section and add immediately after the table:

```markdown
A fifth, unauthenticated sibling app — the marketing site — lives in its
own repository, [czepter/auri-crm-site](https://github.com/czepter/auri-crm-site).
```

- [ ] **Step 6: Add one line to `docs/architecture.md`** under the "Surfaces" heading's opening paragraph:

```markdown
(A marketing site exists at [czepter/auri-crm-site](https://github.com/czepter/auri-crm-site) —
not a surface, since it has neither authentication nor a Laravel dependency.)
```

- [ ] **Step 7: Commit in `auri-crm`**

```bash
git add README.md docs/architecture.md
git commit -m "docs: mention the marketing site repo"
```

---

## Self-review notes

- **Spec coverage:** §4 repo layout → Task 1; §5 landing sections 1-8 → Tasks 2, 4, 5; §6 `/features`/`/docs`/`/about` → Tasks 6-8; §7 screenshot capture → Tasks 9-10; §8 deploy wiring → Task 11. The launch gate itself (repo-public flip, domain purchase) is explicitly not a task — it's an operational step outside this codebase, named in Global Constraints so it isn't forgotten.
- **Placeholder scan:** no TBD/TODO left except the single intentional `// TODO(domain)` marker required by the spec's own domain-placeholder decision — that one is load-bearing, not a gap.
- **Type consistency:** `Feature` (Task 3) is the one shape threaded through `FeatureCard` (Task 4), `/features` (Task 6) — `title`/`description` match everywhere. `SelfHostSteps`'s `compact` prop (Task 5) is consumed identically by `/docs` (Task 7).
