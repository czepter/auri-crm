import { describe, expect, it } from 'vitest'
import { filledProfiles, profileHref, profileText, withOpenRow } from './socialProfile'

describe('profileHref', () => {
  it('builds a handle with or without the at sign', () => {
    expect(profileHref('instagram', '@anna.und.ben')).toBe('https://www.instagram.com/anna.und.ben')
    expect(profileHref('instagram', 'anna_und_ben')).toBe('https://www.instagram.com/anna_und_ben')
    expect(profileHref('instagram', 'anna.und.ben')).toBe('https://www.instagram.com/anna.und.ben')
  })

  it('never emits a non-http scheme', () => {
    expect(profileHref('facebook', 'javascript://%0aalert(1)')).toBe('https://javascript://%0aalert(1)')
  })

  it('keeps the at sign on tiktok', () => {
    expect(profileHref('tiktok', 'anna')).toBe('https://www.tiktok.com/@anna')
  })

  it('prepends a scheme to a bare domain', () => {
    expect(profileHref('instagram', 'instagram.com/anna')).toBe('https://instagram.com/anna')
    expect(profileHref('website', 'annaundben.de')).toBe('https://annaundben.de')
  })

  it('leaves a full url alone', () => {
    expect(profileHref('facebook', 'https://facebook.com/anna')).toBe('https://facebook.com/anna')
  })
})

describe('profileText', () => {
  it('shows a handle with an at sign', () => {
    expect(profileText('instagram', 'anna_und_ben')).toBe('@anna_und_ben')
    expect(profileText('tiktok', '@anna')).toBe('@anna')
  })

  it('shows a link without scheme, www and trailing slash', () => {
    expect(profileText('instagram', 'https://www.instagram.com/anna/')).toBe('instagram.com/anna')
    expect(profileText('website', 'annaundben.de')).toBe('annaundben.de')
  })
})

describe('withOpenRow', () => {
  it('opens one row on an empty list', () => {
    expect(withOpenRow([])).toEqual([{ platform: 'instagram', value: '' }])
  })

  it('adds a row after a filled one and keeps its platform', () => {
    expect(withOpenRow([{ platform: 'tiktok', value: 'anna' }])).toEqual([
      { platform: 'tiktok', value: 'anna' },
      { platform: 'tiktok', value: '' },
    ])
  })

  it('does not stack a second open row', () => {
    const rows = [{ platform: 'instagram', value: '@anna' }, { platform: 'instagram', value: ' ' }]

    expect(withOpenRow(rows)).toBe(rows)
  })

  it('stops at ten', () => {
    const rows = Array.from({ length: 10 }, () => ({ platform: 'instagram', value: 'x' }))

    expect(withOpenRow(rows)).toBe(rows)
  })
})

describe('filledProfiles', () => {
  it('drops rows without a value', () => {
    expect(filledProfiles([{ platform: 'instagram', value: '@anna' }, { platform: 'instagram', value: '  ' }])).toEqual([
      { platform: 'instagram', value: '@anna' },
    ])
  })
})
