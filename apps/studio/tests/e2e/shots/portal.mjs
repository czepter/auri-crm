import { chromium } from '@playwright/test'
import { ADMIN, PORTAL, buttonStates, openStates, settle, shot, visit } from './lib.mjs'

const CONTACT_EMAIL = process.argv[2] ?? 'meier.kowalski@example.test'
const MAILPIT = 'http://localhost:8025'

const failures = []
const browser = await chromium.launch()
const ctx = { viewport: { width: 1440, height: 900 }, locale: 'de-DE', deviceScaleFactor: 2 }

// ─── admin: open the portal up, so every client screen has something on it ──
const admin = await (await browser.newContext(ctx)).newPage()
await visit(admin, `${ADMIN}/login`)
await admin.getByRole('button', { name: 'Dev-Login' }).click()
await settle(admin, 900)

await visit(admin, `${ADMIN}/einstellungen/portal`)
await shot(admin, 'portal/setup', 'einstellungen-portal--alles-aus')
for (const id of [
  '#cb-portal-accept-quotes-enabled',
  '#cb-portal-sign-contracts-enabled',
  '#cb-portal-payment-status-enabled',
]) {
  const box = admin.locator(id)
  if ((await box.getAttribute('aria-checked')) !== 'true' && !(await box.isChecked().catch(() => false))) {
    await box.click()
  }
}
// P-09 (#228): messaging is three states now, not a checkbox. `any` is the
// one that leaves every portal screen with something on it.
await admin.locator('#rb-messaging-any').click()
await shot(admin, 'portal/setup', 'einstellungen-portal--alles-an')
const save = admin.getByRole('button', { name: /speichern/i })
if (await save.isEnabled()) {
  await save.click()
  await settle(admin)
  await shot(admin, 'portal/setup', 'einstellungen-portal--gespeichert')
}

// Make every project this contact is on visible in the portal.
const projects = JSON.parse(process.env.PORTAL_PROJECTS ?? '[]')
for (const publicId of projects) {
  await visit(admin, `${ADMIN}/auftraege/${publicId}`)
  const toggle = admin.getByRole('button', { name: 'Im Portal zeigen' })
  if (await toggle.count()) {
    await shot(admin, 'portal/setup', `auftrag-${publicId}--portal-ausgeblendet`)
    await toggle.first().click()
    await settle(admin)
    // A confirm dialog stands between the click and the write.
    const confirm = admin.getByRole('button', { name: /^(Ja|Bestätigen|Im Portal zeigen)$/ })
    if (await confirm.count()) {
      await shot(admin, 'portal/setup', `auftrag-${publicId}--portal-bestaetigung`, { full: false })
      await confirm.last().click()
      await settle(admin)
    }
    await shot(admin, 'portal/setup', `auftrag-${publicId}--portal-sichtbar`)
  }
}

// ─── portal: the way in ─────────────────────────────────────────────────────
const portalCtx = await browser.newContext(ctx)
const page = await portalCtx.newPage()
page.on('pageerror', (e) => failures.push(`pageerror ${page.url()}: ${e.message}`))
page.on('response', (r) => {
  if (r.status() >= 500) failures.push(`HTTP ${r.status()} ${r.url()}`)
})

await visit(page, `${PORTAL}/anmelden`)
await shot(page, 'portal', 'anmelden')
await buttonStates(page, 'portal', 'anmelden')

await page.getByLabel('E-Mail-Adresse').fill('keine-email')
await page.getByRole('button', { name: 'Link anfordern' }).click()
await settle(page)
await shot(page, 'portal', 'anmelden--ungueltige-adresse')

const since = Date.now()
await page.getByLabel('E-Mail-Adresse').fill(CONTACT_EMAIL)
await page.getByRole('button', { name: 'Link anfordern' }).click()
await settle(page, 800)
await shot(page, 'portal', 'anmelden--link-verschickt')

