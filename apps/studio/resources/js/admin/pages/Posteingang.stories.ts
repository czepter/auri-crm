import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Posteingang from './Posteingang.vue'

const meta = {
  title: 'Bildschirme/Posteingang',
  component: Posteingang,
  parameters: { layout: 'fullscreen', url: '/posteingang' },
  args: {
    drafts: [
      {
        id: 'ar-run-1',
        subject: 'Ihre Galerie ist bald nicht mehr erreichbar',
        body: 'Hallo Marie,\n\ndie Galerie zu Rahn, Marie & Jonas ist noch bis zum 30.09.2026 online.',
        projectName: 'Rahn, Marie & Jonas',
        to: 'Marie Rahn',
      },
    ],
    groups: [
      {
        key: 'unbeantwortet',
        rows: [
          {
            id: 'th-brandt',
            who: 'Familie Brandt',
            project: null,
            subject: 'Trauung am 12.06.2027',
            snippet: 'Wir heiraten am 12. Juni 2027 in Lübeck und suchen noch jemanden für den ganzen Tag.',
            waitingDays: 4,
            origin: 'client',
          },
          {
            id: 'th-rahn',
            who: 'Marie Rahn',
            project: 'Rahn, Marie & Jonas',
            subject: 'Ablauf Gut Basthorst',
            snippet: 'Der Sektempfang wandert nach hinten, Trauung jetzt 15:30.',
            waitingDays: 1,
            origin: 'client',
          },
        ],
      },
      {
        key: 'wartet',
        rows: [
          {
            id: 'th-basthorst',
            who: 'Gut Basthorst',
            project: 'Rahn, Marie & Jonas',
            subject: 'Anfahrt und Stellplatz',
            snippet: 'Zufahrt über das Hoftor, Stellplatz hinter der Scheune.',
            waitingDays: 6,
            origin: 'venue',
          },
        ],
      },
    ],
    open: {
      id: 'th-brandt',
      subject: 'Trauung am 12.06.2027',
      state: 'offen',
      task: null,
      projectId: 'p-brandt',
      projectName: 'Brandt, Familie',
      shootDate: '2027-06-12T11:00:00+02:00',
      origin: 'client',
      people: ['Familie Brandt', 'Christian Zepter'],
      messages: [
        {
          id: 'm-1',
          who: 'Familie Brandt',
          own: false,
          body: 'Wir heiraten am 12. Juni 2027 in Lübeck und suchen noch jemanden für den ganzen Tag. Standesamt mittags, Feier ab 17 Uhr.',
          at: '2026-09-08T09:12:00+02:00',
        },
        {
          id: 'm-2',
          who: 'Christian Zepter',
          own: true,
          body: 'Der 12.06.2027 ist bei mir frei. Ich schicke Ihnen bis Freitag ein Angebot über den ganzen Tag.',
          at: '2026-09-08T17:40:00+02:00',
        },
      ],
    },
    configured: true,
    catchAll: 'post+7f3a91@atelier-nordlicht.de',
    canRotate: true,
    pending: [
      {
        id: 'pm-1',
        from: 'floristik@heide-blumen.de',
        to: 'post+7f3a91@atelier-nordlicht.de',
        subject: 'Blumen Gut Basthorst 13.09.',
        body: 'Wir liefern die Tischgestecke um 11 Uhr, der Bogen steht ab 12 Uhr.',
        state: 'pending',
        stateLabel: 'Nicht zugeordnet',
        reason: 'unknown_sender',
        receivedAt: '2026-09-11T08:05:00+02:00',
        projectName: null,
      },
    ],
    threads: [
      { id: 'th-rahn', label: 'Rahn, Marie & Jonas · Ablauf Gut Basthorst' },
      { id: 'th-basthorst', label: 'Gut Basthorst · Anfahrt und Stellplatz' },
    ],
    unfiled: [
      {
        id: 'th-soehnke',
        subject: 'Paarshooting im Herbst',
        snippet: 'Wir hätten gern Bilder im Oktober, am liebsten an der Elbe.',
        startedAt: '2026-09-10T19:22:00+02:00',
      },
    ],
    staffOptions: [{ value: 'u1', label: 'Christian Zepter' }],
    projects: [
      { id: 'p-rahn', label: 'Rahn, Marie & Jonas' },
      { id: 'p-ahrens', label: 'Ahrens, Sophie & Tim' },
      { id: 'p-brandt', label: 'Brandt, Familie' },
    ],
  },
} satisfies Meta<typeof Posteingang>

export default meta
type Story = StoryObj<typeof meta>

/** Zwei Fäden warten auf Antwort, einer ist offen, und eine Mail hängt noch an keinem. */
export const WartetAufAntwort: Story = {}

/** Alles beantwortet: ohne Faden fällt die Warteschlange weg und nur die Adresse bleibt. */
export const LeererEingang: Story = {
  args: {
    groups: [],
    open: null,
    pending: [],
    unfiled: [],
    threads: [],
  },
}
