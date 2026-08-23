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

// Upload lives in Bilder now, not its own /medien screen — Ingest.vue is
// gone. Bilder is the record's default tab.
const url = `${ADMIN}/galerien/${GALLERY}`
await visit(page, url)
await shot(page, 'gallery/upload', 'bilder--leer')
await buttonStates(page, 'gallery/upload', 'bilder-leer')

// The picker is a hidden <input type=file> behind DropZone's styled label.
await page.locator('input[type=file]').setInputFiles(files)

// UploadTray carries no per-row status class the way the old ingest chips
// did — its summary line ("{done} / {total} · {percent} %") is the only
// thing worth polling; only its first 4 rows ever render, so a per-file
// failure count isn't readable here the way it once was.
const SUMMARY = /(\d+) \/ (\d+) · (\d+) ?%/

async function trayProgress() {
  const text = await page.getByText(SUMMARY).first().innerText().catch(() => null)
  const match = text?.match(SUMMARY)
  return match ? { done: Number(match[1]), total: Number(match[2]), percent: match[3] } : null
}

const started = Date.now()
let taken = 0
while (Date.now() - started < 20 * 60_000) {
  const progress = await trayProgress()
  if (progress === null) break // tray closed — every file settled

  if (taken < 8 && (taken === 0 || progress.done > 0)) {
    await shot(page, 'gallery/upload', `transfer-${String(taken).padStart(2, '0')}-${progress.done}-von-${files.length}`)
    taken++
  }
  console.log(`  ${progress.done}/${files.length} fertig · ${progress.percent}%`)
  if (progress.done >= progress.total) break
  await page.waitForTimeout(4000)
}

await shot(page, 'gallery/upload', 'alle-fertig')

// Reload: the needs-attention tray (G-04, now part of Bilder rather than its
// own screen) is server-rendered, so a Failed row only appears once the
// ingest queue has had its say.
await page.waitForTimeout(8000)
await visit(page, url)
await shot(page, 'gallery/upload', 'nach-reload')

if (await page.getByText('Prüfung erforderlich').count() > 0) {
  failures.push('needs-attention tray carries at least one Failed asset after ingest')
  await shot(page, 'gallery/upload', 'nach-reload--fehler')
}

console.log(`\nfailures: ${failures.length}`)
failures.slice(0, 40).forEach((f) => console.log('  ✗ ' + f))

await browser.close()
