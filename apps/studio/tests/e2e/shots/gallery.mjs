import { chromium } from '@playwright/test'
import { API, GALLERY, PORTAL, buttonStates, settle, shot, visit } from './lib.mjs'

const ID = process.argv[2]
if (!ID) throw new Error('usage: node gallery.mjs <galleryPublicId>')
const PASSWORD = 'sommer2026'
const PIN = '4711'
const GUEST_EMAIL = 'gast@example.test'
const MAILPIT = 'http://localhost:8025'

const failures = []
const browser = await chromium.launch()

async function guest() {
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    locale: 'de-DE',
    deviceScaleFactor: 2,
  })
  // The Astro SSR page renders image URLs against API_URL, which is the
  // compose hostname `api:8000` — reachable from the gallery container, not
  // from a browser on the host. Rewriting them here is the local equivalent
  // of the public api hostname a deploy would hand it.
  await context.route('http://api:8000/**', (route) =>
    route.continue({ url: route.request().url().replace('http://api:8000', API) }),
  )
  const page = await context.newPage()
  page.on('pageerror', (e) => failures.push(`pageerror ${page.url()}: ${e.message}`))
  page.on('response', (r) => {
    if (r.status() >= 500) failures.push(`HTTP ${r.status()} ${r.url()}`)
  })
  return page
}

// ─── the Astro guest gallery: grid, lightbox, favorite, comment ─────────────
{
  const page = await guest()
  await visit(page, `${GALLERY}/g/${ID}`)
  await page.waitForTimeout(2500) // let the lazy grid decode
  await shot(page, 'gallery/guest', 'astro-raster')
  await page.locator('.tile').first().hover()
  await page.waitForTimeout(250)
  await shot(page, 'gallery/guest', 'astro-raster--kachel-hover', { full: false })

  await page.locator('.tile').first().click()
  await page.waitForTimeout(1500)
  await shot(page, 'gallery/guest', 'astro-lightbox', { full: false })

  await page.locator('[data-lb-next]').click()
  await page.waitForTimeout(1200)
  await shot(page, 'gallery/guest', 'astro-lightbox--naechstes-bild', { full: false })
  await page.locator('[data-lb-prev]').click()
  await page.waitForTimeout(1200)

  await page.locator('[data-lb-favorite]').click()
  await page.waitForTimeout(900)
  await shot(page, 'gallery/guest', 'astro-lightbox--favorit-gesetzt', { full: false })
  await page.locator('[data-lb-favorite]').click()
  await page.waitForTimeout(900)
  await shot(page, 'gallery/guest', 'astro-lightbox--favorit-entfernt', { full: false })
  await page.locator('[data-lb-favorite]').click()
  await page.waitForTimeout(900)

  await page.locator('[data-lb-comment-input]').fill('Das dritte von oben bitte größer ausbelichten.')
  await shot(page, 'gallery/guest', 'astro-lightbox--kommentar-getippt', { full: false })
  await page.locator('[data-lb-comment-input]').press('Enter')
  await page.waitForTimeout(1200)
  await shot(page, 'gallery/guest', 'astro-lightbox--kommentar-gespeichert', { full: false })

  await page.locator('[data-lb-thumb]').nth(5).click()
  await page.waitForTimeout(1200)
  await shot(page, 'gallery/guest', 'astro-lightbox--ueber-streifen-gewechselt', { full: false })

  await page.locator('[data-lb-close]').first().click()
  await page.waitForTimeout(600)
  await shot(page, 'gallery/guest', 'astro-raster--zurueck-aus-lightbox', { full: false })

  for (const [w, h, label] of [
    [834, 1112, 'tablet'],
    [390, 844, 'phone'],
  ]) {
    await page.setViewportSize({ width: w, height: h })
    await visit(page, `${GALLERY}/g/${ID}`)
    await page.waitForTimeout(2000)
    await shot(page, `gallery/guest/${label}`, `astro-raster--${label}`)
    await page.locator('.tile').first().click()
    await page.waitForTimeout(1500)
    await shot(page, `gallery/guest/${label}`, `astro-lightbox--${label}`, { full: false })
  }

  await visit(page, `${GALLERY}/g/gibtesnicht0000000000000`)
  await shot(page, 'gallery/guest', 'astro--unbekannte-galerie')
  await page.context().close()
}

