import { mkdir } from 'node:fs/promises'
import path from 'node:path'

export const OUT = '/Users/christian/Desktop/portal-screens'

export const ADMIN = 'http://localhost:8000'
export const PORTAL = 'http://localhost:8001'
export const API = 'http://localhost:8002'
export const GALLERY = 'http://localhost:4321'

let counter = 0
const log = []

export function report() {
  return log
}

/** Screenshot the whole page under <section>/<NNN>-<name>.png. */
export async function shot(page, section, name, opts = {}) {
  const dir = path.join(OUT, section)
  await mkdir(dir, { recursive: true })
  const n = String(++counter).padStart(3, '0')
  const file = path.join(dir, `${n}-${slug(name)}.png`)
  await page.screenshot({ path: file, fullPage: opts.full ?? true, animations: 'disabled' })
  log.push({ file, section, name, url: page.url() })
  console.log(`  · ${section}/${n}-${slug(name)}.png`)
  return file
}

/** Screenshot one element (button, card, dialog) — used for interaction states. */
export async function shotEl(locator, section, name) {
  const dir = path.join(OUT, section)
  await mkdir(dir, { recursive: true })
  const n = String(++counter).padStart(3, '0')
  const file = path.join(dir, `${n}-${slug(name)}.png`)
  try {
    await locator.screenshot({ path: file, animations: 'disabled' })
    log.push({ file, section, name })
  } catch (e) {
    console.log(`  ! skip ${name}: ${e.message.split('\n')[0]}`)
  }
}

export function slug(s) {
  return s
    .toLowerCase()
    .replace(/[äöüß]/g, (c) => ({ ä: 'ae', ö: 'oe', ü: 'ue', ß: 'ss' })[c])
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 60)
}

/** Inertia pages hydrate after load; give Vue a beat and let fonts settle. */
export async function settle(page, ms = 450) {
  await page.waitForLoadState('networkidle').catch(() => {})
  await page.waitForTimeout(ms)
}

export async function visit(page, url) {
  const res = await page.goto(url, { waitUntil: 'domcontentloaded' })
  await settle(page)
  return res?.status() ?? 0
}

/**
 * Anything that writes, sends, deletes or signs out. The sweep clicks every
 * other visible control to find dialogs; without this list it would delete the
 * demo data it is meant to be photographing.
 */
const DESTRUCTIVE =
  /löschen|entfernen|abmelden|logout|versenden|senden|verschicken|zurücksetzen|erneuern|widerruf|rotieren|freigeben|archivieren|stornieren|absagen|beenden|bezahlt|fällig|erledigt|annehmen|unterschreiben|speichern|anlegen|erstellen|hinzufügen|übernehmen|einladen|exportieren|herunterladen|download|abschließen|starten|jetzt zahlen/i

/** Buttons that navigate away rather than opening something in place. */
function isNav(el) {
  return el.tagName === 'A' && el.getAttribute('href') && !el.getAttribute('href').startsWith('#')
}

/**
 * Default / hover / focus for every visible control on the screen, as clipped
 * element shots — a full page per state would be 40 near-identical PNGs.
 */
export async function buttonStates(page, section, screen, limit = 14) {
  const buttons = page.locator('button:visible, a[class*="btn"]:visible, [role="button"]:visible')
  const count = Math.min(await buttons.count(), limit)

  for (let i = 0; i < count; i++) {
    const el = buttons.nth(i)
    let label = ''
    try {
      label = ((await el.innerText()) || (await el.getAttribute('aria-label')) || `btn${i}`).trim()
    } catch {
      continue
    }
    if (!label) label = `btn${i}`
    const base = `${screen}--btn-${i}-${label}`

    const disabled = await el.isDisabled().catch(() => false)
    await shotEl(el, `${section}/states`, `${base}--${disabled ? 'disabled' : 'default'}`)
    if (disabled) continue

    await el.hover({ trial: false }).catch(() => {})
    await page.waitForTimeout(120)
    await shotEl(el, `${section}/states`, `${base}--hover`)

    await el.focus().catch(() => {})
    await page.waitForTimeout(120)
    await shotEl(el, `${section}/states`, `${base}--focus`)
    await page.mouse.move(0, 0)
  }
}

/**
 * Clicks every non-destructive, non-navigating control and photographs
 * whatever it opened (dialog, popover, expanded panel), then closes it again.
 */
export async function openStates(page, section, screen, url) {
  const buttons = page.locator('button:visible')
  const count = Math.min(await buttons.count(), 18)

  for (let i = 0; i < count; i++) {
    const el = page.locator('button:visible').nth(i)
    let label
    try {
      label = (await el.innerText()).trim()
    } catch {
      continue
    }
    if (!label || DESTRUCTIVE.test(label)) continue
    if (await el.isDisabled().catch(() => true)) continue

    const before = await page.locator('[role="dialog"], [role="menu"], [role="listbox"]').count()
    await el.click({ timeout: 2500 }).catch(() => {})
    await page.waitForTimeout(400)

    const after = await page.locator('[role="dialog"], [role="menu"], [role="listbox"]').count()
    if (after > before) {
      await shot(page, section, `${screen}--open-${label}`, { full: false })
      await page.keyboard.press('Escape')
      await page.waitForTimeout(250)
    }

    // A click may have navigated (an in-page tab, a form submit we misread);
    // get back to a known screen before trying the next control.
    if (page.url() !== url) {
      await visit(page, url)
    }
  }
}
