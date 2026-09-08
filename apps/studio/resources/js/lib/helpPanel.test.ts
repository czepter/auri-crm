import { beforeEach, describe, expect, it } from 'vitest'
import { helpPanel } from './helpPanel'

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
