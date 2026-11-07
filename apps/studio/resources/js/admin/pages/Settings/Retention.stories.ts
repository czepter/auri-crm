import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Retention from './Retention.vue'

type Kandidat = App.Privacy.Data.RetentionCandidateData

function kandidat(id: string, name: string, type: string, outcome: 'lost' | 'declined', idleMonths: number, text: string, rest: Partial<Kandidat> = {}): Kandidat {
  return {
    id,
    name,
    type,
    text,
    outcome,
    outcomeLabel: outcome === 'lost' ? 'Verloren' : 'Abgelehnt',
    idleMonths,
    idleSince: new Date(Date.now() - idleMonths * 30.44 * 86_400_000).toISOString().slice(0, 10),
    invoiced: false,
    leads: 1,
    messages: 3,
    offers: 1,
    galleries: 0,
    ...rest,
  }
}

const kandidaten: Kandidat[] = [
  kandidat('k-1', 'Leon Fischer', 'Event', 'declined', 26, 'Konzert im Club, Bezahlung nach Absprache.', { messages: 2, offers: 0 }),
  kandidat('k-2', 'Lena Hoffmann', 'Hochzeit', 'lost', 24, 'Trauung im Mai, ca. 60 Gäste, zwei Locations.', { messages: 6 }),
  kandidat('k-3', 'Kerstin Wendt', 'Hochzeit', 'lost', 20, 'Feier auf dem Land, Anzahlung geleistet.', { invoiced: true, messages: 9, offers: 2, galleries: 1 }),
  kandidat('k-4', 'Tobias Krüger', 'Business', 'lost', 19, 'Teamfotos für die neue Website, zwölf Personen.', { messages: 4 }),
  kandidat('k-5', 'Hannah Böhm', 'Familie', 'lost', 16, 'Familienshooting im Herbst, zwei Kinder.', { offers: 0 }),
  kandidat('k-6', 'Nora Winter', 'Hochzeit', 'declined', 15, 'Trauung an einem Samstag im August.', { offers: 0 }),
  kandidat('k-7', 'Marc Lehmann', 'Event', 'lost', 14, 'Firmenfeier am 14. Juni, Reportage über vier Stunden.', { messages: 5 }),
  kandidat('k-8', 'Julia Sander', 'Porträt', 'lost', 13, 'Bewerbungsfotos, möglichst kurzfristig.', { messages: 2, offers: 0 }),
  kandidat('k-9', 'Timo Beck', 'Porträt', 'declined', 12, 'Bewerbungsfotos, zwei Personen.', { messages: 1, offers: 0 }),
  kandidat('k-10', 'Oliver Peters', 'Hochzeit', 'lost', 11, 'Standesamt und Feier in Weimar, Sommer nächstes Jahr.', { messages: 7, galleries: 1 }),
  kandidat('k-11', 'Emma Kraus', 'Newborn', 'lost', 8, 'Geburt errechnet Ende März.'),
  kandidat('k-12', 'Vera Neumann', 'Business', 'declined', 7, 'Imagefilm und Fotos, Budget offen.', { messages: 2 }),
  kandidat('k-13', 'David Roth', 'Business', 'lost', 5, 'Produktfotos für einen kleinen Onlineshop.', { messages: 2, offers: 0 }),
  kandidat('k-14', 'Ben Schulz', 'Familie', 'declined', 3, 'Familienfotos im Garten.', { messages: 1, offers: 0 }),
]

const meta = {
  title: 'Bildschirme/Einstellungen/Aufbewahrung',
  component: Retention,
  parameters: { layout: 'fullscreen', url: '/einstellungen/aufbewahrung' },
  args: {
    months: 12,
    policy: { name: true, text: true, messages: true, offers: true, galleries: true, order: false },
    candidates: kandidaten,
    funnel: { total: 28, booked: 8 },
  },
} satisfies Meta<typeof Retention>

export default meta
type Story = StoryObj<typeof meta>

/** Die Vorwahl: Person und Inhalte gehen, der Auftrag bleibt in der Auswertung. */
export const Anonymisieren: Story = {}

export const Aus: Story = { args: { months: null } }

/** Ohne Auftrag ändert sich die Auswertung: Anfragen und Buchungsquote springen. */
export const AllesEntfernen: Story = {
  args: { policy: { name: true, text: true, messages: true, offers: true, galleries: true, order: true } },
}

export const NurTexte: Story = {
  args: { policy: { name: false, text: true, messages: true, offers: false, galleries: false, order: false } },
}

export const NichtsFaellig: Story = { args: { months: 36 } }
