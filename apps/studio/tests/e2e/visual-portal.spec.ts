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

/*
 * The two projects E2eSeeder owns, and the reason there are two: a booking
 * and an inquiry are different screens, and every document screen hangs off
 * the first while the details form only exists on the second.
 *
 * By name, not by public id — that is generated per database. Named at all,
 * rather than discovered, because a fresh `migrate:fresh --seed=E2eSeeder`
 * holds nothing else, and baselines taken off a developer's demo cast are
 * baselines CI cannot reproduce.
 */
const BOOKING = 'E2E Buchung'
const INQUIRY = 'Meier & Kowalski \u2014 Hochzeit'

/** A client session: the project it was opened for, and whose it is. */
type Session = { url: string; contact: string }

const STATIC_ROUTES = ['/', '/nachrichten', '/einstellungen', '/impressum']

test.use({ viewport: { width: 1440, height: 900 }, locale: 'de-DE' })
test.setTimeout(10 * 60 * 1000)

async function settle(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle')
  await page.waitForFunction(() => document.fonts.status === 'loaded')
  // Gallery variants are encoded on first request (GalleryImageController), so
  // on a fresh database the first run's thumbnails are still being made when
  // the network goes idle. Four of six missing is what that looks like.
  await page.waitForFunction(() => [...document.images].every((image) => image.complete))
}

/* The gallery counts its own views, so one of its numbers is one higher on
   every run. Masked rather than reset: the count is the screen's, not the
   test's, and zeroing it would need a write the spec has no business making. */
const VOLATILE = { galerie: 'section dl' } as Record<string, string>

/* The lightbox is fixed to the viewport, so a full-page frame of it is the
   grid it covers with one screen's worth of overlay on top. */
const VIEWPORT_ONLY = new Set(['galerie-bild'])

async function shoot(page: Page, name: string): Promise<void> {
  await settle(page)
  const volatile = VOLATILE[name]
  await expect.soft(page).toHaveScreenshot(`${name}.png`, {
    fullPage: !VIEWPORT_ONLY.has(name),
    animations: 'disabled',
    ...(volatile ? { mask: [page.locator(volatile)] } : {}),
  })
}

/**
 * A client session that actually reaches one project's portal.
 *
 * Admin and portal bind the same public id, so the screen can be addressed
 * directly once a session exists. Which of the people a project page links is
 * the one holding that session is not something the page says — it also links
 * people who are only connected to it — so each is tried, and a 404 means it
 * was somebody else. Returns the portal URL of the project and the admin
 * path of the contact holding the session, or null.
 *
 * Read the href and navigate, rather than click: the row links on /projekte
 * report no bounding box, so an actionability check never settles.
 */
async function previewProject(page: Page, project: string): Promise<Session | null> {
  await page.goto(project)
  await settle(page)
  const contacts = await page
    .locator('a[href^="/kontakte/"]')
    .evaluateAll((nodes) => nodes.map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''))

  const url = `${PORTAL}/projekte/${project.split('/').pop()}`

  for (const contact of [...new Set(contacts)].sort()) {
    await page.goto(contact)
    await settle(page)
    const preview = page.getByRole('button', { name: 'Vorschau als Kunde' })
    if ((await preview.count()) === 0) {
      continue
    }

    await preview.click()
    await page.waitForURL(`${PORTAL}/**`)
    await page.goto(url)
    await settle(page)

    if ((await page.locator('header').count()) > 0) {
      return { url, contact: contact.split('/')[2] }
    }

    await page.goto(`${PORTAL}/vorschau-beenden`)
    await settle(page)
  }

  return null
}

/** The admin href of the project whose row carries this name. */
async function projectNamed(page: Page, name: string): Promise<string | undefined> {
  await page.goto('/projekte')
  await settle(page)
  const hrefs = await page.locator('a[href^="/auftraege/"]').evaluateAll(
    (nodes, wanted) =>
      nodes
        .filter((n) => (n.closest('tr, li') ?? n).textContent?.includes(wanted))
        .map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''),
    name,
  )
  return [...new Set(hrefs)].sort()[0]
}

/** Admin dev-login, then a client session for one named project. */
async function preview(page: Page, name: string): Promise<Session | null> {
  const project = await projectNamed(page, name)
  return project === undefined ? null : previewProject(page, project)
}

