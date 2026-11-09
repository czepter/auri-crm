// Ueber den Schluessel, nicht ueber den Namen: den Namen aendert das Studio.
// Ein selbst angelegter Typ bekommt den grauen Punkt, bis project_types eine
// eigene Farbe traegt — dort gehoert sie hin, nicht in diese Tabelle.
export const TYPE_DOT: Record<string, string> = {
    wedding: 'bg-action',
    portrait: 'bg-ok',
}

export function daysAgo(days: number) {
    return days < 1
        ? 'heute'
        : new Intl.RelativeTimeFormat('de-DE', { numeric: 'auto' }).format(-days, 'day')
}

export function relative(at: string) {
    return daysAgo(Math.round((Date.now() - new Date(at).getTime()) / 86400000))
}
