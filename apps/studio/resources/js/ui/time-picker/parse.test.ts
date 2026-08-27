import { describe, expect, it } from 'vitest'
import { toClock, toTime } from './parse'

describe('toTime', () => {
  it('nimmt beide Schreibweisen der API', () => {
    expect(toClock(toTime('23:00'))).toBe('23:00')
    expect(toClock(toTime('08:30:00'))).toBe('08:30')
  })

  it('gibt auf, statt zu raten', () => {
    expect(toTime('')).toBeUndefined()
    expect(toTime(null)).toBeUndefined()
    expect(toTime('24:00')).toBeUndefined()
    expect(toTime('9')).toBeUndefined()
  })
})

it('toClock füllt auf', () => {
  expect(toClock({ hour: 9, minute: 5 })).toBe('09:05')
  expect(toClock(undefined)).toBe('')
})
