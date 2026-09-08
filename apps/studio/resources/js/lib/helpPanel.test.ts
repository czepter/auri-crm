import { beforeEach, describe, expect, it } from 'vitest'
import { helpPanel, linkZiel } from './helpPanel'

const klick = { defaultPrevented: false, button: 0, metaKey: false, ctrlKey: false, shiftKey: false, altKey: false }

beforeEach(() => {
  localStorage.clear()
  helpPanel.zuruecksetzen()
})

describe('helpPanel', () => {
  it('startet offen, solange niemand zugeklappt hat', () => {
    expect(helpPanel.offen.value).toBe(true)
  })

  it('bleibt zu, nachdem einmal zugeklappt wurde', () => {
    helpPanel.schliessen()
    helpPanel.zuruecksetzen()

    expect(helpPanel.offen.value).toBe(false)
  })

  it('vergisst den überschriebenen Artikel bei Navigation', () => {
    // Sonst klebt der in der Palette gewählte Artikel über allen folgenden
    // Screens.
    helpPanel.oeffnen('mcp-claude-verbinden')
    expect(helpPanel.ueberschrieben.value).toBe('mcp-claude-verbinden')

    helpPanel.navigiert()

    expect(helpPanel.ueberschrieben.value).toBeNull()
  })

  it('startet offen, solange kein Wert gespeichert ist, sonst nach dem gespeicherten Wert', () => {
    // Ein Key statt zwei: nur ein bewusster Klick auf oeffnen()/schliessen()
    // schreibt `help.open`. Eine unsichtbare Spalte schreibt nie etwas und
    // kann so die Erstöffnung nicht verbrauchen.
    expect(localStorage.getItem('help.open')).toBeNull()
    helpPanel.zuruecksetzen()
    expect(helpPanel.offen.value).toBe(true)

    localStorage.setItem('help.open', '0')
    helpPanel.zuruecksetzen()

    expect(helpPanel.offen.value).toBe(false)
  })
})

describe('linkZiel', () => {
  it.each(['metaKey', 'ctrlKey', 'shiftKey', 'altKey'] as const)('lässt den Klick in Ruhe, wenn %s gedrückt ist', (taste) => {
    expect(linkZiel({ ...klick, [taste]: true }, '/hilfe/foo')).toBeNull()
  })

  it('lässt den Klick in Ruhe bei einem nicht-primären Maustaste', () => {
    expect(linkZiel({ ...klick, button: 1 }, '/hilfe/foo')).toBeNull()
  })

  it('lässt den Klick in Ruhe, wenn schon defaultPrevented ist', () => {
    expect(linkZiel({ ...klick, defaultPrevented: true }, '/hilfe/foo')).toBeNull()
  })

  it('lässt externe Links in Ruhe', () => {
    expect(linkZiel(klick, 'https://example.com')).toBeNull()
  })

  it('lässt Klicks ohne href in Ruhe', () => {
    expect(linkZiel(klick, null)).toBeNull()
  })

  it('erkennt einen Hilfeartikel', () => {
    expect(linkZiel(klick, '/hilfe/foo')).toBe('artikel')
  })

  it('erkennt eine normale interne Seite', () => {
    expect(linkZiel(klick, '/projekte')).toBe('seite')
  })
})
