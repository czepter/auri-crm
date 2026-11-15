import { describe, expect, it } from 'vitest'
import { toPayload, toRow } from './formFields'

const field = (row: Partial<App.Crm.Data.FormInstanceFieldData>): App.Crm.Data.FormInstanceFieldData => ({
  id: 'f',
  label: 'Frage',
  type: 'short_text',
  typeLabel: 'Kurzantwort',
  required: false,
  options: null,
  currentAnswer: null,
  answeredAt: null,
  groupPhotos: null,
  fileFormats: null,
  fileMaxSize: null,
  fileMultiple: false,
  files: [],
  ...row,
})

describe('formFields', () => {
  it('round-trips each options shape through the editor row', () => {
    const select = field({ type: 'select', options: ['Ja', 'Nein'] })
    const file = field({ type: 'file', fileFormats: ['pdf', 'jpg'], fileMaxSize: 5, fileMultiple: true })
    const groups = field({ type: 'group_photos', groupPhotos: { help: 'Kurz', suggestions: ['Eltern'], minutesPerGroup: 3 } })

    expect(toPayload(toRow(select)).options).toEqual(['Ja', 'Nein'])
    expect(toPayload(toRow(file)).options).toEqual({ formats: ['pdf', 'jpg'], maxSize: 5, multiple: true })
    expect(toPayload(toRow(groups)).options).toEqual({ help: 'Kurz', suggestions: ['Eltern'], minutesPerGroup: 3 })
    expect(toPayload(toRow(field({}))).options).toBeNull()
  })

  it('carries the field id through the editor row', () => {
    expect(toPayload(toRow(field({ id: 'abc' }))).id).toBe('abc')
  })
})
