import { expect, test, type Page } from '@playwright/test'

/*
 * Pixel baselines for the portal surface
 * (docs/superpowers/specs/2026-08-19-tailwind-only-migration-design.md, §3).
 *
 * The portal runs as its own service on another port, and a client session is
 * normally minted by a magic link that arrives by mail. The admin's own
 * "Vorschau als Kunde" issues the same ClientAccess token without the detour
 * through Mailpit, which is why this spec starts on the admin surface.
 *
 * Project and gallery screens are reached by following the links the seeded
 * data actually produces. Hardcoding a public id here would tie the baselines
 * to one database; the routes are parameterised and there is no fixture set
 * that owns them.
 */

// Must match compose's PORTAL_URL exactly, not just resolve to the same host:
// the preview redirect carries that literal origin, and 127.0.0.1 would neither
// match the wait nor share a session cookie with it.
const PORTAL = process.env.PORTAL_URL ?? `http://localhost:${process.env.PORTAL_PORT ?? '8001'}`

const STATIC_ROUTES = ['/', '/nachrichten', '/einstellungen', '/impressum']

test.use({ viewport: { width: 1440, height: 900 }, locale: 'de-DE' })
test.setTimeout(10 * 60 * 1000)

async function settle(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle')
  await page.waitForFunction(() => document.fonts.status === 'loaded')
}

async function shoot(page: Page, name: string): Promise<void> {
  await settle(page)
  await expect.soft(page).toHaveScreenshot(`${name}.png`, {
    fullPage: true,
    animations: 'disabled',
  })
}

/** Admin dev-login, then hand the browser a client session for the first contact. */
async function enterPreview(page: Page): Promise<void> {
  await page.goto('/login')
  await page.getByRole('button', { name: 'Dev-Login' }).click()
  await expect(page).toHaveURL('/')

  // By way of a project, not straight into /kontakte: the contact list is
  // ordered by name and its first entry may well be an inquiry that never
  // became a booking, whose portal home then has no project to open.
  // Read the href and navigate, rather than click: the row links on /projekte
  // report no bounding box, so an actionability check never settles.
  // Sorted, not first: both lists order by something that moves with the data,
  // so "the first row" is a different record from one week to the next.
  const lowest = async (prefix: string) => {
    const hrefs = await page
      .locator(`a[href^="${prefix}"]:not([href$="/neu"])`)
      .evaluateAll((nodes) => nodes.map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''))
    return [...new Set(hrefs)].sort()[0]
  }

  await page.goto('/projekte')
  await settle(page)
  await page.goto(await lowest('/auftraege/'))
  await settle(page)

  await page.goto(await lowest('/kontakte/'))
  await settle(page)

  await page.getByRole('button', { name: 'Vorschau als Kunde' }).click()
  await page.waitForURL(`${PORTAL}/**`)
}

/** In-portal hrefs matching a prefix, deduplicated and ordered so names are stable. */
async function linksUnder(page: Page, prefix: string): Promise<string[]> {
  const hrefs = await page.locator(`a[href*="${prefix}"]`).evaluateAll((nodes) =>
    nodes.map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''),
  )
  return [...new Set(hrefs.filter((h) => h.includes(prefix)))].sort()
}

test('Portal haelt seine Baselines', async ({ page }) => {
  await enterPreview(page)

  for (const route of STATIC_ROUTES) {
    await page.goto(`${PORTAL}${route}`)
    await settle(page)
    // A screen the studio has switched off answers 404, and a frame of the
    // error page proves nothing about the screen it stands in for. Messaging
    // is one such switch.
    if ((await page.locator('header').count()) === 0) {
      console.log(`OHNE BASELINE: ${route} — keine Portalseite, vermutlich abgeschaltet`)
      continue
    }
    await shoot(page, route === '/' ? 'home' : route.slice(1).replace(/\//g, '--'))
  }

  // Project and gallery screens only appear once the studio has released them
  // to that client. A database where nothing is released reaches neither, and
  // releasing one from here would rewrite a developer's own data. The gap is
  // reported rather than asserted away.
  await page.goto(`${PORTAL}/`)
  await settle(page)

  for (const [prefix, name] of [
    ['/projekte/', 'projekt'],
    ['/galerien/', 'galerie'],
  ] as const) {
    const found = await linksUnder(page, prefix)
    if (found.length === 0) {
      console.log(`OHNE BASELINE: ${name} — nichts unter ${prefix} freigegeben`)
      continue
    }
    await page.goto(found[0].startsWith('http') ? found[0] : `${PORTAL}${found[0]}`)
    await shoot(page, name)
    await page.goto(`${PORTAL}/`)
    await settle(page)
  }
})
