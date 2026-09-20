import { describe, expect, it } from 'vitest'
import { requiresRegister } from './legalForm'

describe('Registerpflicht je Rechtsform', () => {
    it('fordert das Registerpaar für eingetragene Rechtsformen', () => {
        expect(requiresRegister('GmbH')).toBe(true)
        expect(requiresRegister('UG (haftungsbeschränkt)')).toBe(true)
    })

    it('fordert es nicht für Einzelunternehmen und GbR', () => {
        expect(requiresRegister('Einzelunternehmen')).toBe(false)
        expect(requiresRegister('GbR')).toBe(false)
    })

    it('behandelt eine fehlende Rechtsform wie eine nicht eingetragene', () => {
        expect(requiresRegister(null)).toBe(false)
    })
})
