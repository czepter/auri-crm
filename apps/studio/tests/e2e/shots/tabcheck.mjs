import { chromium } from '@playwright/test'
import { shot } from './lib.mjs'
const browser = await chromium.launch()
const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'de-DE', deviceScaleFactor: 2 })).newPage()
for (const f of ['portal-start', 'portal-galerie', 'portal-einstellungen']) {
  await page.goto(`file:///Users/christian/www/client-management-portal/mockups/redesign/${f}.html`, { waitUntil: 'networkidle' })
  await page.evaluate(() => document.fonts.ready)
  await page.waitForTimeout(900)
  const box = await page.locator('.tabbar').boundingBox()
  console.log(`${f}: Tableiste bei y=${Math.round(box.y)} (Viewport 844), Höhe ${Math.round(box.height)}`)
  await shot(page, 'mobile/leiste', `${f}--390-oben`, { full: false })
  await page.evaluate(() => window.scrollTo(0, 9999))
  await page.waitForTimeout(400)
  await shot(page, 'mobile/leiste', `${f}--390-unten`, { full: false })
}
await browser.close()
