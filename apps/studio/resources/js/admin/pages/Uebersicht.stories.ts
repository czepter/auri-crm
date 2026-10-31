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

const task = (id: string, projectId: string, projectName: string, title: string, dueOn: string, isOverdue: boolean): App.Crm.Data.TaskRowData => ({
  id,
  projectId,
  projectName,
  title,
  dueOn,
  isOverdue,
  isDone: false,
  shootId: null,
  shootLabel: null,
  audience: 'studio',
  audienceLabel: 'Studio',
  isBlocked: false,
  blockedBy: null,
  completedOn: null,
  dueAnchorLabel: null,
  assigneeId: 'u1',
  assigneeName: 'Christian Zepter',
  contactId: null,
  contactName: null,
})

const lead = (id: string, name: string, waitingDays: number): App.Crm.Data.LeadQueueRowData => ({
  id,
  name,
  date: null,
  text: null,
  open: false,
  waitingDays,
})

const project = (id: string, name: string, date: string, stage: string, reason: string | null, days: number | null): App.Crm.Data.CaseQueueRowData => ({
  id,
  name,
  date,
  stage,
  reason,
  days,
  open: false,
})

const meta = {
  title: 'Bildschirme/Übersicht',
  component: Uebersicht,
  parameters: { layout: 'fullscreen', url: '/' },
  args: {
    today: '2026-09-22',
    range: 'year',
    entries: [
      { date: '2026-10-06', time: '11:00', what: 'Standesamt Ahrens', where: 'Ahrens, Sophie & Tim', away: false, href: '/auftraege/p-ahrens/shootings' },
      { date: '2026-10-17', time: '13:30', what: 'Hochzeit Petersen', where: 'Gut Basthorst', away: false, href: '/auftraege/p-petersen/shootings' },
      { date: '2026-10-23', time: null, what: 'Lena Krohn', where: '23.–24.10.', away: true, href: null },
      { date: '2026-11-07', time: '10:00', what: 'Paarshooting Voss', where: 'Travemünde', away: false, href: '/auftraege/p-voss/shootings' },
      { date: '2026-11-21', time: '12:00', what: 'Hochzeit Timm', where: 'Ratzeburg', away: false, href: '/auftraege/p-timm/shootings' },
      { date: '2026-12-11', time: '09:00', what: 'Firmenporträts Kessler', where: 'Lübeck', away: false, href: '/auftraege/p-kessler/shootings' },
    ],
    pipeline: { total: 0, priorYear: null },
    payments: { open: 1240000, overdue: 0, overdueInstalments: 0, instalments: 12, overdueSinceDays: null, nextDueOn: '2026-10-05', nextAmount: 120000 },
    quotes: { sent: 9, accepted: 5, priorSent: 11, priorAccepted: 4 },
    shoots: { count: 19, priorYear: 24 },
    activity: [
      { text: 'Ahrens, Sophie & Tim · Zahlung erhalten', href: '/auftraege/p-ahrens', when: 'heute, 09:14', fresh: true },
      { text: 'Voss, Katharina & Jan · Galerie geöffnet', href: '/auftraege/p-voss', when: 'gestern', fresh: false },
      { text: 'Timm, Merle · Angebot gesendet', href: '/auftraege/p-timm', when: 'Freitag', fresh: false },
      { text: 'Petersen, Ann-Kathrin · Vertrag unterschrieben', href: '/auftraege/p-petersen', when: 'Donnerstag', fresh: false },
      { text: 'Lembcke, Pia · Projekt gebucht', href: '/auftraege/p-lembcke', when: '15.09.', fresh: false },
      { text: 'Brandt, Familie · Anfrage eingegangen', href: '/auftraege/p-brandt', when: '12.09.', fresh: false },
    ],
    tasks: [],
    openTasks: 0,
    todo: [],
    dunning: [],
    leads: [
      { key: 'unbeantwortet', rows: [lead('l-brandt', 'Familie Brandt', 4)] },
      { key: 'angebot', rows: [lead('l-petersen', 'Petersen, Ann-Kathrin', 26), lead('l-timm', 'Timm, Merle', 9)] },
      { key: 'angenommen', rows: [] },
    ],
    projects: [
      { key: 'haengt', rows: [project('p-ahrens', 'Ahrens, Sophie & Tim', '2026-09-05', 'Fotografiert', 'status', 17)] },
      { key: 'woche', rows: [] },
      { key: 'bearbeitung', rows: [project('p-voss', 'Voss, Katharina & Jan', '2026-09-01', 'Bearbeitung', null, null)] },
      { key: 'spaeter', rows: [project('p-lembcke', 'Lembcke, Pia', '2027-05-22', 'Gebucht', null, null)] },
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
    pipeline: { total: 1278000, priorYear: null },
    payments: { open: 1330000, overdue: 85200, overdueInstalments: 1, instalments: 3, overdueSinceDays: 11, nextDueOn: null, nextAmount: null },
    activity: [
      { text: 'Rahn, Marie & Jonas · Zahlung erhalten', href: '/auftraege/p-rahn', when: 'heute, 08:40', fresh: true },
      { text: 'Söhnke, Britta & Nils · Anfrage eingegangen', href: '/auftraege/p-soehnke', when: 'gestern, 16:40', fresh: true },
      { text: 'Voss, Katharina & Jan · Galerie geöffnet', href: '/auftraege/p-voss', when: 'Donnerstag', fresh: false },
      { text: 'Brandt, Familie · Anfrage eingegangen', href: '/auftraege/p-brandt', when: '08.09.', fresh: false },
      { text: 'Ahrens, Sophie & Tim · Angebot angenommen', href: '/auftraege/p-ahrens', when: '07.09.', fresh: false },
      { text: 'Lembcke, Pia · Galerie geöffnet', href: '/auftraege/p-lembcke', when: '05.09.', fresh: false },
    ],
    tasks: [
      task('t-1', 'p-rahn', 'Rahn, Marie & Jonas', 'Ablaufplan schicken', '2026-09-10', true),
      task('t-2', 'p-petersen', 'Petersen, Ann-Kathrin', 'Galerie freigeben', '2026-09-16', false),
    ],
    openTasks: 6,
    todo: [
      row('Familie Brandt', 'Anfrage seit', { emphasis: '4 Tagen', action: { label: 'Angebot schreiben', kind: 'link', target: '/angebote/neu?projekt=p-brandt' } }),
    ],
    dunning: [
      row('Ahrens, Sophie & Tim', 'Stripe mahnt', { emphasis: 'seit 11 Tagen', whatTail: ' · 852 €', action: { label: 'Anrufen', kind: 'tel', target: 'tel:+49451123456' } }),
    ],
    galleries: [
      row('Voss, Katharina & Jan', 'Auswahl offen', { whatTail: ' · 312 Bilder' }),
      row('Lembcke, Pia', 'Läuft ab am', { emphasis: '28.09.' }),
    ],
  },
}

/** Der erste Morgen: keine Termine, keine Historie, kein Verlauf. */
export const Tag1: Story = {
  args: {
    today: '2026-09-14',
    entries: [],
    pipeline: { total: 0, priorYear: null },
    payments: { open: 0, overdue: 0, overdueInstalments: 0, instalments: 0, overdueSinceDays: null, nextDueOn: null, nextAmount: null },
    quotes: { sent: 0, accepted: 0, priorSent: null, priorAccepted: null },
    shoots: { count: 0, priorYear: null },
    activity: [],
    tasks: [],
    openTasks: 0,
    todo: [],
    dunning: [],
    leads: [],
    projects: [],
    galleries: [],
  },
  parameters: { page: { nav: { leads: 0, unread: 0, projects: 0 } } },
}
