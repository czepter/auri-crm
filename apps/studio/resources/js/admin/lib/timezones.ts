// Die Zonenliste kommt aus Intl statt aus einem Paket: der Browser trägt die
// aktuelle tzdata, ein mitgeliefertes JSON altert zwischen zwei Releases.

export function browserTimezone(): string {
  return Intl.DateTimeFormat().resolvedOptions().timeZone
}

export function timezoneLabel(zone: string): string {
  const now = new Date()

  try {
    const short = new Intl.DateTimeFormat('de', { timeZone: zone, timeZoneName: 'short' })
      .formatToParts(now)
      .find((part) => part.type === 'timeZoneName')?.value
    const city = (zone.split('/').pop() ?? zone).replaceAll('_', ' ')

    return zone.includes('/') ? `${city} (${short})` : zone
  } catch {
    // Der Server nimmt jede PHP-Zone an, auch Aliasse, die Intl nicht kennt.
    return zone
  }
}

export function searchTimezones(term: string, selected: string, limit = 50): string[] {
  const zones = Intl.supportedValuesOf('timeZone')
  const known = zones.includes(selected) ? zones : [selected, ...zones]
  const needle = term.trim().toLowerCase()

  return known.filter((zone) => zone.toLowerCase().includes(needle)).slice(0, limit)
}
