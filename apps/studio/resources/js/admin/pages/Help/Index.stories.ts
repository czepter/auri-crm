import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const meta = {
  title: 'Bildschirme/Hilfe',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/hilfe' },
  args: {
    aufgaben: [
      { slug: 'anfrage-erfassen', titel: 'Anfrage erfassen', beschreibung: 'Vom Kontakt zum Lead auf dem Board' },
      { slug: 'angebot-schreiben', titel: 'Angebot schreiben', beschreibung: 'Pakete, Positionen und Gültigkeit' },
      { slug: 'vertrag-versenden', titel: 'Vertrag versenden', beschreibung: 'Unterschrift im Portal einholen' },
      { slug: 'termin-planen', titel: 'Termin planen', beschreibung: 'Crew zuweisen und Kalender füllen' },
    ],
    referenz: [
      { slug: 'nummernkreise', titel: 'Nummernkreise', beschreibung: 'Präfix, Zähler und Zurücksetzen' },
      { slug: 'automatisierungen', titel: 'Automatisierungen', beschreibung: 'Auslöser, Bedingung und Empfänger' },
      { slug: 'rollen', titel: 'Rollen', beschreibung: 'Was Inhaber, Zweitfotograf und Assistenz dürfen' },
    ],
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Aufgaben oben, Nachschlagewerk darunter. */
export const Uebersicht: Story = {}
