import { expect, it } from 'vitest'
import { shortDate } from './dates'

it('prints a German date and keeps the calendar day', () => {
  expect(shortDate('2026-12-26')).toBe('26.12.2026')
  expect(shortDate('2026-09-13T00:00:00Z')).toBe('13.09.2026')
})