// ─── the portal-surface gates, ungated first ───────────────────────────────
{
  const page = await guest()
  await visit(page, `${PORTAL}/galerien/${ID}`)
  await shot(page, 'gallery/gates', 'ansicht--ohne-passwort-direkt-offen')
  await buttonStates(page, 'gallery/gates', 'ansicht-offen')
  await visit(page, `${PORTAL}/galerien/${ID}/download`)
  await shot(page, 'gallery/gates', 'download--ohne-gates-bereit')
  await page.context().close()
}

// ─── switch the three gates on ──────────────────────────────────────────────
const { execSync } = await import('node:child_process')
execSync(
  `docker compose exec -T -e APP_SURFACE=admin php php artisan tinker --execute="` +
    `\\$g = App\\\\Gallery\\\\Models\\\\Gallery::findByPublicId('${ID}');` +
    `\\$g->rotateViewPassword('${PASSWORD}');` +
    `\\$g->rotateDownloadPin('${PIN}');` +
    `\\$g->rotateEmailVerification(true);` +
    `echo 'gates on';"`,
  { cwd: '/Users/christian/www/client-management-portal', stdio: 'inherit' },
)

{
  const page = await guest()
  await visit(page, `${PORTAL}/galerien/${ID}`)
  await shot(page, 'gallery/gates', 'ansicht--passwort-gefragt')
  await buttonStates(page, 'gallery/gates', 'ansicht-passwort')

  await page.getByLabel('Galerie-Passwort').fill('falschespasswort')
  await shot(page, 'gallery/gates', 'ansicht--passwort-getippt')
  await page.getByRole('button', { name: 'Galerie öffnen' }).click()
  await settle(page)
  await shot(page, 'gallery/gates', 'ansicht--passwort-falsch')

  await page.getByLabel('Galerie-Passwort').fill(PASSWORD)
  await page.getByRole('button', { name: 'Galerie öffnen' }).click()
  await settle(page)
  await shot(page, 'gallery/gates', 'ansicht--freigeschaltet')

  // PIN gate
  await visit(page, `${PORTAL}/galerien/${ID}/download`)
  await shot(page, 'gallery/gates', 'download--pin-gefragt')
  await buttonStates(page, 'gallery/gates', 'download-pin')
  const pinField = page.locator('input').first()
  await pinField.fill('0000')
  await page.getByRole('button').first().click()
  await settle(page)
  await shot(page, 'gallery/gates', 'download--pin-falsch')
  await page.locator('input').first().fill(PIN)
  await page.getByRole('button').first().click()
  await settle(page)
  await shot(page, 'gallery/gates', 'download--email-gefragt')

  // Email gate
  const since = Date.now()
  await page.locator('input').first().fill(GUEST_EMAIL)
  await page.getByRole('button').first().click()
  await settle(page, 900)
  await shot(page, 'gallery/gates', 'download--email-link-verschickt')

  let link = null
  for (let i = 0; i < 30 && !link; i++) {
    const list = await fetch(`${MAILPIT}/api/v1/search?query=${encodeURIComponent('to:' + GUEST_EMAIL)}&limit=5`)
      .then((r) => r.json())
      .catch(() => null)
    const message = list?.messages?.find((m) => new Date(m.Created).getTime() > since - 60_000)
    if (message) {
      const body = await fetch(`${MAILPIT}/api/v1/message/${message.ID}`).then((r) => r.json())
      const match = (body.Text + ' ' + body.HTML).match(/https?:\/\/[^\s"'<]*\/download\/email\/[a-f0-9]+/)
      if (match) link = match[0]
    }
    if (!link) await new Promise((r) => setTimeout(r, 2000))
  }

  if (!link) {
    failures.push('Kein Galerie-Verifikationslink in Mailpit angekommen')
  } else {
    console.log(`  email link: ${link}`)
    await visit(page, link.replace(/^https?:\/\/[^/]+/, PORTAL))
    await shot(page, 'gallery/gates', 'download--bereit')
    await buttonStates(page, 'gallery/gates', 'download-bereit')
  }

  // A second, cold visitor sees the locked screen again.
  const cold = await guest()
  await visit(cold, `${PORTAL}/galerien/${ID}/download`)
  await shot(cold, 'gallery/gates', 'download--fremder-gast-ohne-ansichtsrecht')
  await cold.context().close()
  await page.context().close()
}

console.log(`\nfailures: ${failures.length}`)
failures.forEach((f) => console.log('  ✗ ' + f))
await browser.close()
