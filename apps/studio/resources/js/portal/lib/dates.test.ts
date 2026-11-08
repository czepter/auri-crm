import { expect, it } from 'vitest'
import { daysUntil, shortDate } from './dates'

it('prints a German date and keeps the calendar day', () => {
  expect(shortDate('2026-12-26')).toBe('26.12.2026')
  expect(shortDate('2026-09-13T00:00:00Z')).toBe('13.09.2026')
})

it('counts calendar days to a date', () => {
  const now = new Date('2026-11-06T22:30:00')

  expect(daysUntil('2026-11-06', now)).toBe(0)
  expect(daysUntil('2026-11-10', now)).toBe(4)
  expect(daysUntil('2026-11-05', now)).toBe(-1)
})
