import { chromium } from '@playwright/test'
import { ADMIN, settle, shot, visit } from './lib.mjs'

const PROJECT = process.env.PROJECT ?? 'ujltadoie78zingqkjhq85fo'
const SECTION = 'admin/flows'

const done = []
const failed = []

const browser = await chromium.launch()
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'de-DE', deviceScaleFactor: 2 })
const page = await context.newPage()
page.on('response', (r) => {
  if (r.status() >= 500) failed.push(`HTTP ${r.status()} ${r.request().method()} ${r.url()}`)
})

/** One transition. A broken selector fails its own flow and nothing else. */
const ONLY = process.env.ONLY?.split(',')

async function flow(name, fn) {
  if (ONLY && !ONLY.includes(name)) return
  console.log(`▸ ${name}`)
  try {
    await fn()
    done.push(name)
  } catch (e) {
    const message = e.message.split('\n')[0]
    failed.push(`${name}: ${message}`)
    console.log(`  ✗ ${message}`)
    await shot(page, `${SECTION}/fehler`, `${name}--abbruch`).catch(() => {})
  }
}

await visit(page, `${ADMIN}/login`)
await page.getByRole('button', { name: 'Dev-Login' }).click()
await settle(page, 900)

// ── A · Anfrage über das Board verschieben (vorwärts und zurück) ────────────
await flow('board-drag', async () => {
  await visit(page, `${ADMIN}/anfragen`)
  await shot(page, SECTION, 'board--vorher')

  const card = page.locator('[draggable="true"]').first()
  const columns = page.locator('.col, [class*="column"]')
  await shot(page, SECTION, 'board--karte-vor-dem-zug', { full: false })

  const target = columns.nth(1)
  await card.hover()
  await page.mouse.down()
  await target.hover()
  await page.waitForTimeout(300)
  await shot(page, SECTION, 'board--spalte-als-drop-ziel', { full: false })
  await page.mouse.up()
  await settle(page, 700)
  await shot(page, SECTION, 'board--nach-dem-zug-vorwaerts')

  // Native HTML5 drag needs Playwright's own dragTo when a plain mouse
  // sequence does not raise dragstart.
  const moved = page.locator('[draggable="true"]').first()
  await moved.dragTo(page.locator('.col, [class*="column"]').first())
  await settle(page, 700)
  await shot(page, SECTION, 'board--nach-dem-zug-zurueck')
})

// ── B · Anfrage erfassen ────────────────────────────────────────────────────
await flow('anfrage-erfassen', async () => {
  await visit(page, `${ADMIN}/anfragen/neu`)
  await shot(page, SECTION, 'anfrage--leeres-formular')

  await page.getByRole('searchbox').fill('Viola')
  await settle(page, 700)
  await shot(page, SECTION, 'anfrage--kontaktsuche-treffer')

  await page.getByRole('searchbox').fill('Viola Neumann')
  await settle(page, 700)
  await page.getByRole('button', { name: /als neuen Kontakt anlegen/ }).click()
  await settle(page)
  await shot(page, SECTION, 'anfrage--neuer-kontakt-gewaehlt')

  await page.getByLabel('Partner/in').fill('Chris Neumann')
  await page.getByLabel('E-Mail').fill('viola.neumann@example.test')
  await page.getByLabel('Telefon').fill('0171 2223344')
  await page.getByLabel('Haupttermin').fill('2026-06-14')
  await settle(page, 800)
  await shot(page, SECTION, 'anfrage--termin-belegt-warnung')

  await page.getByLabel('Anfragetext').fill('Freie Trauung, ca. 80 Gäste, Wunsch nach After-Wedding.').catch(() => {})
  await shot(page, SECTION, 'anfrage--ausgefuellt')

  await page.getByRole('button', { name: 'Anfrage anlegen' }).click()
  await settle(page, 900)
  await shot(page, SECTION, 'anfrage--auf-dem-board-gelandet')
})

// ── C · Angebot: Presets, Positionen, Rabatt, Versand ───────────────────────
await flow('angebot', async () => {
  await visit(page, `${ADMIN}/auftraege/${PROJECT}/angebote`)
  await shot(page, SECTION, 'angebot--bestand')
  await page.getByRole('button', { name: 'Neues Angebot' }).click()
  await settle(page)
  await shot(page, SECTION, 'angebot--neu-leer')

  // T-?/ValidUntilField: the preset chips beside "Gültig bis".
  for (const preset of ['1 Wo', '2 Wo', '1 Mon', '3 Mon']) {
    const chip = page.getByRole('button', { name: preset, exact: true })
    if (await chip.count()) {
      await chip.first().click()
      await page.waitForTimeout(300)
      await shot(page, SECTION, `angebot--gueltig-bis-${preset.replace(' ', '')}`, { full: false })
    }
  }

  const addItem = page.getByRole('button', { name: 'Position hinzufügen' })
  if (await addItem.count()) {
    await addItem.first().click()
    await settle(page, 400)
    await shot(page, SECTION, 'angebot--position-hinzugefuegt')
  }

  await shot(page, SECTION, 'angebot--fertig-ausgefuellt')
})

