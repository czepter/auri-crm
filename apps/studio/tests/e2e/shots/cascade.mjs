/*
 * Reads the *effective* style of every element that still carries a class from
 * one of the hand-written sheets, on one admin route.
 *
 * Written for the Tailwind-only migration
 * (docs/superpowers/specs/2026-08-19-tailwind-only-migration-design.md).
 * Reading crm.css is not enough to port a screen: 298 class names carry more
 * than one rule, and the ones that decide a screen are often in tokens.css
 * rather than in the sheet the screen appears to belong to. Contacts/Edit was
 * 9886 pixels off because `.val` takes font:600 13px/1 from tokens.css:170.
 *
 *   node tests/e2e/shots/cascade.mjs /kontakte/abc123
 */
import { chromium } from '@playwright/test'

const ROUTE = process.argv[2]
if (!ROUTE) throw new Error('usage: node cascade.mjs <route> [--anon] [--portal] [--as <route>] [--click <name>] [--fill <placeholder> <value>]')

/* Routes that exist for someone without a session — the sign-in screens — have
   to be read without one, or the dev login lands on the dashboard instead. */
const ANON = process.argv.includes('--anon')

/* Some states are not routes: a list view held in a ref, a dialog, a menu.
   --click <name> presses one button by its accessible name first. */
const CLICK = (() => {
  const i = process.argv.indexOf('--click')
  return i === -1 ? null : process.argv[i + 1]
})()

/* --as <route>: the portal route that decides which preview to keep. Contact-
   scoped screens — the mailbox, the account — render for every client, so
   "the first session that reaches /nachrichten" is whichever contact came
   first, not the one with a conversation on it. Naming a project route here
   picks the session by what it can see. */
const AS = (() => {
  const i = process.argv.indexOf('--as')
  return i === -1 ? null : process.argv[i + 1]
})()

/* --fill <placeholder> <value>: states that only exist once something is
   typed — a typeahead's result list, a filtered table. */
const FILL = (() => {
  const i = process.argv.indexOf('--fill')
  return i === -1 ? null : { where: process.argv[i + 1], what: process.argv[i + 2] }
})()

const ADMIN = process.env.ADMIN_URL ?? 'http://127.0.0.1:8000'

/* The properties a port has to reproduce. The full computed style is 340
   entries, of which 300 are inherited defaults nobody writes. */
const PROPS = [
  'display', 'position', 'flexDirection', 'flexGrow', 'flexShrink', 'flexBasis',
  'alignItems', 'alignSelf', 'justifyContent', 'gap', 'gridTemplateColumns',
  'width', 'minWidth', 'maxWidth', 'height', 'minHeight',
  'margin', 'padding', 'inset', 'zIndex', 'overflow',
  'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing',
  'fontVariantNumeric', 'textTransform', 'textAlign', 'textDecorationLine',
  'whiteSpace', 'color', 'backgroundColor', 'border', 'borderRadius',
  'boxShadow', 'opacity', 'transform', 'transition', 'cursor',
]

const browser = await chromium.launch()
const page = await (
  await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'de-DE' })
).newPage()

/* The portal is a second service on a second port, and a client session is
   minted by the admin's own "Vorschau als Kunde" — the same detour
   visual-portal.spec.ts takes, for the same reason. */
const PORTAL = process.env.PORTAL_URL ?? `http://localhost:${process.env.PORTAL_PORT ?? '8001'}`
const SURFACE = process.argv.includes('--portal') ? PORTAL : ADMIN

