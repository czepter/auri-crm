// Keine Auswahl und alle gewählt heißen dasselbe: die ganze Galerie. Der
// Server bekommt dann keine Liste und kopiert, was später dazukommt, nicht
// anders als jetzt.
export function copyIds(selected: Set<string>, all: string[]): string[] | null {
  const chosen = all.filter((id) => selected.has(id))

  return chosen.length === 0 || chosen.length === all.length ? null : chosen
}

export function copyCount(selected: Set<string>, all: string[]): number {
  return copyIds(selected, all)?.length ?? all.length
}