// ── D · Projekt: Aufgabe, Notiz, Zahlungsplan ───────────────────────────────
await flow('aufgabe', async () => {
  await visit(page, `${ADMIN}/auftraege/${PROJECT}`)
  await page.getByRole('button', { name: 'Aufgabe', exact: true }).first().click()
  await settle(page, 400)
  await shot(page, SECTION, 'aufgabe--formular-offen', { full: false })
  await page.getByLabel('Aufgabe').last().fill('Ablauf mit dem Trauzeugen abstimmen')
  await shot(page, SECTION, 'aufgabe--ausgefuellt', { full: false })
})

await flow('notiz', async () => {
  await visit(page, `${ADMIN}/auftraege/${PROJECT}`)
  const note = page.getByPlaceholder('Notiz schreiben …')
  await note.fill('Brautpaar wünscht keine Gruppenfotos vor der Trauung.')
  await shot(page, SECTION, 'notiz--getippt')
  await page.getByRole('button', { name: 'Speichern' }).first().click()
  await settle(page, 700)
  await shot(page, SECTION, 'notiz--gespeichert')
})

await flow('zahlungsplan', async () => {
  await visit(page, `${ADMIN}/auftraege/${PROJECT}`)
  await shot(page, SECTION, 'zahlungen--bestand')
  const markPaid = page.getByRole('button', { name: 'Als bezahlt markieren' }).first()
  await markPaid.click()
  await settle(page, 400)
  await shot(page, SECTION, 'zahlungen--rate-als-bezahlt-dialog', { full: false })
  await page.keyboard.press('Escape')
})

// ── E · Shootings: anlegen, Status, Crew, Ablauf ────────────────────────────
await flow('shootings', async () => {
  await visit(page, `${ADMIN}/auftraege/${PROJECT}/shootings`)
  await shot(page, SECTION, 'shootings--bestand')

  const add = page.getByRole('button', { name: 'Shooting anlegen' })
  if (await add.count()) {
    await add.first().click()
    await settle(page, 400)
    await shot(page, SECTION, 'shootings--formular-offen', { full: false })
    await page.keyboard.press('Escape')
  }

  const advance = page.getByRole('button', { name: /Status weiter/ }).first()
  if (await advance.count()) {
    await advance.click()
    await settle(page, 500)
    await shot(page, SECTION, 'shootings--status-weiter', { full: false })
  }

  const timeline = page.getByRole('button', { name: 'Ablauf bearbeiten' }).first()
  if (await timeline.count()) {
    await timeline.click()
    await settle(page, 500)
    await shot(page, SECTION, 'shootings--ablauf-bearbeiten')
  }
})

// ── F · Vertrag erzeugen ────────────────────────────────────────────────────
await flow('vertrag', async () => {
  await visit(page, `${ADMIN}/auftraege/${PROJECT}/vertrag`)
  await shot(page, SECTION, 'vertrag--vorher')
  const create = page.getByRole('button').filter({ hasText: /Vertrag (erzeugen|erstellen|anlegen)/ }).first()
  if (await create.count()) {
    await create.click()
    await settle(page, 1500)
    await shot(page, SECTION, 'vertrag--wird-erstellt')
    await page.waitForTimeout(9000)
    await visit(page, `${ADMIN}/auftraege/${PROJECT}/vertrag`)
    await shot(page, SECTION, 'vertrag--fertig')
  }
})

// ── G · Team einladen ───────────────────────────────────────────────────────
await flow('team-einladen', async () => {
  await visit(page, `${ADMIN}/team`)
  await shot(page, SECTION, 'team--vorher')
  await page.getByRole('button', { name: 'Einladen' }).first().click()
  await settle(page, 500)
  await shot(page, SECTION, 'team--einladungsformular-offen')
  await page.getByLabel('Name').fill('Lena Wolters')
  await page.getByLabel('E-Mail').fill('neue.kollegin@nordlicht.test')
  await shot(page, SECTION, 'team--einladung-getippt')
  await page.getByRole('button', { name: 'Einladung senden' }).click()
  await settle(page, 900)
  await shot(page, SECTION, 'team--eingeladen')
})

