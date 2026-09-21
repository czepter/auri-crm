import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const stages = [
    { key: 'booked', name: 'Gebucht', phase: 'upcoming' },
    { key: 'shot', name: 'Fotografiert', phase: 'editing' },
    { key: 'culling', name: 'Auswahl', phase: 'editing' },
    { key: 'editing', name: 'Bearbeitung', phase: 'editing' },
    { key: 'ready', name: 'Fertig', phase: 'editing' },
    { key: 'delivered', name: 'Übergeben', phase: 'delivered' },
]

const projects: App.Crm.Data.ProjectRowData[] = [
    {
        id: 'p-rahn',
        type: 'Hochzeit',
        typeKey: 'wedding',
        name: 'Hochzeit Rahn',
        date: '2026-09-13',
        venue: 'Gut Basthorst',
        position: 'Gebucht',
        positionKey: 'booked',
        phase: 'upcoming',
        contact: 'Jana Rahn',
        openTasks: 2,
        taskCount: 3,
        value: 420000,
        lastAt: '2026-07-02T09:00:00+02:00',
    },
    {
        id: 'p-osterhoff',
        type: 'Porträt',
        typeKey: 'portrait',
        name: 'Porträt Osterhoff',
        date: '2026-10-04',
        venue: 'Atelier',
        position: 'Gebucht',
        positionKey: 'booked',
        phase: 'upcoming',
        contact: 'Silke Osterhoff',
        openTasks: 1,
        taskCount: 1,
        value: 68000,
        lastAt: '2026-08-20T09:00:00+02:00',
    },
    {
        id: 'p-ahrens',
        type: 'Hochzeit',
        typeKey: 'wedding',
        name: 'Hochzeit Ahrens & Kroll',
        date: '2026-07-25',
        venue: 'Kaispeicher, Hamburg',
        position: 'Bearbeitung',
        positionKey: 'editing',
        phase: 'editing',
        contact: 'Marie Ahrens',
        openTasks: 3,
        taskCount: 6,
        value: 380000,
        lastAt: '2026-07-27T09:00:00+02:00',
    },
    {
        id: 'p-lund',
        type: 'Familie',
        typeKey: 'portrait',
        name: 'Familie Lund',
        date: '2026-06-06',
        venue: 'Elbstrand',
        position: 'Auswahl',
        positionKey: 'culling',
        phase: 'editing',
        contact: 'Anke Lund',
        openTasks: 1,
        taskCount: 4,
        value: 54000,
        lastAt: '2026-06-08T09:00:00+02:00',
    },
    {
        id: 'p-wendt',
        type: 'Business',
        typeKey: 'portrait',
        name: 'Business Wendt',
        date: '2026-04-18',
        venue: 'Kontor Altona',
        position: 'Übergeben',
        positionKey: 'delivered',
        phase: 'delivered',
        contact: 'Jörg Wendt',
        openTasks: 0,
        taskCount: 2,
        value: null,
        lastAt: '2026-04-20T09:00:00+02:00',
    },
]

const meta = {
    title: 'Bildschirme/Projektliste',
    component: Index,
    parameters: { layout: 'fullscreen', url: '/auftraege' },
    args: { view: 'liste', projects, stages, stageSettings: null, settingsSelection: null, returnTo: null, workflow: null },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Laufende und übergebene Projekte über alle Phasen. */
export const Liste: Story = {}

/** Dieselben Projekte als Spalten nach Stufe. */
export const Board: Story = { args: { view: 'board' } }

/** Studio ohne Projekte. */
export const Leer: Story = {
    args: { projects: [] },
}
