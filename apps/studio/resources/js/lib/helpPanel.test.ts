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

  it('verbraucht die Erstöffnung erst, wenn die Spalte sichtbar war', () => {
    // Unter der Schwelle gibt es keine Spalte zum Zuklappen. Ein schmales
    // Fenster darf die einzige Entdeckungschance nicht verschlucken.
    helpPanel.zuruecksetzen()
    expect(localStorage.getItem('help.seen')).toBeNull()

    helpPanel.gesehen()

    expect(localStorage.getItem('help.seen')).toBe('1')
  })
})
