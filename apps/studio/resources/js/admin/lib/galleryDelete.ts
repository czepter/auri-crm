// Der Server prüft denselben Titel; eine Galerie ohne Titel hat nichts zu tippen.
export function titleMatches(typed: string, title: string | null): boolean {
  return !title || typed === title
}
