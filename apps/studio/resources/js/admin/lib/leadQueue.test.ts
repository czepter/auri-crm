import { describe, expect, it } from 'vitest'
import { filterQueue } from './leadQueue'

function row(over: Partial<App.Crm.Data.LeadQueueRowData> = {}) {
  return {
    id: 'l1',
    name: 'Sophie Lang & Erik Vogt',
    date: '2026-09-19',
    text: 'Wir heiraten in Köln und suchen eine Fotografin.',
    open: false,
    waitingDays: 0,
    ...over,
  } as App.Crm.Data.LeadQueueRowData
}

const groups = (rows: App.Crm.Data.LeadQueueRowData[]) => [{ key: 'unbeantwortet', rows }]

describe('filterQueue', () => {
  it('leaves every row through without a term or the waiting switch', () => {
    const rows = [row(), row({ id: 'l2', waitingDays: 9 })]

    expect(filterQueue(groups(rows), '', false)[0].rows).toHaveLength(2)
  })

  it('matches the inquiry text and an umlaut typed as its bare vowel', () => {
    expect(filterQueue(groups([row()]), 'köln', false)[0].rows).toHaveLength(1)
    expect(filterQueue(groups([row({ name: 'Jörg Müller' })]), 'muller', false)[0].rows).toHaveLength(1)
  })

  it('keeps the emptied group, so a missed term still leaves its key', () => {
    const shown = filterQueue(groups([row()]), 'hamburg', false)

    expect(shown).toHaveLength(1)
    expect(shown[0].rows).toHaveLength(0)
  })

  it('keeps only the noticeable waits, and takes the term with them', () => {
    const rows = [row({ waitingDays: 2 }), row({ id: 'l2', name: 'Anna Weber', waitingDays: 3 })]

    expect(filterQueue(groups(rows), '', true)[0].rows.map((kept) => kept.id)).toEqual(['l2'])
    expect(filterQueue(groups(rows), 'weber', true)[0].rows.map((kept) => kept.id)).toEqual(['l2'])
  })
})
