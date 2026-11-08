import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { helpPanel, linkZiel } from './helpPanel'

const klick = { defaultPrevented: false, button: 0, metaKey: false, ctrlKey: false, shiftKey: false, altKey: false }

beforeEach(() => {
  localStorage.clear()
  helpPanel.zuruecksetzen()
})

describe('helpPanel', () => {
  it('startet geschlossen', () => {
    expect(helpPanel.offen.value).toBe(false)
  })

  it('bleibt offen, nachdem einmal geöffnet wurde', () => {
    helpPanel.umschalten()
    helpPanel.zuruecksetzen()

    expect(helpPanel.offen.value).toBe(true)
  })

  it('vergisst den überschriebenen Artikel bei Navigation', () => {
    // Sonst klebt der in der Palette gewählte Artikel über allen folgenden
    // Screens.
    helpPanel.oeffnen('mcp-claude-verbinden')
    expect(helpPanel.ueberschrieben.value).toBe('mcp-claude-verbinden')

    helpPanel.navigiert()

    expect(helpPanel.ueberschrieben.value).toBeNull()
  })

})

describe('ohne Speicher', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.resetModules()
  })

  // Ein Browser mit blockierten Site-Daten wirft schon beim Getter. Page.vue
  // importiert dieses Modul und steht auf jedem Adminscreen — ein Wurf beim
  // Import nimmt das ganze Admin-Bundle mit.
  it('lädt trotzdem und startet geschlossen', async () => {
    vi.stubGlobal('localStorage', {
      getItem() {
        throw new DOMException('blocked', 'SecurityError')
      },
      setItem() {
        throw new DOMException('blocked', 'SecurityError')
      },
    })

    vi.resetModules()
    const modul = await import('./helpPanel')

    expect(modul.helpPanel.offen.value).toBe(false)
    expect(() => modul.helpPanel.oeffnen()).not.toThrow()
    expect(modul.helpPanel.offen.value).toBe(true)
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
