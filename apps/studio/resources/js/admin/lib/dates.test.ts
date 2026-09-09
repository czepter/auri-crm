import { describe, expect, it } from 'vitest'
import { dayWithWeekday, daysUntil, isoDate, monthDate, relativeDate, shiftDate } from './dates'

describe('shiftDate', () => {
  it('adds weeks as days', () => {
    expect(shiftDate(new Date(2026, 7, 16), { weeks: 2 })).toBe('2026-08-30')
  })

  it('adds months on the calendar, not in 30-day steps', () => {
    expect(shiftDate(new Date(2026, 7, 16), { months: 3 })).toBe('2026-11-16')
  })

  it('keeps the local day just before midnight, where toISOString() would roll back', () => {
    expect(shiftDate(new Date(2026, 7, 16, 23, 30), { weeks: 1 })).toBe('2026-08-23')
  })

  it('leaves the date alone without a span', () => {
    expect(shiftDate(new Date(2026, 7, 16), {})).toBe(isoDate(new Date(2026, 7, 16)))
  })
})

describe('daysUntil', () => {
  it('counts whole days from today', () => {
    expect(daysUntil('2026-08-30', new Date(2026, 7, 16, 23, 30))).toBe(14)
  })

  it('goes negative for a date that has passed', () => {
    expect(daysUntil('2026-08-10', new Date(2026, 7, 16))).toBe(-6)
  })

  it('is NaN while the field is empty or half typed', () => {
    expect(daysUntil('')).toBeNaN()
    expect(daysUntil('2026-08-')).toBeNaN()
  })
})

describe('relativeDate', () => {
  const today = new Date(2026, 7, 16)

  it('speaks in days close by', () => {
    expect(relativeDate('2026-08-21', 'de', today)).toBe('in 5 Tagen')
  })

  it('switches to weeks, then to months', () => {
    expect(relativeDate('2026-08-30', 'de', today)).toBe('in 2 Wochen')
    expect(relativeDate('2026-11-16', 'de', today)).toBe('in 3 Monaten')
  })

  it('says nothing about an unparsable date', () => {
    expect(relativeDate('', 'de', today)).toBe('')
  })
})

describe('dayWithWeekday', () => {
  it('reads weekday, day and month', () => {
    expect(dayWithWeekday('2026-06-14')).toBe('So., 14.06.')
  })

  it('carries the placeholder the screen asked for when there is no date', () => {
    expect(dayWithWeekday(null)).toBe('—')
    expect(dayWithWeekday(null, null)).toBeNull()
    expect(dayWithWeekday(undefined, '')).toBe('')
  })
})

describe('monthDate', () => {
  it('schreibt Tag, Monat und Jahr aus', () => {
    expect(monthDate('2026-09-08')).toBe('8. Sept. 2026')
  })

  it('bleibt leer ohne Datum', () => {
    expect(monthDate(null)).toBe('')
  })
})
