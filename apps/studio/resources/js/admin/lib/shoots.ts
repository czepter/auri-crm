import { groupBy } from '@/admin/lib/projectOverview'

/**
 * Shoot presentation shared by the overview and the Shootings tab, so the two
 * cannot drift into showing the same shoot two different ways.
 */

export function dayAndMonth(date: string | null): string | null {
  return date
    ? new Date(date).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
    : null
}

export function weekday(date: string | null): string | null {
  return date ? new Date(date).toLocaleDateString('de-DE', { weekday: 'long' }) : null
}

/**
 * Which chip a shoot wears. `now` is reserved for the shoot the project's
 * derived position is reading from — the honest highlight, since that is the
 * one holding the project where it is.
 */
export function chipClass(
  shoot: App.Crm.Data.ShootData,
  position: string | null,
): 'off' | 'done' | 'now' | 'next' {
  if (shoot.isCancelled) {
    return 'off'
  }

  if (['ready', 'delivered', 'archived'].includes(shoot.status)) {
    return 'done'
  }

  return shoot.status === position ? 'now' : 'next'
}

export type ShootFilter = 'all' | 'upcoming' | 'delivered'

const DELIVERED = ['delivered', 'archived']

export function filterShoots(shoots: App.Crm.Data.ShootData[], filter: ShootFilter, today: string): App.Crm.Data.ShootData[] {
  if (filter === 'upcoming') {
    return shoots.filter((shoot) => !shoot.isCancelled && !DELIVERED.includes(shoot.status) && (shoot.date === null || shoot.date >= today))
  }

  if (filter === 'delivered') {
    return shoots.filter((shoot) => !shoot.isCancelled && DELIVERED.includes(shoot.status))
  }

  return shoots
}

/** Ein Block je Tag, aufsteigend; Termine ohne Datum (Schlüssel "") zuletzt. */
export function shootDays(shoots: App.Crm.Data.ShootData[]): { key: string; items: App.Crm.Data.ShootData[] }[] {
  return groupBy(shoots, (shoot) => shoot.date ?? '').sort((a, b) => (a.key === '' ? 1 : b.key === '' ? -1 : a.key.localeCompare(b.key)))
}
