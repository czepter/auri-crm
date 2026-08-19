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
  '/anfragen/neu',
  '/auswertungen',
  '/dokumentvorlagen',
  '/dokumentvorlagen/neu',
  '/einstellungen',
  '/einstellungen/aufgabenvorlagen',
  '/einstellungen/benachrichtigungen',
  '/einstellungen/fragebogen-vorlagen',
  '/einstellungen/fragebogen-vorlagen/neu',
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
  await arrive(page)

  for (const route of ROUTES) {
    await page.goto(route)
    await page.waitForLoadState('networkidle')
    // The studio name is set in Instrument Serif. networkidle can land between
    // the fallback paint and the swap, which is a diff in the sidebar of every
    // screen unlucky enough to be caught there.
    await page.waitForFunction(() => document.fonts.status === 'loaded')
    const name = route === '/' ? 'uebersicht' : route.slice(1).replace(/\//g, '--')
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
  ] as const) {
    await page.goto(list)
    await page.waitForLoadState('networkidle')
    const href = await page.locator(`a[href^="${prefix}"]`).first().getAttribute('href')
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
  }

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
  await expect.soft(page).toHaveScreenshot('palette.png', {
    animations: 'disabled',
    clip: { x: 440, y: 100, width: 440, height: 420 },
  })
})
