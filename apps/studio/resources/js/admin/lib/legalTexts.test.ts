import { describe, expect, it } from 'vitest'
import { missingLegalTexts } from './legalTexts'

const none = { url: '', family: '' }

describe('Rechtstexte vor dem ersten Versand', () => {
    it('vermisst die Widerrufsbelehrung, solange keine Quelle steht', () => {
        expect(missingLegalTexts({ widerruf: none })).toEqual(['widerruf'])
    })

    it('gibt sich mit einer Adresse zufrieden', () => {
        expect(missingLegalTexts({ widerruf: { url: 'https://example.test/widerruf', family: '' } })).toEqual([])
    })

    it('gibt sich auch mit einer Vorlage zufrieden', () => {
        expect(missingLegalTexts({ widerruf: { url: '', family: 'fam-widerruf-0000000000' } })).toEqual([])
    })

    it('verlangt weder Impressum noch AGB', () => {
        expect(missingLegalTexts({ widerruf: { url: '', family: 'f' }, impressum: none, agb: none })).toEqual([])
    })
})
