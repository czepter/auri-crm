/**
 * Shoot presentation shared by the overview and the Shootings tab, so the two
 * cannot drift into showing the same shoot two different ways.
 */

/** "22.03." — the mockup's date column reads day and month, year implied. */
export function dayAndMonth(date: string | null): string | null {
  return date
    ? new Date(date).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
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
