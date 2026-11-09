type Tri = boolean | null

export type DownloadRule = 'open' | 'after_payment' | 'off'

type Preset = { downloadRule: string | null; downloadsEnabled: Tri; listsEnabled: Tri; commentsEnabled: Tri }
type Project = { galleryDefaults: { downloads: Tri; lists: Tri; comments: Tri } }
type Studio = { downloadRule: string; lists: boolean; comments: boolean }
type Born = { downloadRule: DownloadRule; lists: boolean; comments: boolean }

/*
 * Womit eine neue Galerie geboren wird. Die Vorlage steht dem Fall näher als
 * der Kontakt und gewinnt gegen ihn; ein Wert, zu dem eine Ebene nichts sagt,
 * reicht nach unten durch. Läuft hier und nicht auf dem Server, damit vor dem
 * Absenden sichtbar ist, was die Vorlage getan hat — und jedes Feld einzeln
 * korrigierbar bleibt.
 */
export function birthValues(preset: Preset | null, project: Project | null, studio: Studio): Born {
  const pick = (fromPreset: Tri, fromProject: Tri, fromStudio: boolean) =>
    fromPreset ?? fromProject ?? fromStudio

  return {
    downloadRule: downloadRule(preset, project?.galleryDefaults.downloads ?? null, studio.downloadRule),
    lists: pick(preset?.listsEnabled ?? null, project?.galleryDefaults.lists ?? null, studio.lists),
    comments: pick(preset?.commentsEnabled ?? null, project?.galleryDefaults.comments ?? null, studio.comments),
  }
}

// Der Kontakt kennt nur Ja oder Nein: Nein sperrt, Ja lässt die Vorgabe des
// Studios stehen und öffnet nur, wo das Studio gesperrt hat. Vorlagen von vor
// der Regel kennen ebenfalls nur den Schalter.
function downloadRule(preset: Preset | null, fromProject: Tri, fromStudio: string): DownloadRule {
  if (preset?.downloadRule) {
    return preset.downloadRule as DownloadRule
  }

  const legacy = preset?.downloadsEnabled ?? null

  if (legacy !== null) {
    return legacy ? 'open' : 'off'
  }

  if (fromProject === null) {
    return fromStudio as DownloadRule
  }

  return !fromProject ? 'off' : fromStudio === 'off' ? 'open' : (fromStudio as DownloadRule)
}

type Expiry = { expiryMode: string; expiryDays: number | null }

/*
 * Bis wann eine neue Galerie gilt, als lokaler Kalendertag. toISOString()
 * rechnet nach UTC um und liefert in MESZ vor 02:00 Uhr den Vortag — deshalb
 * die Felder einzeln.
 */
export function expiryOn(preset: Expiry | null, studio: { expiryDays: number | null }, from: Date): string {
  const days =
    preset === null || preset.expiryMode === 'inherit'
      ? studio.expiryDays
      : preset.expiryMode === 'never'
        ? null
        : preset.expiryDays

  if (days === null) {
    return ''
  }

  const date = new Date(from)
  date.setDate(date.getDate() + days)

  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-')
}
