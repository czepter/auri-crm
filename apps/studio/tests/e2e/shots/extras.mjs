import { chromium, request } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { ADMIN, buttonStates, settle, shot, visit } from './lib.mjs'

const MAILPIT = 'http://localhost:8025'
const SECTION = 'admin/extras'
const failed = []
const REPO = '/Users/christian/www/client-management-portal'

function tinker(php) {
  return execFileSync(
    'docker',
    ['compose', 'exec', '-T', '-e', 'APP_SURFACE=admin', 'php', 'php', 'artisan', 'tinker', '--execute', php],
    { cwd: REPO, encoding: 'utf8' },
  ).trim()
}

async function linkFrom(query, pattern) {
  const list = await fetch(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(query)}&limit=10`).then((r) => r.json())
  for (const message of list.messages ?? []) {
    const body = await fetch(`${MAILPIT}/api/v1/message/${message.ID}`).then((r) => r.json())
    const match = (body.Text + ' ' + body.HTML).match(pattern)
    if (match) return match[0]
  }
  return null
}

const browser = await chromium.launch()
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'de-DE', deviceScaleFactor: 2 })
const page = await context.newPage()

const ONLY = process.env.ONLY?.split(',')

async function step(name, fn) {
  if (ONLY && !ONLY.includes(name)) return
  console.log(`▸ ${name}`)
  try {
    await fn()
  } catch (e) {
    failed.push(`${name}: ${e.message.split('\n')[0]}`)
    console.log(`  ✗ ${e.message.split('\n')[0]}`)
  }
}

// ── Einladung annehmen ──────────────────────────────────────────────────────
await step('einladung', async () => {
  const link = await linkFrom('to:neue.kollegin@nordlicht.test', /https?:\/\/[^\s"'<]*\/einladung\/[A-Za-z0-9]+/)
  if (!link) throw new Error('kein Einladungslink in Mailpit')
  await visit(page, link.replace(/^https?:\/\/[^/]+/, ADMIN))
  await shot(page, SECTION, 'einladung--annehmen')
  await buttonStates(page, SECTION, 'einladung-annehmen')
})

// ── Passwort zurücksetzen ───────────────────────────────────────────────────
await step('passwort-neu', async () => {
  const link = await linkFrom('to:christian@nordlicht.test', /https?:\/\/[^\s"'<]*\/passwort\/neu\/[A-Za-z0-9]+/)
  if (!link) throw new Error('kein Reset-Link in Mailpit')
  await visit(page, link.replace(/^https?:\/\/[^/]+/, ADMIN))
  await shot(page, SECTION, 'passwort-neu--formular')
  await buttonStates(page, SECTION, 'passwort-neu')
})

// ── Ersteinrichtung des Studios (der Wizard aus Sprint 10) ──────────────────
await step('onboarding', async () => {
  tinker("App\\Settings\\Models\\StudioSettings::query()->update(['onboarding_completed_at' => null]); Cache::store('redis')->flush(); echo 'reset';")
  await visit(page, `${ADMIN}/login`)
  await page.getByRole('button', { name: 'Dev-Login' }).click()
  await settle(page, 900)
  await visit(page, `${ADMIN}/einrichtung/studio`)

  for (let i = 0; i < 8; i++) {
    await shot(page, SECTION, `onboarding--schritt-${String(i + 1).padStart(2, '0')}`)
    await buttonStates(page, SECTION, `onboarding-schritt-${i + 1}`)
    const next = page.getByRole('button', { name: /Weiter|Fertig|Abschließen|Los/ }).first()
    if (!(await next.count()) || (await next.isDisabled().catch(() => true))) break
    await next.click()
    await settle(page, 700)
    if (!page.url().includes('/einrichtung')) break
  }
  await shot(page, SECTION, 'onboarding--ende')
  tinker("App\\Settings\\Models\\StudioSettings::query()->update(['onboarding_completed_at' => now()]); Cache::store('redis')->flush(); echo 'restored';")
})

// ── Downloads und Exporte: keine Screens, aber sie müssen liefern ───────────
await step('downloads', async () => {
  await visit(page, `${ADMIN}/login`)
  if (await page.getByRole('button', { name: 'Dev-Login' }).count()) {
    await page.getByRole('button', { name: 'Dev-Login' }).click()
    await settle(page, 900)
  }
  const cookies = await context.cookies()
  const api = await request.newContext({
    baseURL: ADMIN,
    extraHTTPHeaders: { Cookie: cookies.map((c) => `${c.name}=${c.value}`).join('; ') },
  })

  const project = process.env.PROJECT ?? 'ujltadoie78zingqkjhq85fo'
  const gallery = process.env.GALLERY_ID ?? 'd13pdab6v7cb7mo10jfg6n1e'
  const shoot = tinker(
    `echo App\\Crm\\Models\\Shoot::whereHas('project', fn($q) => $q->where('public_id','${project}'))->firstOrFail()->public_id;`,
  ).split('\n').pop().trim()

  const targets = [
    ['lightroom-export', `/galerien/${gallery}/lightroom-export`],
    ['ablauf-pdf', `/shootings/${shoot}/ablauf.pdf`],
    ['ablauf-ics', `/shootings/${shoot}/ablauf.ics`],
    ['dsgvo-export', `/kontakte/${process.env.CONTACT_ID ?? 's12dv5qqupddf6tjdewh800b'}/dsgvo/export`],
    ['horizon', '/horizon'],
  ]

  for (const [name, url] of targets) {
    const res = await api.get(url)
    const size = (await res.body()).length
    const line = `${name}: HTTP ${res.status()} ${res.headers()['content-type'] ?? '?'} ${size} B`
    console.log(`  ${line}`)
    if (res.status() >= 400) failed.push(line)
  }
  await api.dispose()
})

console.log(`\nprobleme: ${failed.length}`)
failed.forEach((f) => console.log('  ✗ ' + f))
await browser.close()
