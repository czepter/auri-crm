import { ref } from 'vue'

type Artikel = { slug: string; titel: string; art: string; html: string }

// Modulebene, nicht Komponentenzustand: main.ts vergibt keine persistenten
// Inertia-Layouts, jede Seite bringt AdminLayout selbst mit und mountet es
// neu. Was die Navigation überleben soll, lebt hier.
const geladen = new Map<string, Artikel>()

const offen = ref(false)
const ueberschrieben = ref<string | null>(null)

function zuruecksetzen() {
  geladen.clear()
  ueberschrieben.value = null
  // Kein gespeicherter Wert heißt offen. Geschrieben wird `help.open` nur
  // durch oeffnen()/schliessen() — also nur durch einen bewussten Klick,
  // nie durchs bloße Rendern.
  offen.value = localStorage.getItem('help.open') === null
    ? true
    : localStorage.getItem('help.open') === '1'
}

zuruecksetzen()

export const helpPanel = {
  offen,
  ueberschrieben,

  oeffnen(slug?: string) {
    offen.value = true
    localStorage.setItem('help.open', '1')
    if (slug) ueberschrieben.value = slug
  },

  schliessen() {
    offen.value = false
    localStorage.setItem('help.open', '0')
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
