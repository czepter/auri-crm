// Die Zonenliste kommt aus Intl statt aus einem Paket: der Browser trägt die
// aktuelle tzdata, ein mitgeliefertes JSON altert zwischen zwei Releases.

function intlLocale(locale: string): string {
  return locale.replace('_', '-')
}

export function browserTimezone(): string {
  return Intl.DateTimeFormat().resolvedOptions().timeZone
}

export function timezoneLabel(zone: string, locale: string): string {
  const now = new Date()

  try {
    const name = new Intl.DateTimeFormat(intlLocale(locale), { timeZone: zone, timeZoneName: 'long' })
      .formatToParts(now)
      .find((part) => part.type === 'timeZoneName')?.value
    const offset = new Intl.DateTimeFormat('en', { timeZone: zone, timeZoneName: 'longOffset' })
      .formatToParts(now)
      .find((part) => part.type === 'timeZoneName')?.value
      .replace('GMT', 'UTC')

    return `${zone} · ${name} (${offset})`
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
