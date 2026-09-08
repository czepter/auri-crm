import { describe, expect, it } from 'vitest'
import { mediaUrl, releaseMark, sectionCounts } from './releases'

describe('releaseMark', () => {
  it('markiert die laufende Version', () => {
    expect(releaseMark('0.9.1', '0.9.1')).toEqual({ key: 'releases.installed', class: 'bg-ok-bg text-ok' })
  })

  it('markiert eine höhere als verfügbar', () => {
    expect(releaseMark('0.9.2', '0.9.1')?.key).toBe('releases.available')
  })

  it('lässt ältere Versionen unmarkiert', () => {
    expect(releaseMark('0.9.0', '0.9.1')).toBeNull()
  })

  it('vergleicht die Zahlen, nicht die Zeichen', () => {
    expect(releaseMark('0.10.0', '0.9.1')?.key).toBe('releases.available')
    expect(releaseMark('0.9.1', '0.10.0')).toBeNull()
  })

  // Ein Branch-Build heisst `dev`. Ohne die Ziffernprüfung stünde an jeder
  // der neun Zeilen "verfügbar".
  it('markiert nichts, solange die laufende Version keine Zahl ist', () => {
    expect(releaseMark('0.9.2', 'dev')).toBeNull()
    expect(releaseMark('0.9.2', '')).toBeNull()
  })
})

describe('sectionCounts', () => {
  it('übersetzt die Schlüssel des Dienstes', () => {
    expect(
      sectionCounts([
        { key: 'new', label: 'Neu', count: 22 },
        { key: 'fixed', label: 'Behoben', count: 18 },
      ]),
    ).toEqual([
      { key: 'new', label: 'releases.groupNew', count: 22 },
      { key: 'fixed', label: 'releases.groupFixed', count: 18 },
    ])
  })

  it('lässt leere und unbekannte Gruppen weg', () => {
    expect(sectionCounts([
      { key: 'changed', label: 'Änderungen', count: 0 },
      { key: 'security', label: 'Sicherheit', count: 3 },
    ])).toEqual([])
  })
})

describe('mediaUrl', () => {
  it('hängt den Pfad an den Dienst', () => {
    expect(mediaUrl('https://release-log.example', '/media/0.9.1.png')).toBe(
      'https://release-log.example/media/0.9.1.png',
    )
  })

  it('gibt nichts zurück, solange kein Dienst konfiguriert ist', () => {
    expect(mediaUrl(null, '/media/0.9.1.png')).toBeNull()
    expect(mediaUrl('https://release-log.example', null)).toBeNull()
  })
})
