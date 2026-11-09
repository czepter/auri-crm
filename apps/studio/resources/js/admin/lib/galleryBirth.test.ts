import { describe, expect, it } from 'vitest'
import { birthValues, expiryOn } from './galleryBirth'

const STUDIO = { downloadRule: 'open' as const, lists: true, comments: true }

describe('birthValues', () => {
  it('falls back to the studio default when nothing else says anything', () => {
    expect(birthValues(null, null, STUDIO)).toEqual(STUDIO)
  })

  it('lets the contact override the studio', () => {
    const project = { galleryDefaults: { downloads: false, lists: null, comments: null } }

    expect(birthValues(null, project, STUDIO)).toEqual({ downloadRule: 'off', lists: true, comments: true })
  })

  // Die Reihenfolge, die entschieden wurde: die Vorlage steht dem Fall näher
  // als der Kontakt und gewinnt gegen ihn.
  it('lets the preset override the contact', () => {
    const project = { galleryDefaults: { downloads: false, lists: null, comments: null } }
    const preset = { downloadRule: null, downloadsEnabled: true, listsEnabled: null, commentsEnabled: false }

    expect(birthValues(preset, project, STUDIO)).toEqual({ downloadRule: 'open', lists: true, comments: false })
  })

  it('lets the preset rule win over the contact and the studio', () => {
    const project = { galleryDefaults: { downloads: false, lists: null, comments: null } }
    const preset = { downloadRule: 'after_payment' as const, downloadsEnabled: true, listsEnabled: null, commentsEnabled: null }

    expect(birthValues(preset, project, STUDIO).downloadRule).toBe('after_payment')
  })

  it('keeps the studio rule when the contact allows downloads, and opens a closed studio', () => {
    const allowed = { galleryDefaults: { downloads: true, lists: null, comments: null } }

    expect(birthValues(null, allowed, { ...STUDIO, downloadRule: 'after_payment' }).downloadRule).toBe('after_payment')
    expect(birthValues(null, allowed, { ...STUDIO, downloadRule: 'off' }).downloadRule).toBe('open')
  })

  it('leaves a value the preset does not speak to with the contact', () => {
    const project = { galleryDefaults: { downloads: false, lists: false, comments: null } }
    const preset = { downloadRule: null, downloadsEnabled: null, listsEnabled: null, commentsEnabled: null }

    expect(birthValues(preset, project, STUDIO)).toEqual({ downloadRule: 'off', lists: false, comments: true })
  })
})

describe('expiryOn', () => {
  const STUDIO_DAYS = { downloads: true, lists: true, comments: true, expiryDays: 30 }

  // toISOString() rechnet nach UTC um: in MESZ ergab der 1. um 00:30 Uhr den
  // Vortag.
  it('stamps a local calendar day, not a UTC one', () => {
    const from = new Date(2026, 6, 1, 0, 30)

    expect(expiryOn(null, STUDIO_DAYS, from)).toBe('2026-07-31')
  })

  it('leaves no date when neither preset nor studio names a span', () => {
    expect(expiryOn(null, { ...STUDIO_DAYS, expiryDays: null }, new Date(2026, 6, 1))).toBe('')
  })

  it('lets a preset override the studio span', () => {
    const preset = { expiryMode: 'days', expiryDays: 2 }

    expect(expiryOn(preset, STUDIO_DAYS, new Date(2026, 6, 1))).toBe('2026-07-03')
  })

  it('lets a preset say never', () => {
    const preset = { expiryMode: 'never', expiryDays: null }

    expect(expiryOn(preset, STUDIO_DAYS, new Date(2026, 6, 1))).toBe('')
  })
})
