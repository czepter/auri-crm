import { chromium } from '@playwright/test'
import { shot } from './lib.mjs'

const BASE = 'file:///Users/christian/www/client-management-portal/mockups/redesign'

/* Vier Grundtöne, gleiche Screens. Mitgeführt werden immer alle drei Werte,
 * die zusammengehören — Fläche, abgesenkte Fläche, Linie —, sonst vergleicht
 * man einen warmen Rahmen mit einer kalten Trennlinie. */
const TOENE = [
  ['a-reinweiss', '#FFFFFF', '#F4F4F5', '#E7E7E9', 'reines Weiß, kühle Linien'],
  ['b-neutral',   '#FAFAFA', '#F1F1F2', '#E5E5E7', 'neutrales Off-White'],
  ['c-spur-warm', '#FCFBF9', '#F3F0EC', '#E8E3DC', 'Spur warm, halb so weit wie jetzt'],
  ['d-jetzt',     '#FBF8F5', '#F1EAE3', '#E6DCD2', 'der aktuelle Entwurf'],
]

const SCREENS = [
  ['start', 'portal-start.html'],
  ['projekt', 'portal-projekt.html'],
]

const browser = await chromium.launch()
const page = await (await browser.newContext({
  viewport: { width: 1440, height: 900 }, locale: 'de-DE', deviceScaleFactor: 2,
})).newPage()

for (const [key, paper, sunk, line, was] of TOENE) {
  console.log(`\n${key}  ${paper}  — ${was}`)
  for (const [name, file] of SCREENS) {
    await page.goto(`${BASE}/${file}`, { waitUntil: 'networkidle' })
    await page.evaluate(() => document.fonts.ready)
    await page.addStyleTag({
      content: `:root{--paper:${paper};--sunk:${sunk};--line:${line};--line-2:${line}}`,
    })
    await page.waitForTimeout(900)
    await shot(page, 'grundton', `${name}--${key}`, { full: false })
  }
}

await browser.close()
