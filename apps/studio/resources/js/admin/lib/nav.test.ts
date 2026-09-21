import { describe, expect, it } from 'vitest'
import { navItems } from './nav'

type Cap = App.Enums.Capability

const keys = (can: Cap[]) =>
    navItems(can).flatMap((item) => (item.key ? [item.key] : []))

const unterlagen = (can: Cap[]) =>
    navItems(can).find((item) => item.key === 'unterlagen')

const planung = (can: Cap[]) =>
    navItems(can).find((item) => item.key === 'planung')?.children?.map((c) => c.key)

describe('Sidebar-Navigation', () => {
    it('lässt Zeilen ohne Befugnis weg', () => {
        expect(keys([])).not.toContain('team')
        expect(keys(['manage_staff'] as Cap[])).toContain('team')
    })

    it('zeigt Unterlagen nur mit den Arten, die die Rolle führen darf', () => {
        expect(unterlagen(['manage_quotes'] as Cap[])?.children?.map((c) => c.key)).toEqual([
            'angebote',
        ])
        expect(unterlagen(['manage_contracts'] as Cap[])?.children?.map((c) => c.key)).toEqual([
            'vertraege',
            'rechnungen',
            'gutschriften',
        ])
    })

    it('lässt Unterlagen ganz weg, wenn keine Art bleibt', () => {
        expect(unterlagen([])).toBeUndefined()
    })

    it('zeigt Schließzeiten unter Kalender nur mit Befugnis für Einstellungen', () => {
        expect(planung([])).toEqual(['kalender', 'abwesenheiten'])
        expect(planung(['manage_settings'] as Cap[])).toEqual(['kalender', 'abwesenheiten', 'schliesszeiten'])
    })

    it('führt Aufgaben ohne Befugnis, weil der Screen keine verlangt', () => {
        expect(keys([])).toContain('aufgaben')
    })
})
