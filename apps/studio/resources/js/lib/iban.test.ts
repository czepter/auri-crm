import { describe, expect, it } from 'vitest'
import { groupIban, ibanCaret } from './iban'

describe('groupIban', () => {
  it('teilt in Vierergruppen, ohne Leerzeichen am Ende', () => {
    expect(groupIban('de02120300000000202051')).toBe('DE02 1203 0000 0000 2020 51')
    expect(groupIban('DE02 12 03')).toBe('DE02 1203')
    expect(groupIban('DE02')).toBe('DE02')
    expect(groupIban('')).toBe('')
  })
})

describe('ibanCaret', () => {
  it('springt hinter das Leerzeichen, sobald eine neue Gruppe beginnt', () => {
    expect(ibanCaret(0)).toBe(0)
    expect(ibanCaret(4)).toBe(4)
    expect(ibanCaret(5)).toBe(6)
    expect(ibanCaret(9)).toBe(11)
  })
})
