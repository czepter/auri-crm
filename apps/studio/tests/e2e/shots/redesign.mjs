import { chromium } from '@playwright/test'
import { shot } from './lib.mjs'

const BASE = 'file:///Users/christian/www/client-management-portal/mockups/redesign'
const PAGES = [
  ['portal-projekt', 'portal-projekt.html'],
  ['galerie-gate', 'galerie-gate.html'],
  ['galerie', 'galerie.html'],
]

const browser = await chromium.launch()

for (const [w, h, label, full] of [
  [1440, 900, 'desktop', true],
  [390, 844, 'phone', true],
]) {
  const page = await (await browser.newContext({ viewport: { width: w, height: h }, locale: 'de-DE', deviceScaleFactor: 2 })).newPage()
  for (const [name, file] of PAGES) {
    await page.goto(`${BASE}/${file}`, { waitUntil: 'networkidle' })
    await page.evaluate(() => document.fonts.ready)
    await page.waitForTimeout(1400)
    await shot(page, `redesign/${label}`, `${name}--${label}`, { full })
    // Und der erste Bildschirm allein — die Fassade zählt getrennt vom Rest.
    await shot(page, `redesign/${label}`, `${name}--${label}--fold`, { full: false })
  }
  await page.close()
}

await browser.close()
