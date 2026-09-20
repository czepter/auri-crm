import { chromium } from '@playwright/test'
import { writeFile } from 'node:fs/promises'
import { ADMIN, OUT, buttonStates, openStates, report, settle, shot, visit } from './lib.mjs'

const VIEWPORT = { width: 1440, height: 900 }

/** Reads the first matching href off the current page, minus the origin. */
async function firstHref(page, pattern) {
  const hrefs = await page.locator('a[href]').evaluateAll((els) => els.map((e) => e.getAttribute('href')))
  return hrefs.find((h) => h && pattern.test(h)) ?? null
}

const browser = await chromium.launch()
const context = await browser.newContext({ viewport: VIEWPORT, locale: 'de-DE', deviceScaleFactor: 2 })
const page = await context.newPage()

const failures = []
page.on('pageerror', (e) => failures.push(`pageerror ${page.url()}: ${e.message}`))
page.on('response', (r) => {
  if (r.status() >= 500) failures.push(`HTTP ${r.status()} ${r.url()}`)
})

// ─── guest screens ────────────────────────────────────────────────────────
await visit(page, `${ADMIN}/login`)
await shot(page, 'admin/auth', 'login')
await buttonStates(page, 'admin/auth', 'login')

// filled + validation-error states of the same form
await page.getByLabel('E-Mail').fill('falsch@studio.test')
await page.getByLabel('Passwort', { exact: true }).fill('nichtdaspasswort')
await shot(page, 'admin/auth', 'login--filled')
await page.getByRole('button', { name: 'Anmelden' }).click()
await settle(page)
await shot(page, 'admin/auth', 'login--wrong-credentials')

await visit(page, `${ADMIN}/passwort/vergessen`)
await shot(page, 'admin/auth', 'passwort-vergessen')
await buttonStates(page, 'admin/auth', 'passwort-vergessen')
await page.getByLabel('E-Mail').fill('christian@nordlicht.test').catch(() => {})
await page.getByRole('button').first().click().catch(() => {})
await settle(page)
await shot(page, 'admin/auth', 'passwort-vergessen--abgeschickt')

// The MFA challenge, reached with the seeded e2e account (password known).
await visit(page, `${ADMIN}/login`)
await page.getByLabel('E-Mail').fill('e2e-mfa@studio.test')
await page.getByLabel('Passwort', { exact: true }).fill('correct-horse')
await page.getByRole('button', { name: 'Anmelden' }).click()
await settle(page)
await shot(page, 'admin/auth', 'mfa-challenge')
await buttonStates(page, 'admin/auth', 'mfa-challenge')
await page.locator('#code').click().then(() => page.keyboard.type('000000')).catch(() => {})
await page.getByRole('button', { name: 'Bestätigen' }).click().catch(() => {})
await settle(page)
await shot(page, 'admin/auth', 'mfa-challenge--falscher-code')

// ─── sign in ──────────────────────────────────────────────────────────────
await visit(page, `${ADMIN}/login`)
await page.getByRole('button', { name: 'Dev-Login' }).click()
await settle(page, 900)
await shot(page, 'admin', 'dashboard')

// ─── discover ids from the screens themselves ─────────────────────────────
const ids = {}
await visit(page, `${ADMIN}/kontakte`)
ids.contact = await firstHref(page, /^\/kontakte\/[a-z0-9]+$/)
await visit(page, `${ADMIN}/anfragen`)
ids.project = await firstHref(page, /^\/auftraege\/[a-z0-9]+/)
await visit(page, `${ADMIN}/pakete`)
ids.package = await firstHref(page, /^\/pakete\/(?!neu)/)
await visit(page, `${ADMIN}/vorlagen?typ=dokumente`)
ids.documentTemplate = await firstHref(page, /^\/dokumentvorlagen\/(?!neu|vorschau)/)
await visit(page, `${ADMIN}/vorlagen?typ=fragebogen`)
ids.formTemplate = await firstHref(page, /fragebogen-vorlagen\/(?!neu)/)
console.log('discovered:', ids)
await writeFile(`${OUT}/ids.json`, JSON.stringify(ids, null, 2))