// ─── pull the magic link out of Mailpit ─────────────────────────────────────
async function magicLink() {
  for (let i = 0; i < 30; i++) {
    const list = await fetch(`${MAILPIT}/api/v1/search?query=${encodeURIComponent('to:' + CONTACT_EMAIL)}&limit=5`)
      .then((r) => r.json())
      .catch(() => null)
    const message = list?.messages?.find((m) => new Date(m.Created).getTime() > since - 60_000)
    if (message) {
      const body = await fetch(`${MAILPIT}/api/v1/message/${message.ID}`).then((r) => r.json())
      const match = (body.Text + ' ' + body.HTML).match(/https?:\/\/[^\s"'<]*\/zugang\/[a-f0-9]+/)
      if (match) return match[0]
    }
    await new Promise((r) => setTimeout(r, 2000))
  }
  return null
}

const link = await magicLink()
if (!link) {
  failures.push('Kein Magic-Link in Mailpit angekommen')
  console.log('  ✗ kein Magic-Link')
} else {
  console.log(`  magic link: ${link}`)
  await visit(page, link.replace(/^https?:\/\/[^/]+/, PORTAL))
  await shot(page, 'portal', 'startseite')

  const screens = [['startseite', '/']]
  // Whatever the portal itself links to from here — the client surface is
  // small enough to enumerate off its own navigation rather than guessed.
  const hrefs = await page.locator('a[href^="/"]').evaluateAll((els) => [
    ...new Set(els.map((e) => e.getAttribute('href'))),
  ])
  const projectHref = process.env.PORTAL_PROJECT
    ? `/projekte/${process.env.PORTAL_PROJECT}`
    : hrefs.find((h) => /^\/projekte\/[a-z0-9]+$/.test(h))

  if (projectHref) {
    screens.push(
      ['projekt', projectHref],
      ['projekt-vertrag', `${projectHref}/vertrag`],
      ['projekt-angebot', `${projectHref}/angebot`],
      ['projekt-zahlungen', `${projectHref}/zahlungen`],
    )
  }
  // P-06 (#225): the couple's own side of a gallery, if they have one.
  const galleryHref = hrefs.find((h) => /^\/galerien\/[a-z0-9]+\/eure-auswahl$/.test(h))
  if (galleryHref) screens.push(['galerie-auswahl', galleryHref])

  screens.push(
    ['nachrichten', '/nachrichten'],
    ['einstellungen', '/einstellungen'],
    ['impressum', '/impressum'],
  )

  for (const [name, url] of screens) {
    const status = await visit(page, PORTAL + url)
    if (status >= 400) {
      failures.push(`${name}: HTTP ${status} ${url}`)
      console.log(`  ! ${name} → ${status}`)
      continue
    }
    console.log(`▸ ${name}`)
    await shot(page, 'portal', name)
    await buttonStates(page, 'portal', name)
    await openStates(page, 'portal', name, page.url())
  }

  // Responsive — the client surface is the one a phone actually opens.
  for (const [w, h, label] of [
    [834, 1112, 'tablet'],
    [390, 844, 'phone'],
  ]) {
    await page.setViewportSize({ width: w, height: h })
    for (const [name, url] of screens) {
      if ((await visit(page, PORTAL + url)) >= 400) continue
      await shot(page, `portal/${label}`, `${name}--${label}`)
    }
  }
  await page.setViewportSize({ width: 1440, height: 900 })
}

// ─── the screens that only exist in a particular studio state ───────────────
const contactId = process.env.PORTAL_CONTACT_ID
if (contactId) {
  await visit(page, `${PORTAL}/abmelden/${contactId}`)
  await shot(page, 'portal', 'abmeldung')
  await buttonStates(page, 'portal', 'abmeldung')
}

// A dead / already-spent magic link.
await visit(page, `${PORTAL}/zugang/${'0'.repeat(64)}`)
await shot(page, 'portal', 'anmelden--link-abgelaufen')

// T-08's maintenance closure, switched on from the admin surface and off again.
if (!process.env.SKIP_MAINTENANCE) {
await visit(admin, `${ADMIN}/einstellungen/portal`)
await admin.locator('#cb-portal-maintenance-enabled').click()
await admin.locator('#po-wartungstext').fill('Wir sind bis Montag im Studio nicht erreichbar.')
await admin.getByRole('button', { name: /speichern/i }).click()
await settle(admin)
await shot(admin, 'portal/setup', 'einstellungen-portal--wartung-an')

const guest = await (await browser.newContext(ctx)).newPage()
await visit(guest, `${PORTAL}/`)
await shot(guest, 'portal', 'wartung--geschlossen')
await visit(guest, `${PORTAL}/impressum`)
await shot(guest, 'portal', 'wartung--impressum-bleibt-erreichbar')
await guest.close()

await visit(admin, `${ADMIN}/einstellungen/portal`)
await admin.locator('#cb-portal-maintenance-enabled').click()
await admin.getByRole('button', { name: /speichern/i }).click()
await settle(admin)
await shot(admin, 'portal/setup', 'einstellungen-portal--wartung-aus')
}

console.log(`\nfailures: ${failures.length}`)
failures.forEach((f) => console.log('  ✗ ' + f))
await browser.close()
