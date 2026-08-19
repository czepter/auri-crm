import { expect, test, type Page } from '@playwright/test'
import { signIn } from './helpers'

/*
 * Pixel baselines for the Tailwind-only migration
 * (docs/superpowers/specs/2026-08-19-tailwind-only-migration-design.md).
 *
 * One test, not one per route: signing in costs an MFA round trip, and the
 * suite is a ruler rather than a feature test. `expect.soft` is what keeps a
 * single drifted screen from hiding the other twenty-four.
 *
 * The viewport matches the mockups the sheets were ported from; at 1280 the
 * four-column grid in crm.css collapses and the baselines would measure the
 * narrow layout instead.
 */

/*
 * Dev-Login where it exists, the MFA round trip otherwise. Locally the suite
 * runs against a developer's own database — E2eSeeder would write two accounts
 * and a cast of fixtures into it for no gain, since a baseline only ever
 * compares a screen against itself.
 */
async function arrive(page: Page): Promise<void> {
  await page.goto('/login')
  const dev = page.getByRole('button', { name: 'Dev-Login' })
  // waitFor, not isVisible: the button is rendered by Inertia after hydration,
  // and isVisible() answers from the DOM as it stands rather than waiting.
  const present = await dev
    .waitFor({ state: 'visible', timeout: 5000 })
    .then(() => true)
    .catch(() => false)
  if (present) {
    await dev.click()
    await expect(page).toHaveURL('/')
    return
  }
  await signIn(page)
}

const ROUTES = [
  '/',
  '/anfragen',
  // Both views of the board: ?ansicht= drives it, the POST that persists a
  // preference only fires on a click.
  '/anfragen?ansicht=board',
  '/anfragen/neu',
  '/auswertungen',
  '/dokumentvorlagen',
  '/dokumentvorlagen/neu',
  '/einstellungen',
  // The settings shell keeps its pane in the query, so each one is its own
  // frame. `capture` is left out on purpose — it is uncommitted work.
  '/einstellungen?pane=vat',
  '/einstellungen?pane=documents',
  '/einstellungen?pane=email',
  '/einstellungen?pane=general',
  '/auth/mfa/enroll',
  '/auth/mfa/recovery-codes',
  '/einstellungen/aufgabenvorlagen',
  '/einstellungen/benachrichtigungen',
  '/einstellungen/fragebogen-vorlagen',
  '/einstellungen/fragebogen-vorlagen/neu',
  '/einstellungen/kalender',
  '/einstellungen/nummernkreise',
  '/einstellungen/portal',
  '/einstellungen/verfuegbarkeit',
  '/einstellungen/zahlungen',
  '/kalender',
  '/kontakte',
  '/pakete',
  '/pakete/neu',
  '/posteingang',
  '/projekte',
  '/team',
  '/abwesenheiten',
  '/verfuegbarkeit',
]

test.use({ viewport: { width: 1440, height: 900 }, locale: 'de-DE' })

// The config's 30s is sized for a spec that exercises one flow. This one walks
// every route in a single session, so it needs the sum, not the slice.
test.setTimeout(10 * 60 * 1000)

