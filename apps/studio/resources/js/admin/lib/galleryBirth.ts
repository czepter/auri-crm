type Tri = boolean | null

type Preset = { downloadsEnabled: Tri; listsEnabled: Tri; commentsEnabled: Tri }
type Project = { galleryDefaults: { downloads: Tri; lists: Tri; comments: Tri } }
type Studio = { downloads: boolean; lists: boolean; comments: boolean }

/*
 * Womit eine neue Galerie geboren wird. Die Vorlage steht dem Fall näher als
 * der Kontakt und gewinnt gegen ihn; ein Wert, zu dem eine Ebene nichts sagt,
 * reicht nach unten durch. Läuft hier und nicht auf dem Server, damit vor dem
 * Absenden sichtbar ist, was die Vorlage getan hat — und jedes Feld einzeln
 * korrigierbar bleibt.
 */
export function birthValues(preset: Preset | null, project: Project | null, studio: Studio): Studio {
  const pick = (fromPreset: Tri, fromProject: Tri, fromStudio: boolean) =>
    fromPreset ?? fromProject ?? fromStudio

  return {
    downloads: pick(preset?.downloadsEnabled ?? null, project?.galleryDefaults.downloads ?? null, studio.downloads),
    lists: pick(preset?.listsEnabled ?? null, project?.galleryDefaults.lists ?? null, studio.lists),
    comments: pick(preset?.commentsEnabled ?? null, project?.galleryDefaults.comments ?? null, studio.comments),
  }
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
