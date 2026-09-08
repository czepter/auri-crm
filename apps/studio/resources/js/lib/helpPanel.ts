import { ref } from 'vue'

type Artikel = { slug: string; titel: string; art: string; html: string }
type Klick = Pick<MouseEvent, 'defaultPrevented' | 'button' | 'metaKey' | 'ctrlKey' | 'shiftKey' | 'altKey'>

// Modulebene, nicht Komponentenzustand: main.ts vergibt keine persistenten
// Inertia-Layouts, jede Seite bringt AdminLayout selbst mit und mountet es
// neu. Was die Navigation überleben soll, lebt hier.
const geladen = new Map<string, Artikel>()

const offen = ref(false)
const ueberschrieben = ref<string | null>(null)

// AdminLayout rendert den Sidebar-Knopf oberhalb von Page.vue und kennt die
// Seiten-Rail nicht — nur Page.vue weiß, ob #right steht, und nur Page.vue
// kennt die zwei Schwellen. Page.vue schreibt hier bei jedem Render, der
// Knopf liest nur.
const dockt = ref(false)

// Ein Browser mit blockierten Site-Daten wirft schon beim Zugriff auf
// `localStorage`. zuruecksetzen() läuft beim Import, Page.vue importiert das
// hier und steht auf jedem Adminscreen — ein Wurf nimmt das ganze Bundle mit.
// Ohne Speicher gilt der Vorgabewert, und der Zustand hält für diese
// Seitenladung.
function gespeichert(): string | null {
  try {
    return localStorage.getItem('help.open')
  } catch {
    return null
  }
}

function speichern(wert: string) {
  try {
    localStorage.setItem('help.open', wert)
  } catch {
    // Siehe gespeichert().
  }
}

function zuruecksetzen() {
  geladen.clear()
  ueberschrieben.value = null
  dockt.value = false
  // Kein gespeicherter Wert heißt offen. Geschrieben wird `help.open` nur
  // durch oeffnen()/schliessen() — also nur durch einen bewussten Klick,
  // nie durchs bloße Rendern.
  offen.value = gespeichert() === null ? true : gespeichert() === '1'
}

zuruecksetzen()

// Reiner Entscheid über sieben primitiven Feldern — keine DOM-Abhängigkeit,
// damit er ohne jsdom testbar bleibt. `HelpRail.vue` führt nur noch aus,
// was hier entschieden wurde.
export function linkZiel(event: Klick, href: string | null): 'artikel' | 'seite' | null {
  if (!href || !href.startsWith('/')) return null
  if (event.defaultPrevented || event.button !== 0) return null
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return null

  return href.startsWith('/hilfe/') ? 'artikel' : 'seite'
}

export const helpPanel = {
  offen,
  ueberschrieben,
  dockt,

  oeffnen(slug?: string) {
    offen.value = true
    speichern('1')
    if (slug) ueberschrieben.value = slug
  },

  schliessen() {
    offen.value = false
    speichern('0')
  },

  navigiert() {
    ueberschrieben.value = null
  },

  async artikel(slug: string): Promise<Artikel> {
    const bekannt = geladen.get(slug)
    if (bekannt) return bekannt

    const antwort = await fetch(`/hilfe/artikel/${slug}`, {
      headers: { Accept: 'application/json' },
    })

    if (!antwort.ok) throw new Error(String(antwort.status))

    const artikel = (await antwort.json()) as Artikel
    geladen.set(slug, artikel)

    return artikel
  },

  zuruecksetzen,
}
