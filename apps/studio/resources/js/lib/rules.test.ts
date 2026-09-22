import { describe, expect, it } from 'vitest'
import { ruleValid } from './rules'

describe('ruleValid', () => {
  it('prüft die IBAN-Prüfsumme', () => {
    expect(ruleValid('iban', 'DE02 1203 0000 0000 2020 51')).toBe(true)
    expect(ruleValid('iban', 'DE02 1203 0000 0000 2020 15')).toBe(false)
  })

  it('lässt leere Werte durch', () => {
    expect(ruleValid('iban', '')).toBe(true)
  })

  it('kennt BIC, USt-IdNr., Steuernummer und Farbe', () => {
    expect(ruleValid('bic', 'BYLADEM1001')).toBe(true)
    expect(ruleValid('bic', 'BYLA')).toBe(false)
    expect(ruleValid('vatId', 'DE812345678')).toBe(true)
    expect(ruleValid('vatId', 'DE8123')).toBe(false)
    expect(ruleValid('taxNumber', '43/123/45678')).toBe(true)
    expect(ruleValid('taxNumber', '43/12')).toBe(false)
    expect(ruleValid('color', '#2F4858')).toBe(true)
    expect(ruleValid('color', '2F4858')).toBe(false)
    expect(ruleValid('phone', '+49 40 123456-7')).toBe(true)
    expect(ruleValid('phone', 'sadsdfdfs sdfsdfsd')).toBe(false)
    expect(ruleValid('phone', '123')).toBe(false)
  })
})
