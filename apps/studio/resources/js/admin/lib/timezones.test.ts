import { describe, expect, it } from 'vitest'
import { searchTimezones, timezoneLabel } from './timezones'

describe('timezoneLabel', () => {
  it('names the zone in the given locale', () => {
    expect(timezoneLabel('Europe/Berlin', 'de_DE')).toMatch(/^Europe\/Berlin · Mitteleuropäische .+ \(UTC\+0[12]:00\)$/)
  })

  it('falls back to the identifier for a zone Intl does not know', () => {
    expect(timezoneLabel('Mars/Olympus', 'de')).toBe('Mars/Olympus')
  })
})

describe('searchTimezones', () => {
  it('matches anywhere in the identifier, case-insensitively', () => {
    expect(searchTimezones('berlin', 'Europe/Berlin')).toEqual(['Europe/Berlin'])
  })

  it('keeps a stored zone Intl does not list', () => {
    expect(searchTimezones('', 'Mars/Olympus')[0]).toBe('Mars/Olympus')
  })

  it('caps the list', () => {
    expect(searchTimezones('', 'Europe/Berlin', 5)).toHaveLength(5)
  })
})
