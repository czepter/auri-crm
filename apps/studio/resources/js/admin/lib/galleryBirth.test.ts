import { describe, expect, it } from 'vitest'
import { birthValues } from './galleryBirth'

const STUDIO = { downloads: true, lists: true, comments: true }

describe('birthValues', () => {
  it('falls back to the studio default when nothing else says anything', () => {
    expect(birthValues(null, null, STUDIO)).toEqual(STUDIO)
  })

  it('lets the contact override the studio', () => {
    const project = { galleryDefaults: { downloads: false, lists: null, comments: null } }

    expect(birthValues(null, project, STUDIO)).toEqual({ downloads: false, lists: true, comments: true })
  })

  // Die Reihenfolge, die entschieden wurde: die Vorlage steht dem Fall näher
  // als der Kontakt und gewinnt gegen ihn.
  it('lets the preset override the contact', () => {
    const project = { galleryDefaults: { downloads: false, lists: null, comments: null } }
    const preset = { downloadsEnabled: true, listsEnabled: null, commentsEnabled: false }

    expect(birthValues(preset, project, STUDIO)).toEqual({ downloads: true, lists: true, comments: false })
  })

  it('leaves a value the preset does not speak to with the contact', () => {
    const project = { galleryDefaults: { downloads: false, lists: false, comments: null } }
    const preset = { downloadsEnabled: null, listsEnabled: null, commentsEnabled: null }

    expect(birthValues(preset, project, STUDIO)).toEqual({ downloads: false, lists: false, comments: true })
  })
})
