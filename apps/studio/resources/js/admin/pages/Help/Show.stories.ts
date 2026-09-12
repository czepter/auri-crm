import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Show from './Show.vue'

const meta = {
  title: 'Bildschirme/Hilfe/Artikel',
  component: Show,
  parameters: { layout: 'fullscreen', url: '/hilfe/angebot-schreiben' },
  args: {
    artikel: {
      slug: 'angebot-schreiben',
      titel: 'Angebot schreiben',
      art: 'aufgabe',
      html: [
        '<p>Ein Angebot entsteht am Lead: öffne ihn und wähle <em>Angebot</em>.</p>',
        '<h2>Positionen</h2>',
        '<p>Pakete bringen ihre Positionen mit. Einzelne Zeilen lassen sich danach ändern, ohne das Paket zu berühren.</p>',
        '<h2>Gültigkeit</h2>',
        '<p>Die Frist kommt aus den Einstellungen und steht pro Angebot frei.</p>',
      ].join(''),
    },
    aufgaben: [
      { slug: 'anfrage-erfassen', titel: 'Anfrage erfassen', beschreibung: 'Vom Kontakt zum Lead auf dem Board' },
      { slug: 'angebot-schreiben', titel: 'Angebot schreiben', beschreibung: 'Pakete, Positionen und Gültigkeit' },
      { slug: 'termin-planen', titel: 'Termin planen', beschreibung: 'Crew zuweisen und Kalender füllen' },
    ],
    referenz: [
      { slug: 'nummernkreise', titel: 'Nummernkreise', beschreibung: 'Präfix, Zähler und Zurücksetzen' },
      { slug: 'rollen', titel: 'Rollen', beschreibung: 'Was Inhaber, Zweitfotograf und Assistenz dürfen' },
    ],
  },
} satisfies Meta<typeof Show>

export default meta
type Story = StoryObj<typeof meta>

/** Ein Artikel mit der Navigation seiner Nachbarn links. */
export const Artikel: Story = {}
