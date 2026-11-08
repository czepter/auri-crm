import { describe, expect, it } from 'vitest'
import { filterShoots, shootDays } from './shoots'

const shoot = (id: string, date: string | null, status = 'planned', isCancelled = false) =>
  ({ id, date, status, isCancelled }) as App.Crm.Data.ShootData

const shoots = [
  shoot('late', '2026-12-01'),
  shoot('open', null),
  shoot('past', '2026-01-05', 'delivered'),
  shoot('gone', '2026-12-01', 'planned', true),
  shoot('same', '2026-12-01'),
]

describe('filterShoots', () => {
  it('trennt anstehend und geliefert, abgesagte bleiben nur unter Alle', () => {
    const ids = (filter: 'all' | 'upcoming' | 'delivered') => filterShoots(shoots, filter, '2026-06-01').map((s) => s.id)

    expect(ids('all')).toHaveLength(5)
    expect(ids('upcoming')).toEqual(['late', 'open', 'same'])
    expect(ids('delivered')).toEqual(['past'])
  })
})

describe('shootDays', () => {
  it('gruppiert nach Tag, aufsteigend, ohne Datum zuletzt', () => {
    const days = shootDays(shoots)

    expect(days.map((d) => d.key)).toEqual(['2026-01-05', '2026-12-01', ''])
    expect(days[1].items.map((s) => s.id)).toEqual(['late', 'gone', 'same'])
  })
})
