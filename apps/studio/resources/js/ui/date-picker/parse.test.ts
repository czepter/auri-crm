import { describe, expect, it } from 'vitest'
import { toGerman, toIso, withDots } from './parse'

describe('toIso', () => {
  it('nimmt die deutsche Schreibweise, auch einstellig', () => {
    expect(toIso('14.06.2026')).toBe('2026-06-14')
    expect(toIso('4.6.2026')).toBe('2026-06-04')
  })

  it('lässt ISO durch — Playwright und die API tippen so', () => {
    expect(toIso('2026-06-14')).toBe('2026-06-14')
  })

  it('gibt auf, statt zu raten', () => {
    expect(toIso('')).toBeNull()
    expect(toIso('14.06.')).toBeNull()
    expect(toIso('31.02.2026')).toBeNull()
  })
})

describe('withDots', () => {
  it('punktiert reine Ziffern Stück für Stück', () => {
    expect(['1', '16', '161', '16.10', '16.102', '16.10.2026', '16.10.20261'].map(withDots))
      .toEqual(['1', '16', '16.1', '16.10', '16.10.2', '16.10.2026', '16.10.2026'])
    expect(withDots('16102026')).toBe('16.10.2026')
  })

  it('lässt selbst gesetzte Punkte und ISO in Ruhe', () => {
    expect(['16.', '4.6.2026', '16.1.2026', '2026-10-16', ''].map(withDots))
      .toEqual(['16.', '4.6.2026', '16.1.2026', '2026-10-16', ''])
  })
})

it('toGerman dreht zurück', () => {
  expect(toGerman('2026-06-14')).toBe('14.06.2026')
})
