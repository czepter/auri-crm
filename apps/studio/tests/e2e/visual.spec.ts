import { expect, test } from '@playwright/test'
import { ACCOUNTS, arrive, submitLogin } from './helpers'

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

/** E2eSeeder's own project, the one with a quote still in draft on it. */
const DRAFT_QUOTE_PROJECT = 'E2E Buchung'

const ROUTES = [
  '/',
  '/anfragen',
  // Both views of the board: ?ansicht= drives it, the POST that persists a
  // preference only fires on a click.
  '/anfragen?ansicht=board',
  '/anfragen/neu',
  '/auswertungen',
  '/vorlagen',
  '/vorlagen?typ=aufgaben',
  '/vorlagen?typ=fragebogen',
  '/vorlagen?typ=dokumente',
  '/vorlagen?typ=galerien',
  '/dokumentvorlagen/neu',
  '/einstellungen',
  // The settings shell keeps its pane in the query, so each one is its own
  // frame. `capture` is left out on purpose — it is uncommitted work.
  '/einstellungen?pane=legal',
  '/einstellungen?pane=bank',
  '/einstellungen?pane=vat',
  '/einstellungen?pane=documents',
  '/einstellungen?pane=email',
  '/einstellungen?pane=general',
  // Reachable whatever onboarding says: the gate sits on the routes after it,
  // not on the wizard itself. /einrichtung, the first-run screen, needs a
  // studio with no staff account at all and has no frame.
  '/einrichtung/studio',
  '/auth/mfa/enroll',
  '/auth/mfa/recovery-codes',
  '/einstellungen/benachrichtigungen',
  '/einstellungen/fragebogen-vorlagen/neu',
  '/einstellungen/kalender',
  '/einstellungen/nummernkreise',
  '/einstellungen/portal',
  '/einstellungen/verfuegbarkeit',
  '/einstellungen/zahlungen',
  '/benachrichtigungen',
  // The person's own account, as opposed to the studio's settings above.
  '/profil',
  '/profil?pane=sicherheit',
  '/profil?pane=benachrichtigungen',
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

/*
 * Columns that carry a relative time ("vor 6 Tagen", "gestern"). They cross a
 * boundary on their own, so the frame masks them; the cell's width comes from
 * the column, not from its text, so the mask box stays put.
 */
const RELATIVE_TIME_CELLS: Record<string, string> = {
  '/anfragen': 'tbody td:last-child',
}

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
    // The form behind a reset link. Any token renders it — the link is only
    // checked when the new password is posted — so none has to be minted.
    ['/passwort/neu/e2e?email=e2e-basic%40studio.test', 'passwort-neu'],
    // This one does have to be real: an unknown token redirects away. The
    // token is E2eSeeder::INVITATION_TOKEN.
    ['/einladung/e2e-einladung', 'einladung'],
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
    const volatile = RELATIVE_TIME_CELLS[route]
    await expect.soft(page).toHaveScreenshot(`${name}.png`, {
      fullPage: true,
      animations: 'disabled',
      ...(volatile ? { mask: [page.locator(volatile)] } : {}),
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
    ['/vorlagen?typ=dokumente', '/dokumentvorlagen/', 'dokumentvorlage'],
    ['/pakete', '/pakete/', 'paket'],
    ['/vorlagen?typ=fragebogen', '/einstellungen/fragebogen-vorlagen/', 'fragebogen'],
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
    // The project screen ends in a timeline that the portal suite writes to:
    // every "Vorschau als Kunde" leaves an entry, so the page is one row
    // taller than it was. The viewport frame stops above it.
    await expect.soft(page).toHaveScreenshot(`${name}.png`, {
      fullPage: name !== 'auftrag',
      animations: 'disabled',
    })

    // A project carries five more screens under its own id. The quote editor
    // only renders where a quote exists, so that one screen is taken from the
    // lowest project that has one rather than from this row.
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

  // N-04's consent screen. Not in ROUTES because it is only a screen with the
  // whole authorization request on the query string; without one it is a 400.
  await page.goto(
    '/oauth/authorize?client_id=studio-mcp&redirect_uri=http%3A%2F%2F127.0.0.1%3A33418%2Fcallback' +
      '&response_type=code&code_challenge_method=S256&code_challenge=e2e&scope=manage_leads+manage_settings',
  )
  await page.waitForLoadState('networkidle')
  if ((await page.locator('[data-slot="button"]').count()) === 0) {
    console.log('OHNE BASELINE: oauth-freigabe — kein Einwilligungsschirm')
  } else {
    await page.waitForFunction(() => document.fonts.status === 'loaded')
    await expect.soft(page).toHaveScreenshot('oauth-freigabe.png', {
      fullPage: true,
      animations: 'disabled',
    })
  }

  // The quote editor, which only renders for a draft. E2eSeeder puts one on a
  // project it owns, so this looks that project up by name rather than walking
  // rows until one happens to have a draft on it.
  await page.goto('/projekte')
  await page.waitForLoadState('networkidle')
  const quoted = (
    await page.locator('a[href^="/auftraege/"]').evaluateAll(
      (nodes, name) =>
        nodes
          .filter((n) => (n.closest('tr, li') ?? n).textContent?.includes(name))
          .map((n) => (n as HTMLAnchorElement).getAttribute('href') ?? ''),
      DRAFT_QUOTE_PROJECT,
    )
  )[0]

  if (quoted !== undefined) {
    await page.goto(`${quoted}/angebote`)
    await page.waitForLoadState('networkidle')
  }

  if (quoted !== undefined && (await page.locator('[data-slot="button"]').filter({ hasText: 'Position' }).count()) > 0) {
    await page.waitForFunction(() => document.fonts.status === 'loaded')
    await expect.soft(page).toHaveScreenshot('auftrag-angebot-editor.png', {
      fullPage: true,
      animations: 'disabled',
    })
  } else {
    console.log('OHNE BASELINE: auftrag-angebot-editor — kein Angebot im Entwurf')
  }

  // Whatever gallery the dashboard links first — .../galerien/{id}, its
  // record route. slice(0, 3) drops anything after the id in case the link
  // ever grows a sub-path again.
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

    // Three more tabs behind the first (Bilder, already shot above) — the
    // rail's own record redesign folded Download and Freigaben into
    // Einstellungen, and upload now lives in Bilder rather than its own
    // /medien route.
    // Links, not buttons: which area is open is a fact of the path now, so
    // each one is a real navigation and the shot has to wait for it.
    for (const tab of ['Gestaltung', 'Einstellungen', 'Aktivität']) {
      // In der Datensatzspalte gesucht, nicht auf der ganzen Seite: die
      // Seitenleiste trägt ebenfalls einen Eintrag „Einstellungen". Und mit
      // Präfix statt exakt, weil der zugängliche Name den Zähler enthält —
      // „Aktivität 41" ist nicht „Aktivität".
      await page
        .locator('nav[style*="--record-nav-width"]')
        .getByRole('link', { name: new RegExp(`^${tab}`) })
        .click()
      await page.waitForLoadState('networkidle')
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
  await page.locator('[data-slot="choice-group"]').getByText('Liste', { exact: true }).click()
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

  // The challenge, last: reaching it means signing out of the session every
  // frame above was taken in. E2eSeeder's own enrolled account, so the screen
  // exists whether or not the developer's own user has MFA on.
  await page.context().clearCookies()
  await submitLogin(page, ACCOUNTS.mfa)
  await page.waitForLoadState('networkidle')
  if (page.url().endsWith('/auth/mfa')) {
    await page.waitForFunction(() => document.fonts.status === 'loaded')
    await expect.soft(page).toHaveScreenshot('auth--mfa.png', {
      fullPage: true,
      animations: 'disabled',
    })
  } else {
    console.log('OHNE BASELINE: auth--mfa — der Testzugang wurde nicht abgefragt')
  }
})
