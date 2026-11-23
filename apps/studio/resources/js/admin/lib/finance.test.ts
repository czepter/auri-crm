import { describe, expect, it } from 'vitest'
import { centsToField, costBasis, defaultMethod, GWG_LIMIT, modeForYear, parseCents, shareKind, splitGross } from './finance'

describe('Geschäftsanteil', () => {
  it('unterscheidet voll, keiner und anteilig', () => {
    expect([100, 0, 50, 1, 99].map(shareKind)).toEqual(['voll', 'keiner', 'anteilig', 'anteilig', 'anteilig'])
  })
})

describe('GWG-Vorauswahl', () => {
  it('rechnet in der Regelbesteuerung vom Netto und als Kleinunternehmer vom Brutto', () => {
    expect(defaultMethod(costBasis('standard', 64_000, 76_160))).toBe('gwg')
    expect(defaultMethod(costBasis('standard', GWG_LIMIT, 95_200))).toBe('gwg')
    expect(defaultMethod(costBasis('standard', 145_000, 172_550))).toBe('linear')
    expect(defaultMethod(costBasis('small_business', 80_000, 95_200))).toBe('linear')
    expect(defaultMethod(costBasis('small_business', 60_000, 71_400))).toBe('gwg')
  })

  it('nimmt für ein Jahr ohne Eintrag den Standard des Studios', () => {
    expect(modeForYear({ '2025': 'small_business' }, 'standard', 2025)).toBe('small_business')
    expect(modeForYear({ '2025': 'small_business' }, 'standard', 2026)).toBe('standard')
  })
})

describe('Netto und USt. aus dem Brutto', () => {
  it('teilt kaufmännisch gerundet und geht ohne Rest auf', () => {
    expect(splitGross(11_900, 1900)).toEqual({ net: 10_000, vat: 1_900 })
    expect(splitGross(8_900, 700)).toEqual({ net: 8_318, vat: 582 })
    expect(splitGross(15_000, 1900)).toEqual({ net: 12_605, vat: 2_395 })
    expect(splitGross(4_900, 0)).toEqual({ net: 4_900, vat: 0 })
  })
})

describe('Betragsfelder', () => {
  it('liest Komma, Punkt und Tausenderpunkt gleich', () => {
    expect(['1.725,50', '1725,50', '1725.50', ' 761,6 ', ''].map(parseCents)).toEqual([172_550, 172_550, 172_550, 76_160, null])
  })

  it('schreibt mit Komma zurück', () => {
    expect(centsToField(172_550)).toBe('1725,50')
  })
})
