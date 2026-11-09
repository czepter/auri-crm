import { describe, expect, it } from 'vitest'
import { filterEvents, groupByDay, type ActivityEvent } from './galleryActivity'

const photo = { n: 3, src: '/x' }

const events: ActivityEvent[] = [
  { type: 'comment', at: '2026-11-09T10:00:00+01:00', id: 'c1', studio: false, who: 'Jana', photo, text: 'Schön' },
  { type: 'views', at: '2026-11-09T00:00:00+01:00', count: 4 },
  { type: 'favorites', at: '2026-11-08T09:00:00+01:00', who: null, photos: [photo] },
  { type: 'hide', at: '2026-11-08T08:00:00+01:00', id: 'h1', who: 'Jana', photo },
  { type: 'download', at: '2026-11-07T00:00:00+01:00', what: null, size: 'web' },
]

describe('filterEvents', () => {
  it('keeps the mixed timeline for all, views included', () => {
    expect(filterEvents(events, 'all')).toHaveLength(5)
  })

  it('narrows to one kind and drops view counts', () => {
    expect(filterEvents(events, 'comments').map((event) => event.type)).toEqual(['comment'])
    expect(filterEvents(events, 'hidden').map((event) => event.type)).toEqual(['hide'])
    expect(filterEvents(events, 'downloads').map((event) => event.type)).toEqual(['download'])
  })
})

describe('groupByDay', () => {
  it('groups consecutive events of one day and keeps the given order', () => {
    expect(groupByDay(events).map((group) => [group.day, group.events.length])).toEqual([
      ['2026-11-09', 2],
      ['2026-11-08', 2],
      ['2026-11-07', 1],
    ])
  })
})
