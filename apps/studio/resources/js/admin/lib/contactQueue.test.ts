import { describe, expect, it } from 'vitest'
import { groupContacts } from './contactQueue'

function row(over: Partial<App.Crm.Data.ContactRowData> = {}) {
  return {
    id: 'c1',
    name: 'Charlotte Vehse',
    email: 'charlotte.vehse@example.test',
    phone: null,
    projectCount: 1,
    standing: 'running',
    nextDate: '2026-09-19',
    latestProject: 'Charlotte Vehse & Paul Rieck',
    ...over,
  } as App.Crm.Data.ContactRowData
}

describe('groupContacts', () => {
  it('orders the groups by standing, not by the order of the rows', () => {
    const groups = groupContacts([row({ standing: 'none' }), row({ id: 'c2', standing: 'running' })])

    expect(groups.map((group) => group.key)).toEqual(['running', 'none'])
  })

  it('drops a group nothing falls into', () => {
    expect(groupContacts([row()]).map((group) => group.key)).toEqual(['running'])
  })

  it('finds an umlaut typed as its bare vowel', () => {
    expect(groupContacts([row({ name: 'Jörg Müller' })], 'muller')[0].rows).toHaveLength(1)
  })

  it('matches the mail address and the project, not only the name', () => {
    expect(groupContacts([row()], 'example.test')[0].rows).toHaveLength(1)
    expect(groupContacts([row()], 'rieck')[0].rows).toHaveLength(1)
  })

  it('leaves nothing standing when the term misses', () => {
    expect(groupContacts([row()], 'hamburg')).toEqual([])
  })
})
