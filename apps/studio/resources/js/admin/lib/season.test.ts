import { describe, expect, it } from 'vitest'
import { seasonYears, weekMondays } from './season'

describe('weekMondays', () => {
  it('starts on the monday of ISO week 1, even when that falls in december', () => {
    expect(weekMondays(2026)[0]).toBe('2025-12-29')
    expect(weekMondays(2027)[0]).toBe('2027-01-04')
  })

  it('counts 53 weeks in a long ISO year and 52 otherwise', () => {
    expect(weekMondays(2026)).toHaveLength(53)
    expect(weekMondays(2027)).toHaveLength(52)
  })

  it('hands the new-year week to one year only', () => {
    expect(weekMondays(2026).at(-1)).toBe('2026-12-28')
    expect(weekMondays(2027)).not.toContain('2026-12-28')
  })
})

describe('seasonYears', () => {
  it('reaches back to the first shoot and one year past the last', () => {
    expect(seasonYears(['2019-06-15', '2028-05-20'], '2026-09-11')).toEqual({ first: 2019, last: 2029 })
  })

  it('always covers this year and the next', () => {
    expect(seasonYears([], '2026-09-11')).toEqual({ first: 2026, last: 2027 })
  })
})
