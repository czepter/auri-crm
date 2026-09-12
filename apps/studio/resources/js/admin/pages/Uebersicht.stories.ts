import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Uebersicht from './Uebersicht.vue'

const row = (
  who: string,
  what: string,
  rest: Partial<App.Crm.Data.DomainRowData> = {},
): App.Crm.Data.DomainRowData => ({
  projectId: `p-${who.split(',')[0].toLowerCase()}`,
  who,
  what,
  emphasis: null,
  whatTail: null,
  action: null,
  hot: false,
  ...rest,
})

const meta = {
  title: 'Bildschirme/Übersicht',
  component: Uebersicht,
  parameters: { layout: 'fullscreen', url: '/' },
  args: {
    today: '2026-09-22',
    range: '30',
    entries: [
      { date: '2026-10-06', time: '11:00', what: 'Standesamt Ahrens', where: 'Ahrens, Sophie & Tim', away: false, href: '/auftraege/p-ahrens/shootings' },
      { date: '2026-10-17', time: '13:30', what: 'Hochzeit Petersen', where: 'Gut Basthorst', away: false, href: '/auftraege/p-petersen/shootings' },
      { date: '2026-10-23', time: null, what: 'Lena Krohn', where: '23.–24.10.', away: true, href: null },
      { date: '2026-11-07', time: '10:00', what: 'Paarshooting Voss', where: 'Travemünde', away: false, href: '/auftraege/p-voss/shootings' },
      { date: '2026-11-21', time: '12:00', what: 'Hochzeit Timm', where: 'Ratzeburg', away: false, href: '/auftraege/p-timm/shootings' },
      { date: '2026-12-11', time: '09:00', what: 'Firmenporträts Kessler', where: 'Lübeck', away: false, href: '/auftraege/p-kessler/shootings' },
    ],
    pipeline: { total: 0, priorYear: 820000 },
    payments: { open: 1240000, overdue: 0, overdueInstalments: 0, instalments: 12, overdueSinceDays: null, nextDueOn: '2026-10-05', nextAmount: 120000 },
    quotes: { sent: 9, accepted: 5, priorSent: 11, priorAccepted: 4 },
    shoots: { lastTwelveMonths: 19, priorYear: 24 },
    activity: [
      { text: '1.200 € von Ahrens eingegangen', when: 'vor 2 Stunden', fresh: true },
      { text: 'Voss hat die Galerie geöffnet · 312 Bilder', when: 'gestern, 21:40', fresh: false },
      { text: 'Angebot an Timm verschickt · 2.800 €', when: 'Freitag', fresh: false },
      { text: 'Petersen hat den Vertrag unterschrieben', when: 'Donnerstag', fresh: false },
      { text: 'Galerie Lembcke geliefert · 248 Bilder', when: '15.09.', fresh: false },
      { text: 'Anfrage Brandt beantwortet', when: '12.09.', fresh: false },
    ],
    todo: [],
    quotesOut: [
      row('Petersen, Ann-Kathrin', 'Verschickt vor', {
        emphasis: '26 Tagen',
        whatTail: ' · 3.400 €',
        action: { label: 'Nachfassen', kind: 'quote', target: '/auftraege/p-petersen/angebote' },
      }),
      row('Timm, Merle', 'Verschickt vor 9 Tagen', { whatTail: ' · 2.800 €' }),
      row('Voss, Katharina & Jan', 'Verschickt vor 4 Tagen', { whatTail: ' · 5.000 €' }),
    ],
    galleries: [
      row('Voss, Katharina & Jan', 'Auswahl offen', { whatTail: ' · 312 Bilder' }),
      row('Ahrens, Sophie & Tim', 'Auswahl offen', { whatTail: ' · 194 Bilder' }),
      row('Lembcke, Pia', 'Läuft ab am', { emphasis: '28.09.' }),
    ],
  },
} satisfies Meta<typeof Uebersicht>

export default meta
type Story = StoryObj<typeof meta>

