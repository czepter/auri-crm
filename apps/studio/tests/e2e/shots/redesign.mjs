import { chromium } from '@playwright/test'
import { shot } from './lib.mjs'

const BASE = 'file:///Users/christian/www/client-management-portal/mockups/redesign'
const PAGES = [
  ['portal-start', 'portal-start.html'],
  ['portal-start-location', 'portal-start-location.html'],
  ['portal-anfrage', 'portal-anfrage.html'],
  ['portal-nachrichten', 'portal-nachrichten.html'],
  ['portal-einstellungen', 'portal-einstellungen.html'],
  ['portal-galerie', 'portal-galerie.html'],
  ['portal-projekt', 'portal-projekt.html'],
  ['galerie-gate', 'galerie-gate.html'],
  ['galerie', 'galerie.html'],
  ['galerie-lightbox', 'galerie-lightbox.html'],
  ['galerie-download', 'galerie-download.html'],
]

const browser = await chromium.launch()

for (const [w, h, label] of [
  [1440, 900, 'desktop'],
  [390, 844, 'phone'],
]) {
  const page = await (await browser.newContext({ viewport: { width: w, height: h }, locale: 'de-DE', deviceScaleFactor: 2 })).newPage()
  for (const [name, file] of PAGES) {
    await page.goto(`${BASE}/${file}`, { waitUntil: 'networkidle' })
    await page.evaluate(() => document.fonts.ready)
    await page.waitForTimeout(1400)
    await shot(page, `redesign/${label}`, `${name}--${label}`, { full: true })
    await shot(page, `redesign/${label}`, `${name}--${label}--fold`, { full: false })

    // Angaben ändern ist ein Zustand desselben Blocks, kein eigener Screen.
    if (name === 'portal-anfrage') {
      await page.locator('.editbtn').click()
      await page.waitForTimeout(400)
      await shot(page, `redesign/${label}`, `${name}--${label}--angaben-aendern`, { full: true })
    }

    // Der Auftragswechsler ist ein Zustand, kein eigener Screen.
    if (name === 'portal-projekt') {
      await page.locator('.switchbtn').click()
      await page.waitForTimeout(500)
      await shot(page, `redesign/${label}`, `${name}--${label}--wechsler-offen`, { full: false })
    }
  }
  await page.close()
}

await browser.close()
