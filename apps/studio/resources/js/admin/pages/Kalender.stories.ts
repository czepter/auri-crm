import type { Meta, StoryObj } from '@storybook/vue3-vite'
import CalendarSeason from '@/admin/components/crm/CalendarSeason.vue'
import { addDays, weekMondays } from '@/admin/lib/season'
import Kalender from './Kalender.vue'

type Saison = InstanceType<typeof CalendarSeason>['$props']['season']
type SaisonShoot = Saison['shoots'][number]

const HEUTE = '2026-09-12'

function tage(
  start: string,
  monat: string,
  eintraege: Record<string, App.Crm.Data.CalendarEntryData[]>,
): App.Crm.Data.CalendarDayData[] {
  return Array.from({ length: 42 }, (_, index) => {
    const date = addDays(start, index)

    return { date, inMonth: date.startsWith(monat), today: date === HEUTE, entries: eintraege[date] ?? [] }
  })
}

const PAARE = ['Rahn', 'Brandt', 'Krohn', 'Söhnke', 'Petersen', 'Ahrens', 'Voss', 'Lembcke']

/** Die Samstage der Wochen `von` bis `bis` als Termine — eine belegte Saison. */
function samstage(jahr: number, von: number, bis: number, phase: SaisonShoot['phase'], status: string): SaisonShoot[] {
  return weekMondays(jahr)
    .slice(von - 1, bis)
    .map((montag, index) => ({
      date: addDays(montag, 5),
      name: `Hochzeit ${PAARE[index % PAARE.length]}`,
      projectId: `p-${jahr}-${index}`,
      status,
      phase,
    }))
}

const shootRahn: App.Crm.Data.ShootData = {
  id: 's-rahn-feier',
  kind: 'wedding',
  label: 'Feier',
  date: '2026-09-13',
  callTime: '14:30',
  endTime: '23:00',
  venue: 'Gut Basthorst, Basthorst',
  venueName: 'Gut Basthorst',
  venueCity: 'Basthorst',
  status: 'confirmed',
  statusLabel: 'Bestätigt',
  isPrimary: true,
  isCancelled: false,
  cancelledReason: null,
  crew: [
    { id: 'c-1', userId: 'u1', name: 'Christian Zepter', initials: 'CZ' },
    { id: 'c-2', userId: 'u2', name: 'Lena Krohn', initials: 'LK' },
  ],
  forwardStatuses: [{ value: 'shot', label: 'Fotografiert' }],
  dueStatus: null,
  timeline: [],
  pdfUrl: '/auftraege/p-rahn/shootings/s-rahn-feier.pdf',
  icsUrl: '/auftraege/p-rahn/shootings/s-rahn-feier.ics',
  crewConflicts: [],
}

const shootAhrens: App.Crm.Data.ShootData = {
  id: 's-ahrens-standesamt',
  kind: 'civil',
  label: 'Standesamt',
  date: '2026-09-19',
  callTime: '11:00',
  endTime: '13:00',
  venue: 'Standesamt Hamburg-Nord',
  venueName: 'Standesamt Hamburg-Nord',
  venueCity: 'Hamburg',
  status: 'planned',
  statusLabel: 'Geplant',
  isPrimary: true,
  isCancelled: false,
  cancelledReason: null,
  crew: [
    { id: 'c-3', userId: 'u1', name: 'Christian Zepter', initials: 'CZ' },
  ],
  forwardStatuses: [{ value: 'confirmed', label: 'Bestätigt' }],
  dueStatus: null,
  timeline: [],
  pdfUrl: '/auftraege/p-ahrens/shootings/s-ahrens-standesamt.pdf',
  icsUrl: '/auftraege/p-ahrens/shootings/s-ahrens-standesamt.ics',
  crewConflicts: [],
}

const meta = {
  title: 'Bildschirme/Kalender',
  component: Kalender,
  parameters: { layout: 'fullscreen', url: '/kalender' },
  args: {
    view: 'monat',
    month: '2026-09',
    label: 'September 2026',
    previous: '2026-08',
    next: '2026-10',
    days: tage('2026-08-31', '2026-09', {
      '2026-09-13': [
        { kind: 'shoot', time: '14:30', what: 'Hochzeit', who: 'Rahn', projectId: 'p-rahn' },
        { kind: 'hold', time: null, what: 'Option', who: 'Söhnke', projectId: 'p-soehnke' },
      ],
      '2026-09-15': [{ kind: 'shoot', time: '10:00', what: 'Vorgespräch', who: 'Petersen', projectId: 'p-petersen' }],
      '2026-09-17': [{ kind: 'away', time: null, what: 'Urlaub', who: 'Lena Krohn', projectId: null }],
      '2026-09-19': [{ kind: 'shoot', time: '11:00', what: 'Standesamt', who: 'Ahrens', projectId: 'p-ahrens' }],
      '2026-09-26': [
        { kind: 'shoot', time: '13:00', what: 'Hochzeit', who: 'Voss', projectId: 'p-voss' },
        { kind: 'shoot', time: '09:00', what: 'Paarshooting', who: 'Lembcke', projectId: 'p-lembcke' },
        { kind: 'shoot', time: '18:00', what: 'Portraits', who: 'Krohn', projectId: 'p-krohn' },
        { kind: 'shoot', time: '20:00', what: 'Party', who: 'Voss', projectId: 'p-voss' },
      ],
    }),
    shoots: [shootRahn, shootAhrens],
    projects: {
      's-rahn-feier': { id: 'p-rahn', name: 'Rahn, Marie & Jonas' },
      's-ahrens-standesamt': { id: 'p-ahrens', name: 'Ahrens, Sophie & Tim' },
    },
    mine: ['s-rahn-feier'],
    workingDays: [1, 2, 3, 4, 5],
    season: {
      shoots: [
        ...samstage(2026, 18, 36, 'delivered', 'Ausgeliefert'),
        ...samstage(2026, 37, 42, 'upcoming', 'Bestätigt'),
        ...samstage(2027, 16, 41, 'upcoming', 'Bestätigt'),
        ...samstage(2028, 20, 34, 'upcoming', 'Option'),
      ],
      away: [
        { from: '2026-10-19', to: '2026-10-30', what: 'Urlaub', who: 'Christian Zepter' },
        { from: '2027-01-11', to: '2027-01-22', what: 'Urlaub', who: 'Lena Krohn' },
      ],
    },
  },
} satisfies Meta<typeof Kalender>

export default meta
type Story = StoryObj<typeof meta>

/** Der Monat, wie er nach dem Aufruf steht — mit einem Tag über der Drei-Zeilen-Grenze. */
export const Monat: Story = {}

/** Drei Jahre dicht belegt, mit Doppelbelegung und Abwesenheiten in derselben Zeile. */
export const SaisonDicht: Story = { args: { view: 'saison' } }

/** Ein Jahr mit zwei Terminen: die Saison bleibt eine Zeile Wochen, fast alle leer. */
export const SaisonFastLeer: Story = {
  args: {
    view: 'saison',
    season: {
      shoots: [
        { date: '2026-09-13', name: 'Hochzeit Rahn', projectId: 'p-rahn', status: 'Bestätigt', phase: 'upcoming' },
        { date: '2027-06-12', name: 'Trauung Brandt', projectId: 'p-brandt', status: 'Option', phase: 'upcoming' },
      ],
      away: [],
    },
  },
}

/** Ein Monat ohne Termine, in der Liste. */
export const ListeLeer: Story = { args: { view: 'liste', shoots: [] } }

/** Arbeitstage über dem Monat. */
