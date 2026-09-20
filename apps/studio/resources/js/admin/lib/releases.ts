/*
 * Was die Neuigkeiten-Screens aus einer release-log-Antwort machen. Die Formen
 * hier sind die des Dienstes (release-log/server.mjs), nicht die einer
 * Data-Klasse: der Controller reicht die Antwort durch.
 */

export type ReleaseSection = {
  key: string
  label: string
  count?: number
  items?: ReleaseChange[]
}

export type ReleaseChange = {
  type: string
  title: string
  description: string | null
  commit: string
}

export type FeedItem = {
  version: string
  date: string
  headline: string | null
  /* Absätze unter der Überschrift. Der Feed trägt sie mit — ohne sie steht
     dort eine Nummer und drei Zahlen. */
  body: string[]
  image: { src: string; alt: string } | null
  sections: ReleaseSection[]
}

export type ReleaseDetail = FeedItem & {
  tag: string | null
}

/** Die drei Gruppen, die der Dienst kennt, mit ihren Schlüsseln in de.json. */
const LABELS: Record<string, string> = {
  new: 'releases.groupNew',
  changed: 'releases.groupChanged',
  fixed: 'releases.groupFixed',
}

export function sectionLabel(key: string): string | null {
  return LABELS[key] ?? null
}

/**
 * Die Zähler einer Feed-Zeile, leere Gruppen raus. Der Dienst liefert bereits
 * nur nichtleere Abschnitte; die Prüfung steht hier, weil eine 0 in der Zeile
 * schlimmer aussieht als eine fehlende Gruppe.
 */
export function sectionCounts(sections: ReleaseSection[]): { key: string; label: string; count: number }[] {
  return (sections ?? [])
    .map((section) => ({ key: section.key, label: sectionLabel(section.key), count: section.count ?? 0 }))
    .filter((part): part is { key: string; label: string; count: number } => part.label !== null && part.count > 0)
}

export type Mark = { key: string; class: string }

/**
 * Die Marke an einer Version: die laufende, oder eine höhere, die es schon
 * gibt. Nichts an allen anderen.
 *
 * `dev` ist keine Version — version_compare in PHP wie ein naiver Vergleich
 * hier sortieren sie unter jede Zahl, und ein Branch-Build würde sonst jede
 * Zeile als verfügbar markieren.
 */
export function releaseMark(version: string, installed: string): Mark | null {
  if (version === installed) {
    return { key: 'releases.installed', class: 'bg-ok-bg text-ok' }
  }

  if (!/^\d/.test(installed)) return null

  return compare(version, installed) > 0
    ? { key: 'releases.available', class: 'bg-due-bg text-due' }
    : null
}

/** Semver-Vergleich über die Zahlen, ohne Vorabversionen — die gibt es hier nicht. */
function compare(a: string, b: string): number {
  const left = a.split('.').map(Number)
  const right = b.split('.').map(Number)

  for (let i = 0; i < Math.max(left.length, right.length); i += 1) {
    const diff = (left[i] ?? 0) - (right[i] ?? 0)
    if (diff !== 0) return diff
  }

  return 0
}

/** Das Bild eines Releases liegt beim Dienst, nicht in diesem Build. */
export function mediaUrl(base: string | null, src: string | null | undefined): string | null {
  return base && src ? `${base}/${src.replace(/^\//, '')}` : null
}
