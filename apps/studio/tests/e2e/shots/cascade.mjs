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
if (!ROUTE) throw new Error('usage: node cascade.mjs <route> [--anon]')

/* Routes that exist for someone without a session — the sign-in screens — have
   to be read without one, or the dev login lands on the dashboard instead. */
const ANON = process.argv.includes('--anon')

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

if (!ANON) {
  await page.goto(`${ADMIN}/login`)
  await page.getByRole('button', { name: 'Dev-Login' }).click()
  await page.waitForURL(`${ADMIN}/`)
}
await page.goto(ADMIN + ROUTE)
await page.waitForLoadState('networkidle')

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
