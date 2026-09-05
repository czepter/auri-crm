import { chromium } from '@playwright/test'
import { PORTAL, settle, shot, visit } from './lib.mjs'

const CONTACT_EMAIL = 'meier.kowalski@example.test'
const PROJECT = 'ujltadoie78zingqkjhq85fo'
const MAILPIT = 'http://localhost:8025'

const browser = await chromium.launch()
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'de-DE', deviceScaleFactor: 2 })).newPage()

const since = Date.now()
await visit(page, `${PORTAL}/anmelden`)
await page.getByLabel('E-Mail-Adresse').fill(CONTACT_EMAIL)
await page.getByRole('button', { name: 'Link anfordern' }).click()
await settle(page, 900)

let link = null
for (let i = 0; i < 30 && !link; i++) {
  const list = await fetch(`${MAILPIT}/api/v1/search?query=${encodeURIComponent('to:' + CONTACT_EMAIL)}&limit=5`)
    .then((r) => r.json())
    .catch(() => null)
  const message = list?.messages?.find((m) => new Date(m.Created).getTime() > since - 60_000)
  if (message) {
    const body = await fetch(`${MAILPIT}/api/v1/message/${message.ID}`).then((r) => r.json())
    const match = (body.Text + ' ' + body.HTML).match(/https?:\/\/[^\s"'<]*\/zugang\/[a-f0-9]+/)
    if (match) link = match[0]
  }
  if (!link) await new Promise((r) => setTimeout(r, 2000))
}

await visit(page, link.replace(/^https?:\/\/[^/]+/, PORTAL))
await visit(page, `${PORTAL}/projekte/${PROJECT}/zahlungen`)
await shot(page, 'portal', process.argv[2] ?? 'projekt-zahlungen--ohne-bankverbindung')
console.log('Überweisen-Block sichtbar:', await page.getByText('Überweisen').count())
console.log('IBAN-Label sichtbar:', await page.getByText('IBAN').count())

await browser.close()