// ─── every GET screen on the admin surface ────────────────────────────────
const project = ids.project?.replace(/\/$/, '') ?? ''

const screens = [
  ['dashboard', '/'],
  ['anfragen-board', '/anfragen'],
  ['anfragen-neu', '/anfragen/neu'],
  ['kontakte', '/kontakte'],
  ['kontakt-detail', ids.contact],
  ['verfuegbarkeit', '/verfuegbarkeit'],
  ['kalender', '/kalender'],
  ['posteingang', '/posteingang'],
  ['auswertungen', '/auswertungen'],
  ['abwesenheiten', '/abwesenheiten'],
  ['team', '/team'],
  ['pakete', '/pakete'],
  ['pakete-neu', '/pakete/neu'],
  ['paket-detail', ids.package],
  ['vorlagen', '/vorlagen'],
  ['vorlagen-aufgaben', '/vorlagen?typ=aufgaben'],
  ['vorlagen-fragebogen', '/vorlagen?typ=fragebogen'],
  ['vorlagen-dokumente', '/vorlagen?typ=dokumente'],
  ['vorlagen-galerien', '/vorlagen?typ=galerien'],
  ['dokumentvorlagen-neu', '/dokumentvorlagen/neu'],
  ['dokumentvorlage-detail', ids.documentTemplate],
  ['einstellungen', '/einstellungen'],
  ['einstellungen-nummernkreise', '/einstellungen/nummernkreise'],
  ['einstellungen-verfuegbarkeit', '/einstellungen/verfuegbarkeit'],
  ['einstellungen-zahlungen', '/einstellungen/zahlungen'],
  ['einstellungen-fragebogen-vorlagen-neu', '/einstellungen/fragebogen-vorlagen/neu'],
  ['einstellungen-fragebogen-vorlage-detail', ids.formTemplate],
  ['einstellungen-portal', '/einstellungen/portal'],
  ['einstellungen-benachrichtigungen', '/einstellungen/benachrichtigungen'],
  ['auftrag-uebersicht', project],
  ['auftrag-shootings', `${project}/shootings`],
  ['auftrag-nachrichten', `${project}/nachrichten`],
  ['auftrag-angebote', `${project}/angebote`],
  ['auftrag-frageboegen', `${project}/frageboegen`],
  ['auftrag-vertrag', `${project}/vertrag`],
  ['mfa-enroll', '/auth/mfa/enroll'],
  ['mfa-recovery-codes', '/auth/mfa/recovery-codes'],
].filter(([, url]) => url)

for (const [name, url] of screens) {
  const full = url.startsWith('http') ? url : ADMIN + url
  const status = await visit(page, full)
  if (status >= 400) {
    failures.push(`${name}: HTTP ${status} at ${full}`)
    console.log(`  ! ${name} → ${status}`)
    continue
  }
  console.log(`▸ ${name}`)
  await shot(page, 'admin', name)
  await buttonStates(page, 'admin', name)
  await openStates(page, 'admin', name, page.url())
}

// ─── responsive: the same screens at tablet and phone widths ──────────────
const mobile = await context.newPage()
for (const [w, h, label] of [
  [834, 1112, 'tablet'],
  [390, 844, 'phone'],
]) {
  await mobile.setViewportSize({ width: w, height: h })
  for (const [name, url] of screens.slice(0, 14)) {
    await visit(mobile, url.startsWith('http') ? url : ADMIN + url)
    await shot(mobile, `admin/${label}`, `${name}--${label}`)
  }
}
await mobile.close()

await writeFile(`${OUT}/admin-report.json`, JSON.stringify({ shots: report(), failures }, null, 2))
console.log(`\n${report().length} screenshots, ${failures.length} failures`)
failures.forEach((f) => console.log('  ✗ ' + f))

await browser.close()
