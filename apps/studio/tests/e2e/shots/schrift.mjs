import { chromium } from '@playwright/test'
import { shot } from './lib.mjs'

const BASE = 'file:///Users/christian/www/client-management-portal/mockups/redesign'

/* Vier Paarungen, gleiche Screens. Alle vier sind frei lizenziert und damit
 * im selben Topf, aus dem auch die Generatoren schöpfen — nur unterschiedlich
 * weit unten. Die Kandidaten mussten drei Dinge können: ein anständiges ß,
 * Umlaute, die bei 86px nicht auseinanderfallen, und Tabellenziffern für
 * Beträge und Daten. */
const PAARE = [
  {
    key: 'a-jetzt',
    was: 'Instrument Serif + Instrument Sans — der aktuelle Stand',
    css: 'family=Instrument+Sans:wght@400;500;600;700&family=Instrument+Serif:ital@0;1',
    serif: "'Instrument Serif', Georgia, serif",
    sans: "'Instrument Sans', system-ui, sans-serif",
  },
  {
    key: 'b-fraunces',
    was: 'Fraunces + Instrument Sans — nur das Display getauscht',
    css: 'family=Instrument+Sans:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..700',
    serif: "'Fraunces', Georgia, serif",
    sans: "'Instrument Sans', system-ui, sans-serif",
  },
  {
    key: 'c-newsreader',
    was: 'Newsreader + Public Sans — ruhig, redaktionell, mit optischen Graden',
    css: 'family=Newsreader:ital,opsz,wght@0,6..72,300..700;1,6..72,300..700&family=Public+Sans:wght@400;500;600;700',
    serif: "'Newsreader', Georgia, serif",
    sans: "'Public Sans', system-ui, sans-serif",
  },
  {
    key: 'd-bricolage',
    was: 'Bricolage Grotesque + Public Sans — gar keine Serif',
    css: 'family=Bricolage+Grotesque:opsz,wght@10..48,300..800&family=Public+Sans:wght@400;500;600;700',
    serif: "'Bricolage Grotesque', system-ui, sans-serif",
    sans: "'Public Sans', system-ui, sans-serif",
  },
]

const SCREENS = [
  ['start', 'portal-start.html'],
  ['projekt', 'portal-projekt.html'],
  ['anfrage', 'portal-anfrage.html'],
]

const browser = await chromium.launch()
const page = await (await browser.newContext({
  viewport: { width: 1440, height: 900 }, locale: 'de-DE', deviceScaleFactor: 2,
})).newPage()

for (const p of PAARE) {
  console.log(`\n${p.key}  — ${p.was}`)
  for (const [name, file] of SCREENS) {
    await page.goto(`${BASE}/${file}`, { waitUntil: 'networkidle' })
    await page.addStyleTag({ url: `https://fonts.googleapis.com/css2?${p.css}&display=swap` })
    await page.addStyleTag({ content: `:root{--serif:${p.serif};--sans:${p.sans}}` })
    await page.evaluate(() => document.fonts.ready)
    await page.waitForTimeout(1600)
    await shot(page, 'schrift', `${name}--${p.key}`, { full: false })
  }
}

await browser.close()
