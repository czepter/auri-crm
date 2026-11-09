export type ActivityFilter = 'all' | 'favorites' | 'comments' | 'hidden' | 'downloads'

export interface Photo {
  n: number
  src: string
}

export type ActivityEvent =
  | { type: 'comment'; at: string; id: string; studio: boolean; who: string | null; photo: Photo; text: string }
  | { type: 'favorites'; at: string; who: string | null; photos: Photo[] }
  | { type: 'hide'; at: string; id: string; who: string; photo: Photo }
  | { type: 'download'; at: string; what: string | null; size: string | null }
  | { type: 'views'; at: string; count: number }

export const FILTERS: ActivityFilter[] = ['all', 'favorites', 'comments', 'hidden', 'downloads']

const KIND: Record<ActivityEvent['type'], ActivityFilter | null> = {
  comment: 'comments',
  favorites: 'favorites',
  hide: 'hidden',
  download: 'downloads',
  views: null,
}

export function filterEvents(events: ActivityEvent[], filter: ActivityFilter): ActivityEvent[] {
  return filter === 'all' ? events : events.filter((event) => KIND[event.type] === filter)
}

export function groupByDay(events: ActivityEvent[]): { day: string; events: ActivityEvent[] }[] {
  const days = new Map<string, ActivityEvent[]>()

  for (const event of events) {
    const day = event.at.slice(0, 10)
    days.set(day, [...(days.get(day) ?? []), event])
  }

  return [...days].map(([day, rows]) => ({ day, events: rows }))
}
