import { describe, expect, it } from 'vitest'
import { ROLE_SUGGESTIONS, roleLabel } from './roles'

describe('roleLabel', () => {
    it('writes the known five through the catalogue', () => {
        expect(roleLabel('primary')).toBe('Hauptkontakt')
        expect(roleLabel('payer')).toBe('Zahlt')
    })

    it('leaves a freely written role as it was typed', () => {
        expect(roleLabel('Trauredner')).toBe('Trauredner')
    })

    it('offers the known five as suggestions', () => {
        expect(ROLE_SUGGESTIONS).toContain('Hauptkontakt')
        expect(ROLE_SUGGESTIONS).toContain('Dienstleister')
    })
})