/** Nichts fällig, aber das Studio läuft: Termine reichen bis Dezember, der Verlauf trägt den Tag. */
export const RuhigerTag: Story = {}

/** Ein Samstag mitten in der Saison: überfällige Rate, vier Aufgaben, Termine ab morgen. */
export const VollerTag: Story = {
  args: {
    today: '2026-09-12',
    entries: [
      { date: '2026-09-13', time: '14:30', what: 'Hochzeit Rahn', where: 'Gut Basthorst', away: false, href: '/auftraege/p-rahn/shootings' },
      { date: '2026-09-15', time: '10:00', what: 'Vorgespräch Petersen', where: 'Studio', away: false, href: '/auftraege/p-petersen' },
      { date: '2026-09-17', time: null, what: 'Lena Krohn', where: 'ganztags', away: true, href: null },
      { date: '2026-09-19', time: '11:00', what: 'Standesamt Ahrens', where: 'Lübeck', away: false, href: '/auftraege/p-ahrens/shootings' },
      { date: '2026-09-21', time: '17:00', what: 'Paarshooting Voss', where: 'Travemünde', away: false, href: '/auftraege/p-voss/shootings' },
    ],
    pipeline: { total: 1278000, priorYear: 940000 },
    payments: { open: 1330000, overdue: 85200, overdueInstalments: 1, instalments: 3, overdueSinceDays: 11, nextDueOn: null, nextAmount: null },
    activity: [
      { text: 'Rahn hat den Ablauf bestätigt', when: 'heute, 08:40', fresh: true },
      { text: 'Söhnke hat angefragt · 13.09. doppelt', when: 'gestern, 16:40', fresh: true },
      { text: 'Voss hat die Galerie geöffnet · 312 Bilder', when: 'Donnerstag', fresh: false },
      { text: 'Brandt hat angefragt · Trauung 12.06.2027', when: '08.09.', fresh: false },
      { text: 'Erinnerung an Ahrens verschickt · 852 €', when: '07.09.', fresh: false },
      { text: 'Galerie Lembcke geliefert · 248 Bilder', when: '05.09.', fresh: false },
    ],
    todo: [
      row('Rahn, Marie & Jonas', 'Ablaufplan schicken ·', { emphasis: '10.09.', action: { label: 'Erledigt', kind: 'task', target: '/aufgaben' } }),
      row('Familie Brandt', 'Anfrage seit', { emphasis: '4 Tagen', action: { label: 'Angebot schreiben', kind: 'quote', target: '/angebote/neu' } }),
      row('Ahrens, Sophie & Tim', 'Restzahlung seit', { emphasis: '11 Tagen', action: { label: 'Erinnern', kind: 'payment', target: '/auftraege/p-ahrens/zahlungen' } }),
      row('Petersen, Ann-Kathrin', 'Galerie freigeben · 16.09.', { action: { label: 'Erledigt', kind: 'task', target: '/aufgaben' } }),
    ],
    galleries: [
      row('Voss, Katharina & Jan', 'Auswahl offen', { whatTail: ' · 312 Bilder' }),
      row('Lembcke, Pia', 'Läuft ab am', { emphasis: '28.09.' }),
    ],
  },
}

/** Der erste Morgen: keine Termine, keine Historie, der Verlauf kennt nur das Konto. */
export const Tag1: Story = {
  args: {
    today: '2026-09-14',
    entries: [],
    pipeline: { total: 0, priorYear: null },
    payments: { open: 0, overdue: 0, overdueInstalments: 0, instalments: 0, overdueSinceDays: null, nextDueOn: null, nextAmount: null },
    quotes: { sent: 0, accepted: 0, priorSent: null, priorAccepted: null },
    shoots: { lastTwelveMonths: 0, priorYear: null },
    activity: [{ text: 'Konto angelegt', when: 'heute, 08:12', fresh: false }],
    todo: [],
    quotesOut: [],
    galleries: [],
  },
  parameters: { page: { nav: { leads: 0, unread: 0, projects: 0 } } },
}
