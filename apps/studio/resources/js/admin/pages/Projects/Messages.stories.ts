import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Messages from './Messages.vue'
import { header } from '../__stories__/fixtures'

const von = (
    row: Pick<App.Engagement.Data.MessageData, 'id' | 'author' | 'fromStaff' | 'body' | 'sentAt' | 'deliveryState'>,
): App.Engagement.Data.MessageData => ({
    projectName: header.name,
    projectId: header.id,
    scheduledFor: null,
    ...row,
})

const studio = von({
    id: 'm-r1',
    author: 'Christian Zepter',
    fromStaff: true,
    body: 'Hallo Jana, der Ablauf steht. Ich bin ab 13:30 auf Gut Basthorst, die Trauung beginnt um 14:30.',
    sentAt: '2026-08-24T09:30:00+02:00',
    deliveryState: 'delivered',
})

const kundin = von({
    id: 'm-r2',
    author: 'Jana Rahn',
    fromStaff: false,
    body: 'Passt. Meine Mutter kommt schon um 12 Uhr zum Getting Ready dazu, ist das ein Problem?',
    sentAt: '2026-08-25T21:06:00+02:00',
    deliveryState: null,
})

const antwort = von({
    id: 'm-r3',
    author: 'Christian Zepter',
    fromStaff: true,
    body: 'Überhaupt nicht, ich bin ab 12 Uhr im Zimmer. Ablauf als PDF kommt morgen.',
    sentAt: '2026-08-26T08:15:00+02:00',
    deliveryState: 'delivered',
})

const thread: App.Engagement.Data.ThreadData = {
    id: 'th-rahn',
    subject: 'Hochzeit am 13.09.2026',
    recipients: ['jana.rahn@posteo.de', 'michael.rahn@posteo.de'],
    messages: [studio, kundin],
}

const mailTemplates: App.Documents.Data.MailTemplateData[] = [
    {
        id: 'mt-ablauf',
        name: 'Ablauf zum Termin',
        subject: null,
        body: '<p>Hallo Jana,</p><p>anbei der Ablauf für den 13.09.2026 auf Gut Basthorst.</p>',
        unknownFields: [],
    },
    {
        id: 'mt-galerie',
        name: 'Galerie online',
        subject: null,
        body: '<p>Hallo Jana,</p><p>eure Galerie steht bereit: {{ gallery.link }}</p>',
        unknownFields: [],
    },
]

const meta = {
    title: 'Bildschirme/Auftrag Nachrichten',
    component: Messages,
    parameters: { layout: 'fullscreen', url: `/auftraege/${header.id}/nachrichten` },
    args: { header, thread, mailTemplates },
} satisfies Meta<typeof Messages>

export default meta
type Story = StoryObj<typeof meta>

/** Die Kundin hat zuletzt geschrieben, die Antwort steht aus. */
export const AntwortOffen: Story = {}

/** Das Studio hat zuletzt geschrieben. */
export const StudioZuletzt: Story = {
    args: { thread: { ...thread, messages: [studio, kundin, antwort] } },
}

/** Noch keine Nachricht in diesem Auftrag. */
export const Leer: Story = {
    args: { thread: null },
}
