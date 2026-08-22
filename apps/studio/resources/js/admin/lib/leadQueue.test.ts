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

  it('matches the inquiry text, not only the name', () => {
    expect(filterQueue(groups([row()]), 'köln', false)[0].rows).toHaveLength(1)
  })

  it('finds an umlaut typed as its bare vowel', () => {
    expect(filterQueue(groups([row({ name: 'Jörg Müller' })]), 'muller', false)[0].rows).toHaveLength(1)
  })

  it('drops rows the term misses', () => {
    expect(filterQueue(groups([row()]), 'hamburg', false)[0].rows).toHaveLength(0)
  })

  it('keeps only the noticeable waits when the switch is on', () => {
    const rows = [row({ waitingDays: 2 }), row({ id: 'l2', waitingDays: 3 })]
    const kept = filterQueue(groups(rows), '', true)[0].rows

    expect(kept.map((kept) => kept.id)).toEqual(['l2'])
  })

  it('takes the term and the switch together', () => {
    const rows = [row({ waitingDays: 9 }), row({ id: 'l2', name: 'Anna Weber', waitingDays: 9 })]
    const kept = filterQueue(groups(rows), 'weber', true)[0].rows

    expect(kept.map((kept) => kept.id)).toEqual(['l2'])
  })

  it('keeps the groups themselves, so an emptied one still has its key', () => {
    expect(filterQueue(groups([row()]), 'hamburg', false)).toHaveLength(1)
  })
})
