import { describe, expect, it } from 'vitest'
import { toGerman, toIso } from './parse'

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

it('toGerman dreht zurück', () => {
  expect(toGerman('2026-06-14')).toBe('14.06.2026')
})
