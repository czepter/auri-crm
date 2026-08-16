import { chromium } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { ADMIN, buttonStates, settle, shot, visit } from './lib.mjs'

const SECTION = 'admin/onboarding'
const REPO = '/Users/christian/www/client-management-portal'
const notes = []

function tinker(php) {
  return execFileSync(
    'docker',
    ['compose', 'exec', '-T', '-e', 'APP_SURFACE=admin', 'php', 'php', 'artisan', 'tinker', '--execute', php],
    { cwd: REPO, encoding: 'utf8' },
  ).trim()
}

// The wizard writes through the real settings endpoints, so the demo studio's
// own row has to come back afterwards.
const snapshot = tinker('echo App\\Settings\\Models\\StudioSettings::query()->first()->toJson();').split('\n').pop()
tinker("App\\Settings\\Models\\StudioSettings::query()->update(['onboarding_completed_at' => null]); Cache::store('redis')->flush(); echo 'wizard scharf';")

const browser = await chromium.launch()
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'de-DE', deviceScaleFactor: 2 })
const page = await context.newPage()

/** Inertia hands validation errors back as props, not as a 422 body — read them off the rendered form. */
const errors = () => page.locator('.err:visible').allInnerTexts()
const stepName = async () => (await page.locator('h1').first().innerText()).trim()
const stepCount = async () => (await page.locator('h1 + *').first().innerText().catch(() => '')).trim()

async function reopen() {
  tinker("App\\Settings\\Models\\StudioSettings::query()->update(['onboarding_completed_at' => null]); Cache::store('redis')->flush(); echo 'ok';")
  await visit(page, `${ADMIN}/einrichtung/studio`)
}

await visit(page, `${ADMIN}/login`)
await page.getByRole('button', { name: 'Dev-Login' }).click()
await settle(page, 900)
await visit(page, `${ADMIN}/einrichtung/studio`)

// ── Schritt 1 · Studio-Profil ───────────────────────────────────────────────
await shot(page, SECTION, '01-profil--unberuehrt')
await buttonStates(page, SECTION, '01-profil')

// Bankverbindung leer lassen — seit der Änderung darf das durchgehen.
await page.getByRole('button', { name: 'Weiter' }).click()
await settle(page, 1200)
notes.push(`Bankverbindung leer → ${await stepName()} · ${await stepCount()} · Fehler: ${JSON.stringify(await errors())}`)
await shot(page, SECTION, '02-nach-profil-ohne-bankverbindung')

// Die beiden echten Ablehnungen dieses Schritts, jede einzeln.
await reopen()
await page.locator('#p-ustid').fill('')
await page.locator('#p-steuernr').fill('')
await page.getByRole('button', { name: 'Weiter' }).click()
await settle(page, 1200)
await shot(page, SECTION, '01-profil--ohne-steuernummer-und-ustid-abgelehnt')
notes.push(`ohne Steuernummer + USt-IdNr. → ${await stepName()} · Fehler: ${JSON.stringify(await errors())}`)

await reopen()
await page.locator('#p-iban').fill('DE00 1234')
await page.locator('#p-kontoinhaber').fill('Nordlicht Fotografie GmbH')
await page.locator('#p-bic').fill('BYLADEM1001')
await shot(page, SECTION, '01-profil--kaputte-iban-getippt')
await page.getByRole('button', { name: 'Weiter' }).click()
await settle(page, 1200)
await shot(page, SECTION, '01-profil--kaputte-iban-abgelehnt')
notes.push(`kaputte IBAN → ${await stepName()} · Fehler: ${JSON.stringify(await errors())}`)

// Und jetzt der saubere Durchlauf mit vollständiger Bankverbindung.
await reopen()
await page.locator('#p-kontoinhaber').fill('Nordlicht Fotografie GmbH')
await page.locator('#p-iban').fill('DE02120300000000202051')
await page.locator('#p-bic').fill('BYLADEM1001')
await shot(page, SECTION, '01-profil--vollstaendig-ausgefuellt')
await page.getByRole('button', { name: 'Weiter' }).click()
await settle(page, 1400)
notes.push(`vollständig → ${await stepName()} · ${await stepCount()}`)

// ── Schritte 2 bis 6, jeder gleich behandelt ────────────────────────────────
for (let i = 2; i <= 6; i++) {
  const name = await stepName()
  const slug = `${String(i).padStart(2, '0')}-${name.toLowerCase().replace(/[^a-zäöüß]+/g, '-').replace(/^-|-$/g, '')}`
  await shot(page, SECTION, `${slug}--offen`)
  await buttonStates(page, SECTION, slug)
  notes.push(`Schritt ${i}: ${name} · ${await stepCount()}`)

  const next = page.getByRole('button', { name: /^(Weiter|Fertigstellen)$/ }).first()
  const label = (await next.innerText()).trim()
  await next.click()
  await settle(page, 1600)

  if (!page.url().includes('/einrichtung')) {
    notes.push(`"${label}" auf Schritt ${i} beendet den Wizard → ${new URL(page.url()).pathname}`)
    break
  }
  const after = await stepName()
  if (after === name) {
    await shot(page, SECTION, `${slug}--abgelehnt`)
    notes.push(`Schritt ${i} (${name}) kam nicht weiter · Fehler: ${JSON.stringify(await errors())}`)
    break
  }
}

await settle(page, 900)
await shot(page, SECTION, '07-nach-abschluss')
notes.push(`Abschluss landet auf ${new URL(page.url()).pathname}`)
notes.push(`onboarding_completed_at danach: ${tinker('echo App\\Settings\\Models\\StudioSettings::query()->first()->onboarding_completed_at ?? "null";').split('\n').pop()}`)

// ── "Später abschließen" ────────────────────────────────────────────────────
await reopen()
await shot(page, SECTION, '08-spaeter-abschliessen--vorher')
await page.getByRole('button', { name: /Später abschließen/ }).click()
await settle(page, 1000)
await shot(page, SECTION, '08-spaeter-abschliessen--danach')
notes.push(`"Später abschließen" → ${new URL(page.url()).pathname}`)
notes.push(`onboarding_completed_at danach: ${tinker('echo App\\Settings\\Models\\StudioSettings::query()->first()->onboarding_completed_at ?? "null";').split('\n').pop()}`)

// Was das Gate mit einer anderen Seite macht, solange der Wizard offen ist.
await reopen()
await visit(page, `${ADMIN}/anfragen`)
await shot(page, SECTION, '09-gate--andere-seite-bei-offenem-wizard')
notes.push(`/anfragen bei offenem Onboarding → ${new URL(page.url()).pathname}`)

await browser.close()

// ── zurücksetzen ────────────────────────────────────────────────────────────
tinker(
  `$row = json_decode('${snapshot.replace(/'/g, "\\'")}', true);` +
    `unset($row['id']);` +
    `App\\Settings\\Models\\StudioSettings::query()->first()->forceFill($row)->save();` +
    `Cache::store('redis')->flush(); echo 'settings zurueckgesetzt';`,
)

console.log('\n── Beobachtungen ──')
notes.forEach((n) => console.log('  · ' + n))