// ── H · Paket anlegen ───────────────────────────────────────────────────────
await flow('paket-anlegen', async () => {
  await visit(page, `${ADMIN}/pakete/neu`)
  await shot(page, SECTION, 'paket--leeres-formular')
  await page.getByLabel('Name').first().fill('Elopement Nordsee')
  await page.getByRole('button', { name: 'Leistung hinzufügen' }).first().click()
  await settle(page, 400)
  await shot(page, SECTION, 'paket--leistung-hinzugefuegt')
})

// ── I · Abwesenheit eintragen ───────────────────────────────────────────────
await flow('abwesenheit', async () => {
  await visit(page, `${ADMIN}/abwesenheiten`)
  await shot(page, SECTION, 'abwesenheit--vorher')
  await page.getByRole('button', { name: 'Abwesenheit eintragen' }).first().click()
  await settle(page, 500)
  await shot(page, SECTION, 'abwesenheit--formular-offen')
  await page.locator('input[type=date]').first().fill('2026-09-01')
  await page.locator('input[type=date]').nth(1).fill('2026-09-07')
  await shot(page, SECTION, 'abwesenheit--ausgefuellt')
  await page.getByRole('button', { name: /Speichern|Abwesenheit eintragen/ }).last().click()
  await settle(page, 900)
  await shot(page, SECTION, 'abwesenheit--eingetragen')
})

// ── J · Kontakt: bearbeiten, DSGVO ──────────────────────────────────────────
await flow('kontakt', async () => {
  await visit(page, `${ADMIN}/kontakte`)
  const first = await page.locator('a[href^="/kontakte/"]').first().getAttribute('href')
  await visit(page, ADMIN + first)
  await shot(page, SECTION, 'kontakt--detail')
  await page.getByLabel('Telefon').first().fill('040 123456')
  await shot(page, SECTION, 'kontakt--geaendert')
  const revoke = page.getByRole('button', { name: 'Portal-Zugang widerrufen' })
  if (await revoke.count()) {
    await revoke.first().click()
    await settle(page, 400)
    await shot(page, SECTION, 'kontakt--portalzugang-widerruf-bestaetigung', { full: false })
    await page.keyboard.press('Escape')
  }
  const erase = page.getByRole('button', { name: 'Kontakt löschen' })
  if (await erase.count()) {
    await erase.first().click()
    await settle(page, 400)
    await shot(page, SECTION, 'kontakt--dsgvo-loeschen-bestaetigung', { full: false })
    await page.keyboard.press('Escape')
  }
})

// ── K · Dokumentvorlage aus einem Starter ───────────────────────────────────
await flow('dokumentvorlage', async () => {
  await visit(page, `${ADMIN}/dokumentvorlagen/neu`)
  await shot(page, SECTION, 'vorlage--starterauswahl')
  const starter = page.locator('button, [role=button]').filter({ hasText: /Fotografievertrag|Leere Vorlage/ }).first()
  await starter.click()
  await settle(page, 800)
  await shot(page, SECTION, 'vorlage--starter-uebernommen')
})

// ── L · MFA einrichten (eigener Account, eigener Kontext) ───────────────────
await flow('mfa-enroll', async () => {
  const fresh = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'de-DE', deviceScaleFactor: 2 })
  const p = await fresh.newPage()
  await visit(p, `${ADMIN}/login`)
  await p.getByLabel('E-Mail').fill('e2e-basic@studio.test')
  await p.getByLabel('Passwort').fill('correct-horse')
  await p.getByRole('button', { name: 'Anmelden' }).click()
  await settle(p, 900)
  await visit(p, `${ADMIN}/auth/mfa/enroll`)
  await shot(p, SECTION, 'mfa--qr-code')
  await fresh.close()
})

// ── M · Projekt als gebucht markieren ───────────────────────────────────────
await flow('projekt-buchen', async () => {
  await visit(page, `${ADMIN}/anfragen`)
  const href = await page.locator('a[href^="/auftraege/"]').first().getAttribute('href')
  await visit(page, ADMIN + href)
  const book = page.getByRole('button', { name: 'Als gebucht markieren' })
  if (await book.count()) {
    await shot(page, SECTION, 'buchung--vorher')
    await book.first().click()
    await settle(page, 400)
    await shot(page, SECTION, 'buchung--bestaetigung', { full: false })
    await page.keyboard.press('Escape')
  }
})

console.log(`\nfertig: ${done.length}  ·  problematisch: ${failed.length}`)
failed.forEach((f) => console.log('  ✗ ' + f))
await browser.close()
