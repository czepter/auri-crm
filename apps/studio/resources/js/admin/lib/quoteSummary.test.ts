import { describe, expect, it } from 'vitest'
import { grossTotal, topLines } from './quoteSummary'

const line = (title: string, netTotal: number) => ({ title, netTotal })

describe('topLines', () => {
  it('takes the three largest by net total and counts the rest', () => {
    const result = topLines([
      line('Anfahrt', 0),
      line('Reportage', 189000),
      line('Buch', 34200),
      line('Zweitfotograf', 49000),
      line('USB', 4900),
    ])

    expect(result.shown.map((item) => item.title)).toEqual(['Reportage', 'Zweitfotograf', 'Buch'])
    expect(result.rest).toBe(2)
  })

  it('counts no rest when everything fits', () => {
    const result = topLines([line('Reportage', 189000), line('Buch', 34200)])

    expect(result.shown).toHaveLength(2)
    expect(result.rest).toBe(0)
  })

  it('sorts an included line last rather than dropping it', () => {
    const result = topLines([line('Anfahrt', 0), line('Reportage', 189000)], 2)

    expect(result.shown.map((item) => item.title)).toEqual(['Reportage', 'Anfahrt'])
  })

  it('leaves the given array untouched', () => {
    const items = [line('a', 1), line('b', 2)]
    topLines(items)

    expect(items.map((item) => item.title)).toEqual(['a', 'b'])
  })
})

describe('grossTotal', () => {
  it('adds VAT only in standard mode', () => {
    expect(grossTotal({ net: 1000, vatAmount: 190, vatMode: 'standard' })).toBe(1190)
    expect(grossTotal({ net: 1000, vatAmount: 0, vatMode: 'small_business' })).toBe(1000)
  })
})
