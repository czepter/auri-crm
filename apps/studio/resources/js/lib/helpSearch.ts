export type HelpEintrag = { slug: string; titel: string; beschreibung: string; art: string; suchtext: string }

export function treffer(index: HelpEintrag[], frage: string): HelpEintrag[] {
  const q = frage.trim().toLowerCase()
  if (!q) return []

  return index.filter((e) => e.titel.toLowerCase().includes(q) || e.suchtext.toLowerCase().includes(q))
}
