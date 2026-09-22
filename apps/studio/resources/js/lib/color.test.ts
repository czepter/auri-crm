import { describe, expect, it } from 'vitest'
import { hexToHsv, hsvToHex, isHex } from './color'

describe('color', () => {
  it('rechnet Hex und HSV hin und zurück', () => {
    for (const hex of ['#1C6870', '#FF0000', '#00FF00', '#0000FF', '#FFFFFF', '#000000', '#2F4858']) {
      expect(hsvToHex(hexToHsv(hex))).toBe(hex)
    }
  })

  it('kennt Primärfarben', () => {
    expect(hexToHsv('#FF0000')).toEqual({ h: 0, s: 1, v: 1 })
    expect(hexToHsv('#0000FF').h).toBe(240)
  })

  it('erkennt sechsstelliges Hex', () => {
    expect(isHex('#1c6870')).toBe(true)
    expect(isHex('#1c68')).toBe(false)
  })
})