if (!ANON) {
  await page.goto(`${ADMIN}/login`)
  await page.getByRole('button', { name: 'Dev-Login' }).click()
  await page.waitForURL(`${ADMIN}/`)
}
// A portal route with no session of its own — the sign-in screen, the
// unsubscribe link — is reached directly; there is nothing to preview as.
if (SURFACE === PORTAL && !ANON) {
  await page.goto(`${ADMIN}/projekte`)
  await page.waitForLoadState('networkidle')
  const projects = await page
    .locator('a[href^="/auftraege/"]')
    .evaluateAll((nodes) => nodes.map((n) => n.getAttribute('href') ?? ''))

  // Which contact holds a session that reaches the requested route is not
  // something any page says: a project links people who are only connected to
  // it, and a portal route belonging to somebody else answers 404. So every
  // preview is tried until the route itself renders.
  let reached = false
  for (const project of [...new Set(projects)].sort()) {
    await page.goto(ADMIN + project)
    await page.waitForLoadState('networkidle')
    const contacts = await page
      .locator('a[href^="/kontakte/"]')
      .evaluateAll((nodes) => nodes.map((n) => n.getAttribute('href') ?? ''))

    for (const contact of [...new Set(contacts)].sort()) {
      await page.goto(ADMIN + contact)
      await page.waitForLoadState('networkidle')
      const preview = page.getByRole('button', { name: 'Vorschau als Kunde' })
      if ((await preview.count()) === 0) continue

      await preview.click()
      await page.waitForURL(`${PORTAL}/**`)
      await page.goto(PORTAL + (AS ?? ROUTE))
      await page.waitForLoadState('networkidle')
      if ((await page.locator('header').count()) > 0) {
        if (AS) {
          await page.goto(PORTAL + ROUTE)
          await page.waitForLoadState('networkidle')
        }
        reached = true
        break
      }
      await page.goto(`${PORTAL}/vorschau-beenden`)
      await page.waitForLoadState('networkidle')
    }
    if (reached) break
  }
  if (!reached) throw new Error(`kein Kontakt erreicht ${ROUTE}`)
} else {
  await page.goto(SURFACE + ROUTE)
  await page.waitForLoadState('networkidle')
}

if (FILL) {
  await page.getByPlaceholder(FILL.where).fill(FILL.what)
  await page.waitForTimeout(1200)
}

if (CLICK) {
  // Button or link: the states worth measuring hide behind both.
  const target = page.getByRole('button', { name: CLICK })
  const hit = (await target.count()) > 0 ? target : page.getByRole('link', { name: CLICK })
  await hit.first().click()
  await page.waitForLoadState('networkidle')
}

const rows = await page.evaluate((PROPS) => {
  /* A class is "legacy" when a hand-written sheet declares it. Tailwind's own
     utilities are in the same attribute and have to be told apart; the marker
     is that Tailwind's live in a layer. */
  const legacy = new Set()
  for (const sheet of document.styleSheets) {
    let rules
    try {
      rules = sheet.cssRules
    } catch {
      continue
    }
    const walk = (list, layered) => {
      for (const rule of list) {
        // selectorText first: Chrome gives CSSStyleRule its own (usually empty)
        // cssRules for CSS nesting, so a cssRules check descends into every
        // rule and never reads the selector it was standing on.
        if (rule.selectorText) {
          if (!layered) {
            for (const m of rule.selectorText.matchAll(/\.([a-zA-Z][\w-]*)/g)) legacy.add(m[1])
          }
          continue
        }
        if (rule.cssRules) {
          walk(rule.cssRules, layered || rule.constructor.name === 'CSSLayerBlockRule')
        }
      }
    }
    walk(rules, false)
  }

  const seen = new Map()
  for (const el of document.querySelectorAll('[class]')) {
    const own = [...el.classList].filter((c) => legacy.has(c))
    if (own.length === 0) continue

    const key = own.sort().join('.') + '|' + el.tagName.toLowerCase()
    if (seen.has(key)) continue

    const cs = getComputedStyle(el)
    seen.set(key, Object.fromEntries(PROPS.map((p) => [p, cs[p]])))
  }
  return [...seen].map(([key, style]) => ({ key, style }))
}, PROPS)

for (const { key, style } of rows) {
  console.log(`\n── ${key}`)
  for (const [prop, value] of Object.entries(style)) {
    if (value === '' || value === 'normal' || value === 'none' || value === 'auto') continue
    console.log(`   ${prop}: ${value}`)
  }
}
console.log(`\n${rows.length} verschiedene Klassenkombinationen auf ${ROUTE}`)

await browser.close()
