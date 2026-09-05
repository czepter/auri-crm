import { chromium, devices } from '@playwright/test'
import { shot } from './lib.mjs'

const BASE = 'file:///Users/christian/www/client-management-portal/mockups/redesign'
const PAGES = [
  'portal-start.html',
  'portal-start-location.html',
  'portal-anfrage.html',
  'portal-nachrichten.html',
  'portal-einstellungen.html',
  'portal-galerie.html',
  'portal-projekt.html',
  'galerie-gate.html',
  'galerie.html',
  'galerie-lightbox.html',
  'galerie-download.html',
]

// 360 ist das schmalste Gerät, das noch verbreitet ist; 390 der iPhone-Regelfall.
const WIDTHS = [
  [360, 780, 'schmal'],
  [390, 844, 'phone'],
]

/** Läuft im Browser: sucht die drei Fehler, die auf schmalen Geräten wirklich weh tun. */
const audit = () => {
  const vw = document.documentElement.clientWidth
  const out = { overflow: null, wide: [], small: [], clipped: [] }

  if (document.documentElement.scrollWidth > vw + 1) {
    out.overflow = { page: document.documentElement.scrollWidth, viewport: vw }
  }

  // Was in einer horizontal scrollbaren Leiste liegt, ragt absichtlich über
  // den Rand — Filmstreifen, Listenauswahl. Nur echtes Seiten-Querscrollen
  // ist ein Fehler, und das prüft scrollWidth oben bereits.
  const inScroller = (el) => {
    for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
      const ox = getComputedStyle(p).overflowX
      if (ox === 'auto' || ox === 'scroll') return true
    }
    return false
  }

  const label = (el) =>
    `${el.tagName.toLowerCase()}${el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : ''}` +
    (el.innerText ? ` "${el.innerText.replace(/\s+/g, ' ').trim().slice(0, 32)}"` : '')

  for (const el of document.querySelectorAll('body *')) {
    const r = el.getBoundingClientRect()
    if (r.width === 0 || r.height === 0) continue
    const style = getComputedStyle(el)
    if (style.visibility === 'hidden' || style.display === 'none' || style.opacity === '0') continue

    // Was über den rechten Rand hinausragt, verursacht das Querscrollen.
    if (r.right > vw + 1 && el.children.length === 0 && !inScroller(el)) out.wide.push({ el: label(el), right: Math.round(r.right) })

    // Tippziele unter 44px sind auf Glas nicht zuverlässig zu treffen.
    const tappable = el.matches('a, button, input[type=checkbox], select, [role=button]')
    if (tappable && (r.width < 40 || r.height < 40)) {
      out.small.push({ el: label(el), size: `${Math.round(r.width)}×${Math.round(r.height)}` })
    }

    // Text, der in seinem Kasten nicht aufgeht.
    if (el.children.length === 0 && el.scrollWidth > el.clientWidth + 2 && style.overflow !== 'visible') {
      out.clipped.push({ el: label(el), by: el.scrollWidth - el.clientWidth })
    }
  }
  return out
}

const browser = await chromium.launch()
const problems = []

for (const [w, h, label] of WIDTHS) {
  const ctx = await browser.newContext({
    ...devices['iPhone 13'],
    viewport: { width: w, height: h },
    locale: 'de-DE',
    deviceScaleFactor: 2,
  })
  const page = await ctx.newPage()

  for (const file of PAGES) {
    await page.goto(`${BASE}/${file}`, { waitUntil: 'networkidle' })
    await page.evaluate(() => document.fonts.ready)
    await page.waitForTimeout(900)

    const r = await page.evaluate(audit)
    const name = file.replace('.html', '')
    const found = []

    if (r.overflow) found.push(`querscrollt: ${r.overflow.page}px auf ${r.overflow.viewport}px`)
    if (r.wide.length) found.push(`${r.wide.length}× über den Rand: ${r.wide.slice(0, 3).map((x) => x.el).join(' | ')}`)
    if (r.small.length) found.push(`${r.small.length}× Tippziel < 40px: ${[...new Set(r.small.map((x) => `${x.el} (${x.size})`))].slice(0, 3).join(' | ')}`)
    if (r.clipped.length) found.push(`${r.clipped.length}× abgeschnitten: ${r.clipped.slice(0, 3).map((x) => x.el).join(' | ')}`)

    console.log(`\n▸ ${name} @ ${w}px`)
    if (found.length === 0) console.log('  sauber')
    found.forEach((f) => console.log('  ✗ ' + f))
    if (found.length) {
      problems.push({ name, w, found })
      await shot(page, `mobile/${label}`, `${name}--${w}`, { full: true })
    }
  }
  await page.close()
}

console.log(`\n── ${problems.length} Seite/Breite-Kombinationen mit Befund ──`)
await browser.close()
