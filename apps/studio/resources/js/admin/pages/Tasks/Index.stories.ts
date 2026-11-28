import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const AUFGABEN: App.Crm.Data.TaskRowData[] = [
  {
    id: 't1', projectId: 'p1', projectName: 'Hochzeit Brandt', title: 'Ablaufplan mit Lena durchgehen',
    dueOn: '2026-09-10', isOverdue: true, isDone: false, shootId: 's1', shootLabel: 'Trauung Gut Basthorst',
    audience: 'studio', audienceLabel: 'Studio', isBlocked: false, blockedBy: null, completedOn: null,
    dueAnchorLabel: null, assigneeId: 'u1', assigneeName: 'Christian Zepter', contactId: null, contactName: null, threadId: null,
  },
  {
    id: 't2', projectId: 'p1', projectName: 'Hochzeit Brandt', title: 'Zweitfotograf bestätigen',
    dueOn: '2026-09-19', isOverdue: false, isDone: false, shootId: 's1', shootLabel: 'Trauung Gut Basthorst',
    audience: 'studio', audienceLabel: 'Studio', isBlocked: true, blockedBy: 'Angebot angenommen', completedOn: null,
    dueAnchorLabel: null, assigneeId: null, assigneeName: null, contactId: null, contactName: null, threadId: null,
  },
  {
    id: 't3', projectId: 'p2', projectName: 'Businessporträts Nordwerk', title: 'Auswahl an Kerstin Voß schicken',
    dueOn: null, isOverdue: false, isDone: false, shootId: null, shootLabel: null,
    audience: 'studio', audienceLabel: 'Studio', isBlocked: false, blockedBy: null, completedOn: null,
    dueAnchorLabel: 'Nach dem Shooting', assigneeId: 'u2', assigneeName: 'Marit Ahrens', contactId: null, contactName: null, threadId: null,
  },
  {
    id: 't4', projectId: 'p1', projectName: 'Hochzeit Brandt', title: 'Vertrag an Tom Brandt senden',
    dueOn: '2026-08-30', isOverdue: false, isDone: true, shootId: null, shootLabel: null,
    audience: 'studio', audienceLabel: 'Studio', isBlocked: false, blockedBy: null, completedOn: '2026-08-29',
    dueAnchorLabel: null, assigneeId: 'u1', assigneeName: 'Christian Zepter', contactId: null, contactName: null, threadId: null,
  },
  {
    id: 't5', projectId: null, projectName: null, title: 'Datenschutz-Anfrage: Datenkopie',
    dueOn: '2026-10-14', isOverdue: false, isDone: false, shootId: null, shootLabel: null,
    audience: 'internal', audienceLabel: 'Intern', isBlocked: false, blockedBy: null, completedOn: null,
    dueAnchorLabel: null, assigneeId: 'u1', assigneeName: 'Christian Zepter',
    contactId: 'c1', contactName: 'Tom Brandt', threadId: null,
  },
]

const meta = {
  title: 'Bildschirme/Aufgaben/Übersicht',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/aufgaben' },
  args: {
    tasks: AUFGABEN,
    staffOptions: [
      { value: 'u1', label: 'Christian Zepter' },
      { value: 'u2', label: 'Marit Ahrens' },
    ],
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Der Reiter „Meine“ mit einer überfälligen Aufgabe. */
export const Meine: Story = {}

/** Ein Studio ohne Team: der Reiter „Meine“ und die Zuständigkeit fallen weg. */
export const SoloStudio: Story = {
  parameters: { page: { soloStudio: true }, url: '/aufgaben' },
  args: { staffOptions: [] },
}

/** Nichts offen — der Zustand, in dem der Bildschirm die meiste Zeit steht. */
export const Leer: Story = { args: { tasks: [] } }
