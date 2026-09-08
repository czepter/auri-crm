export type HelpEintrag = { slug: string; titel: string; beschreibung: string; art: string; suchtext: string }

// Modulebene, nicht Komponentenzustand: AdminLayout mountet bei jeder
// Inertia-Navigation neu (kein persistentes Layout, siehe helpPanel.ts), ein
// Ref-Init in CommandPalette.vue würde also pro besuchter Seite neu holen.
// Als geteilte Inertia-Prop wären es sechzig Kilobyte an jeder Antwort, damit
// jemand vielleicht ⌘K drückt.
let geholt: Promise<HelpEintrag[]> | null = null

export function holeIndex(): Promise<HelpEintrag[]> {
  // Ohne das Zurücksetzen bliebe ein einziger Fehlschlag — offline, oder eine
  // 302 auf /login, an deren HTML r.json() erstickt — bis zum Seitenneuladen
  // gemerkt, und CommandPalette.vue awaitet ohne catch.
  geholt ??= fetch('/hilfe/suche', { headers: { Accept: 'application/json' } })
    .then((r) => r.json())
    .catch(() => {
      geholt = null
      return []
    })

  return geholt
}

export function treffer(index: HelpEintrag[], frage: string): HelpEintrag[] {
  const q = frage.trim().toLowerCase()
  if (!q) return []

  return index.filter((e) => e.titel.toLowerCase().includes(q) || e.suchtext.toLowerCase().includes(q))
}