/** In-portal hrefs matching a prefix, deduplicated and ordered so names are stable. */
async function linksUnder(page: Page, prefix: string): Promise<string[]> {
  const hrefs = await page.locator(`a[href*="${prefix}"]`).evaluateAll((nodes) =>
    nodes.map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''),
  )
  return [...new Set(hrefs.filter((h) => h.includes(prefix)))].sort()
}

test('Portal haelt seine Baselines', async ({ page }) => {
  // Both screens a client reaches without a session, framed before one
  // exists: /anmelden is behind `guest:portal` and would redirect away.
  await page.goto(`${PORTAL}/anmelden`)
  await shoot(page, 'anmelden')

  await page.goto('/login')
  await page.getByRole('button', { name: 'Dev-Login' }).click()
  await expect(page).toHaveURL('/')

  const booking = await preview(page, BOOKING)

  if (booking === null) {
    console.log(`OHNE BASELINE: alle — ${BOOKING} nicht erreichbar`)
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

  await page.goto(booking.url)
  await shoot(page, 'projekt')

  // The three documents a booking hangs off. Each is a 404 when the studio
  // has not produced it — or, for payments, when T-08's switch is off — and
  // a frame of the error page would prove nothing about the screen.
  for (const [path, name] of [
    ['/angebot', 'angebot'],
    ['/vertrag', 'vertrag'],
    ['/zahlungen', 'zahlungen'],
  ] as const) {
    await page.goto(`${booking.url}${path}`)
    await settle(page)
    if ((await page.locator('header').count()) === 0) {
      console.log(`OHNE BASELINE: ${name} — nichts unter ${path}`)
      continue
    }
    await shoot(page, name)
  }

  await page.goto(booking.url)
  await settle(page)

  // A gallery only appears once the studio has released it to that client. A
  // database where none is released reaches it never, and releasing one from
  // here would rewrite a developer's own data. The gap is reported rather
  // than asserted away.
  const gallery = (await linksUnder(page, '/galerien/'))[0]
  if (gallery === undefined) {
    console.log('OHNE BASELINE: galerie — nichts unter /galerien/ freigegeben')
  } else {
    const url = gallery.startsWith('http') ? gallery : `${PORTAL}${gallery}`
    await page.goto(url)
    await shoot(page, 'galerie')

    // One picture, full-bleed. Not a route: the lightbox is a ref on the grid.
    await page.locator('#raster > span').first().click()
    await shoot(page, 'galerie-bild')

    // The download gate. Behind the view gate above, which this session has
    // already cleared — see routes/portal.php.
    await page.goto(`${url}/download`)
    await settle(page)
    if ((await page.locator('body > div').count()) === 0) {
      console.log('OHNE BASELINE: galerie-download — kein Downloadschirm')
    } else {
      await shoot(page, 'galerie-download')
    }
  }

  // A different contact, so it comes last: entering a preview replaces the
  // session every frame above was taken in.
  await page.goto(`${PORTAL}/vorschau-beenden`)
  await settle(page)

  // The unsubscribe screen carries its contact in the path and asks for no
  // session at all — see routes/portal.php.
  await page.goto(`${PORTAL}/abmelden/${booking.contact}`)
  await settle(page)
  if ((await page.locator('header').count()) === 0) {
    console.log('OHNE BASELINE: abmelden — keine Portalseite')
  } else {
    await shoot(page, 'abmelden')
  }

  const inquiry = await preview(page, INQUIRY)

  if (inquiry !== null) {
    await shoot(page, 'anfrage')

    // The details form is not a route: the read view holds it behind its own
    // button, and the fields are the whole of what P-03 added.
    await page.getByRole('button', { name: 'Ändern' }).click()
    await shoot(page, 'anfrage-bearbeiten')

    await page.goto(inquiry.url)
    await settle(page)
    const sheet = (await linksUnder(page, '/fragebogen/'))[0]
    if (sheet === undefined) {
      console.log('OHNE BASELINE: fragebogen — keiner geteilt')
    } else {
      await page.goto(sheet.startsWith('http') ? sheet : `${PORTAL}${sheet}`)
      await shoot(page, 'fragebogen')
    }

    // The mailbox with nothing in it. /nachrichten above is the same screen
    // on the contact E2eSeeder gave a conversation to, and the two share no
    // element: one is a list beside a conversation, the other a compose box.
    await page.goto(`${PORTAL}/nachrichten`)
    await settle(page)
    await shoot(page, 'nachrichten-leer')
  } else {
    console.log(`OHNE BASELINE: anfrage — ${INQUIRY} nicht erreichbar`)
  }
})
