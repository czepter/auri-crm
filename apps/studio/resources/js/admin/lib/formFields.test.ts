import { describe, expect, it } from 'vitest'
import { toPayload, toRow } from './formFields'

const field = (row: Partial<App.Crm.Data.FormInstanceFieldData>): App.Crm.Data.FormInstanceFieldData => ({
  id: 'f',
  label: 'Frage',
  type: 'short_text',
  typeLabel: 'Kurzantwort',
  required: false,
  help: null,
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
    const groups = field({ type: 'group_photos', groupPhotos: { suggestions: ['Eltern'], minutesPerGroup: 3 } })

    expect(toPayload(toRow(select)).options).toEqual(['Ja', 'Nein'])
    expect(toPayload(toRow(file)).options).toEqual({ formats: ['pdf', 'jpg'], maxSize: 5, multiple: true })
    expect(toPayload(toRow(groups)).options).toEqual({ suggestions: ['Eltern'], minutesPerGroup: 3 })
    expect(toPayload(toRow(field({}))).options).toBeNull()
  })

  it('round-trips the help text of any question type', () => {
    const text = field({ type: 'text', help: 'Gemeint ist die Zeit vor der Trauung.' })
    const groups = field({ type: 'group_photos', help: 'Wählt die Gruppen.', groupPhotos: { suggestions: [], minutesPerGroup: 2 } })

    expect(toRow(text).helpText).toBe('Gemeint ist die Zeit vor der Trauung.')
    expect(toPayload(toRow(text)).help).toBe('Gemeint ist die Zeit vor der Trauung.')
    expect(toPayload(toRow(groups)).help).toBe('Wählt die Gruppen.')
    expect(toPayload({ ...toRow(text), helpText: '  ' }).help).toBeNull()
    expect(toPayload(toRow(field({}))).help).toBeNull()
  })

  it('carries the field id through the editor row', () => {
    expect(toPayload(toRow(field({ id: 'abc' }))).id).toBe('abc')
  })
})
