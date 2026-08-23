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