test('Admin-Oberflaeche haelt ihre Baselines', async ({ page }) => {
  // Before signing in: the two auth screens reachable without a session. They
  // share a shell with six more that need a token or a challenge to reach.
  for (const [route, name] of [
    ['/login', 'login'],
    ['/passwort/vergessen', 'passwort-vergessen'],
  ] as const) {
    await page.goto(route)
    await page.waitForLoadState('networkidle')
    await page.waitForFunction(() => document.fonts.status === 'loaded')
    await expect.soft(page).toHaveScreenshot(`${name}.png`, {
      fullPage: true,
      animations: 'disabled',
    })
  }

  await arrive(page)

  for (const route of ROUTES) {
    await page.goto(route)
    await page.waitForLoadState('networkidle')
    // The studio name is set in Instrument Serif. networkidle can land between
    // the fallback paint and the swap, which is a diff in the sidebar of every
    // screen unlucky enough to be caught there.
    await page.waitForFunction(() => document.fonts.status === 'loaded')
    const name = route === '/' ? 'uebersicht' : route.slice(1).replace(/[/?=]/g, '--')
    await expect.soft(page).toHaveScreenshot(`${name}.png`, {
      fullPage: true,
      animations: 'disabled',
    })
  }

  // Detail screens carry the heaviest markup in the admin — Projects/Show
  // alone holds 116 of the semantic classes. Their ids come from the lists
  // rather than from a fixture, and the href is read rather than clicked: the
  // row links report no bounding box, so an actionability check never settles.
  for (const [list, prefix, name] of [
    ['/projekte', '/auftraege/', 'auftrag'],
    ['/anfragen', '/anfragen/', 'anfrage'],
    ['/kontakte', '/kontakte/', 'kontakt'],
    ['/dokumentvorlagen', '/dokumentvorlagen/', 'dokumentvorlage'],
    ['/einstellungen/fragebogen-vorlagen', '/einstellungen/fragebogen-vorlagen/', 'fragebogen'],
    // The admin has no gallery index — the dashboard is the only screen
    // that links one (the record is #254, the nav entry is not built).
    ['/', '/galerien/', 'galerie-verwaltung'],
  ] as const) {
    await page.goto(list)
    await page.waitForLoadState('networkidle')
    // Sorted, not first: the lists order by date or by name, and both move
    // as the data does. The lowest id is the same row on every run.
    const hrefs = await page
      .locator(`a[href^="${prefix}"]:not([href$="/neu"])`)
      .evaluateAll((nodes) => nodes.map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''))
    const href = [...new Set(hrefs)].sort()[0]
    if (!href || href === list) {
      console.log(`OHNE BASELINE: ${name} — nichts unter ${prefix} verlinkt`)
      continue
    }
    await page.goto(href)
    await page.waitForLoadState('networkidle')
    await page.waitForFunction(() => document.fonts.status === 'loaded')
    await expect.soft(page).toHaveScreenshot(`${name}.png`, {
      fullPage: true,
      animations: 'disabled',
    })

    // A project carries five more screens under its own id.
    if (name === 'auftrag') {
      for (const sub of ['angebote', 'frageboegen', 'nachrichten', 'shootings', 'vertrag']) {
        await page.goto(`${href}/${sub}`)
        await page.waitForLoadState('networkidle')
        await page.waitForFunction(() => document.fonts.status === 'loaded')
        await expect.soft(page).toHaveScreenshot(`auftrag-${sub}.png`, {
          fullPage: true,
          animations: 'disabled',
        })
      }
    }
  }

  // The gallery record has no list of its own — the dashboard links the media
  // screen under it, so the record's own route is derived from that id.
  await page.goto('/')
  await page.waitForLoadState('networkidle')
  const media = await page.locator('a[href^="/galerien/"]').first().getAttribute('href')
  if (media) {
    await page.goto(media.split('/').slice(0, 3).join('/'))
    await page.waitForLoadState('networkidle')
    await page.waitForFunction(() => document.fonts.status === 'loaded')
    await expect.soft(page).toHaveScreenshot('galerie-datensatz.png', {
      fullPage: true,
      animations: 'disabled',
    })

    // Four more tabs behind the first, all client state.
    for (const tab of ['Gestaltung', 'Download', 'Freigaben', 'Aktivität']) {
      await page.getByRole('button', { name: tab, exact: true }).click()
      await page.waitForFunction(() => document.fonts.status === 'loaded')
      await expect.soft(page).toHaveScreenshot(`galerie-${tab.toLowerCase()}.png`, {
        fullPage: true,
        animations: 'disabled',
      })
    }
  } else {
    console.log('OHNE BASELINE: galerie-datensatz — keine Galerie verlinkt')
  }

  // The contact typeahead shows a field and a hint until something is typed;
  // its result rows, avatars and the create-new row are behind that.
  await page.goto('/anfragen/neu')
  await page.waitForLoadState('networkidle')
  // Two characters at least, then a 250ms debounce and a fetch.
  await page.getByPlaceholder('Suchen').fill('an')
  await expect(page.locator('form')).toBeVisible()
  await page.waitForTimeout(1200)
  await page.waitForFunction(() => document.fonts.status === 'loaded')
  await expect.soft(page).toHaveScreenshot('anfrage-kontaktsuche.png', {
    fullPage: true,
    animations: 'disabled',
  })

  // And the chosen state: the picked card replaces the field entirely.
  await page.locator('form button').filter({ hasText: 'Annika Reuter' }).first().click()
  await page.waitForFunction(() => document.fonts.status === 'loaded')
  await expect.soft(page).toHaveScreenshot('anfrage-kontakt-gewaehlt.png', {
    fullPage: true,
    animations: 'disabled',
  })

  // The item editor renders nothing until a row exists, so the card, its head
  // and the field grid inside it are never on the /pakete/neu frame.
  await page.goto('/pakete/neu')
  await page.waitForLoadState('networkidle')
  await page.getByRole('button', { name: 'Leistung hinzufügen' }).click()
  await page.waitForFunction(() => document.fonts.status === 'loaded')
  await expect.soft(page).toHaveScreenshot('paket-position.png', {
    fullPage: true,
    animations: 'disabled',
  })

  // The template editor opens only after a starting point is chosen — the
  // screen's first state is the choice, and the split view behind it would
  // otherwise never be measured.
  await page.goto('/dokumentvorlagen/neu')
  await page.waitForLoadState('networkidle')
  await page.getByRole('link', { name: /Leere Vorlage/ }).click()
  await page.waitForFunction(() => document.fonts.status === 'loaded')
  await expect.soft(page).toHaveScreenshot('dokumentvorlage-editor.png', {
    fullPage: true,
    animations: 'disabled',
  })

  // The calendar's list view is client state, not a route: it can only be
  // reached by pressing the toggle.
  await page.goto('/kalender')
  await page.waitForLoadState('networkidle')
  await page.getByRole('button', { name: 'Liste', exact: true }).click()
  await page.waitForFunction(() => document.fonts.status === 'loaded')
  await expect.soft(page).toHaveScreenshot('kalender-liste.png', {
    fullPage: true,
    animations: 'disabled',
  })

  // Dialogs are closed on every screen above. The invite form is the one that
  // still carries the shared .fgrid/.fld shell, so it gets a frame of its own.
  await page.goto('/team')
  await page.waitForLoadState('networkidle')
  await page.getByRole('button', { name: 'Einladen', exact: true }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.waitForFunction(() => document.fonts.status === 'loaded')
  await expect.soft(page).toHaveScreenshot('team-einladen.png', { animations: 'disabled' })
  await page.keyboard.press('Escape')

  // The palette is closed on every screen above, so it needs its own frame.
  // Clipped to everything left of the time column: the rows are this session's
  // own recently-viewed list, so the walk above keeps rewriting the timestamps
  // it shows. A mask does not help — the column is content-width, and "vor 8
  // Minuten" is narrower than "vor 12 Minuten".
  await page.goto('/')
  await page.waitForLoadState('networkidle')
  await page.waitForFunction(() => document.fonts.status === 'loaded')
  await page.getByRole('button', { name: 'Suchen' }).click()
  await expect(page.locator('dialog[open]')).toBeVisible()
  // The result list is this session's own recently-viewed items, so the walk
  // above rewrites it. Masked rather than clipped away: the container's
  // geometry is fixed, so the mask box does not move with its contents.
  await expect.soft(page).toHaveScreenshot('palette.png', {
    animations: 'disabled',
    clip: { x: 440, y: 100, width: 560, height: 420 },
    mask: [page.locator('dialog[open] > div')],
  })
})
