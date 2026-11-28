import { describe, expect, it } from 'vitest'
import { navItems, phoneNav } from './nav'

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
        ])
    })

    it('hält die Telefonleiste kurz und den Rest unter Mehr', () => {
        const { bar, more } = phoneNav(
            navItems(['manage_contacts', 'manage_settings', 'view_all_cases'] as Cap[]),
        )

        expect(bar.map((item) => item.key)).toEqual(['uebersicht', 'kalender', 'projekte'])
        expect(more.map((item) => item.key)).toEqual([
            'benachrichtigungen',
            'aufgaben',
            'abwesenheiten',
            'schliesszeiten',
            'kontakte',
            'auswertungen',
        ])
    })

    it('nennt die Termine auf dem Telefon Kalender', () => {
        expect(phoneNav(navItems([])).bar.find((item) => item.key === 'kalender')?.label).toBe('Kalender')
    })

    it('lässt Zeilen ohne Befugnis auch in der Telefonleiste weg', () => {
        expect(phoneNav(navItems([])).bar.map((item) => item.key)).toEqual(['uebersicht', 'kalender', 'projekte'])
    })

    it('lässt Unterlagen ganz weg, wenn keine Art bleibt', () => {
        expect(unterlagen([])).toBeUndefined()
    })

    it('zeigt Schließzeiten unter Kalender nur mit Befugnis für Einstellungen', () => {
        expect(planung([])).toEqual(['kalender', 'abwesenheiten'])
        expect(planung(['manage_settings'] as Cap[])).toEqual(['kalender', 'abwesenheiten', 'schliesszeiten'])
    })

    it('zeigt Finanzen nur mit Befugnis und auf dem Telefon unter Mehr', () => {
        const finanzen = (can: Cap[]) => navItems(can).find((item) => item.key === 'finanzen')?.children?.map((c) => c.key)

        expect(finanzen([])).toBeUndefined()
        expect(finanzen(['manage_finances'] as Cap[])).toEqual(['ausgaben', 'anlagen', 'euer'])
        expect(phoneNav(navItems(['manage_finances'] as Cap[])).more.map((item) => item.key)).toEqual(['benachrichtigungen', 'aufgaben', 'abwesenheiten', 'ausgaben', 'anlagen', 'euer'])
    })

    it('führt Aufgaben ohne Befugnis, weil der Screen keine verlangt', () => {
        expect(keys([])).toContain('aufgaben')
    })
})
