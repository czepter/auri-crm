import { chromium } from '@playwright/test'
import { readdir } from 'node:fs/promises'
import path from 'node:path'
import { ADMIN, buttonStates, settle, shot, visit } from './lib.mjs'

const SOURCE = '/Users/christian/Desktop/viola+chris/final'
const GALLERY = process.argv[2]
if (!GALLERY) throw new Error('usage: node upload.mjs <galleryPublicId>')

const files = (await readdir(SOURCE))
  .filter((f) => /\.jpe?g$/i.test(f))
  .sort()
  .map((f) => path.join(SOURCE, f))

console.log(`${files.length} Bilder aus ${SOURCE}`)

const browser = await chromium.launch()
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  locale: 'de-DE',
  deviceScaleFactor: 2,
})
const page = await context.newPage()
const failures = []
page.on('pageerror', (e) => failures.push(`pageerror: ${e.message}`))
page.on('requestfailed', (r) => failures.push(`requestfailed ${r.method()} ${r.url()} — ${r.failure()?.errorText}`))
page.on('response', (r) => {
  if (r.status() >= 400) failures.push(`HTTP ${r.status()} ${r.request().method()} ${r.url()}`)
})

await visit(page, `${ADMIN}/login`)
await page.getByRole('button', { name: 'Dev-Login' }).click()
await settle(page, 900)

const url = `${ADMIN}/galerien/${GALLERY}/medien`
await visit(page, url)
await shot(page, 'gallery/upload', 'ingest--leer')
await buttonStates(page, 'gallery/upload', 'ingest-leer')

// The picker is a hidden <input type=file> behind a styled label.
await page.locator('input[type=file]').setInputFiles(files)

// Photograph the transfer while it is running rather than only its end state.
const started = Date.now()
let taken = 0
while (Date.now() - started < 20 * 60_000) {
  const done = await page.locator('.chip.done').count()
  const percent = (await page.locator('.chip.now').first().innerText().catch(() => '')) || '?'
  if (taken < 8 && (taken === 0 || done > 0)) {
    await shot(page, 'gallery/upload', `ingest--transfer-${String(taken).padStart(2, '0')}-${done}-von-${files.length}`)
    taken++
  }
  console.log(`  ${done}/${files.length} fertig · ${percent}`)
  if (done >= files.length) break
  const failed = await page.locator('.chip.fail').count()
  if (failed > 0) {
    await shot(page, 'gallery/upload', `ingest--fehler-${failed}`)
    failures.push(`${failed} Datei(en) mit Upload-Fehler`)
  }
  await page.waitForTimeout(4000)
}

await shot(page, 'gallery/upload', 'ingest--alle-fertig')

// Reload: the failed-asset tray (G-04) is server-rendered, so it only appears
// after the ingest queue has had its say.
await page.waitForTimeout(8000)
await visit(page, url)
await shot(page, 'gallery/upload', 'ingest--nach-reload')

console.log(`\nfailures: ${failures.length}`)
failures.slice(0, 40).forEach((f) => console.log('  ✗ ' + f))

await browser.close()
