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

/**
 * Admin dev-login, then a client session for the first contact whose portal
 * actually has something on it.
 *
 * Not simply "the first contact": a booking only appears in the portal once
 * the studio has made it visible, and the contact at the top of the list is
 * as likely as not to be attached to one that never was. A frame of the empty
 * home screen would then stand in for every project screen behind it.
 */
async function enterPreview(page: Page): Promise<boolean> {
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
  const projects = await page
    .locator('a[href^="/auftraege/"]')
    .evaluateAll((nodes) => nodes.map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''))

  for (const project of [...new Set(projects)].sort()) {
    await page.goto(project)
    await settle(page)
    // Every contact the screen links, not just the first: a project page also
    // links people who are only connected to it.
    const contacts = await page
      .locator('a[href^="/kontakte/"]')
      .evaluateAll((nodes) => nodes.map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''))

    for (const contact of [...new Set(contacts)].sort()) {
      await page.goto(contact)
      await settle(page)
      const preview = page.getByRole('button', { name: 'Vorschau als Kunde' })
      if ((await preview.count()) === 0) {
        continue
      }

      await preview.click()
      await page.waitForURL(`${PORTAL}/**`)
      await settle(page)

      if ((await page.locator('a[href^="/projekte/"]').count()) > 0) {
        return true
      }

      await page.goto(`${PORTAL}/vorschau-beenden`)
      await settle(page)
      await page.goto(project)
      await settle(page)
    }
  }

  return false
}

/** In-portal hrefs matching a prefix, deduplicated and ordered so names are stable. */
async function linksUnder(page: Page, prefix: string): Promise<string[]> {
  const hrefs = await page.locator(`a[href*="${prefix}"]`).evaluateAll((nodes) =>
    nodes.map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''),
  )
  return [...new Set(hrefs.filter((h) => h.includes(prefix)))].sort()
}

/**
 * The one project E2eSeeder owns, reached by name.
 *
 * Not by public id: that is generated per database. Not through the loop
 * above either — it stops at the first portal that has a project on it, and
 * the inquiry screen is a second project on the same surface, one with
 * nothing reserved on it yet (see Project::clientMayEditDetails).
 */
const FIXTURE_PROJECT = 'Meier & Kowalski \u2014 Hochzeit'

async function previewFixture(page: Page): Promise<boolean> {
  await page.goto('/projekte')
  await settle(page)

  const href = (
    await page
      .locator('a[href^="/auftraege/"]')
      .evaluateAll(
        (nodes, name) =>
          nodes
            .filter((n) => (n.closest('tr, li') ?? n).textContent?.includes(name))
            .map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''),
        FIXTURE_PROJECT,
      )
  )[0]

  if (href === undefined) {
    return false
  }

  await page.goto(href)
  await settle(page)
  const contacts = await page
    .locator('a[href^="/kontakte/"]')
    .evaluateAll((nodes) => nodes.map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''))

  // Admin and portal bind the same public id, so the screen can be addressed
  // directly once a session exists. Which of the people the project page
  // links is the one holding that session is not something the page says —
  // so each is tried, and a 404 means it was somebody else.
  const url = `${PORTAL}/projekte/${href.split('/').pop()}`

  for (const contact of [...new Set(contacts)]) {
    await page.goto(contact)
    await settle(page)
    await page.getByRole('button', { name: 'Vorschau als Kunde' }).click()
    await page.waitForURL(`${PORTAL}/**`)
    await page.goto(url)
    await settle(page)

    if ((await page.locator('header').count()) > 0) {
      return true
    }

    await page.goto(`${PORTAL}/vorschau-beenden`)
    await settle(page)
  }

  return false
}

test('Portal haelt seine Baselines', async ({ page }) => {
  if (!(await enterPreview(page))) {
    console.log('OHNE BASELINE: alle — kein Kontakt mit einem freigegebenen Projekt')
    return
  }

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

  // A different contact, so it comes last: entering a preview replaces the
  // session every frame above was taken in.
  await page.goto(`${PORTAL}/vorschau-beenden`)
  await settle(page)

  if (await previewFixture(page)) {
    await shoot(page, 'anfrage')

    // The details form is not a route: the read view holds it behind its own
    // button, and the fields are the whole of what P-03 added.
    const url = page.url()
    await page.getByRole('button', { name: 'Ändern' }).click()
    await shoot(page, 'anfrage-bearbeiten')

    await page.goto(url)
    await settle(page)
    const sheet = (await linksUnder(page, '/fragebogen/'))[0]
    if (sheet === undefined) {
      console.log('OHNE BASELINE: fragebogen — keiner geteilt')
    } else {
      await page.goto(sheet.startsWith('http') ? sheet : `${PORTAL}${sheet}`)
      await shoot(page, 'fragebogen')
    }
  } else {
    console.log(`OHNE BASELINE: anfrage — ${FIXTURE_PROJECT} nicht erreichbar`)
  }
})
