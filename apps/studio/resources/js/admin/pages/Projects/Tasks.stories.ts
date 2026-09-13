import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Tasks from './Tasks.vue'
import { header, tasks } from '../__stories__/fixtures'

const meta = {
    title: 'Bildschirme/Aufgaben',
    component: Tasks,
    parameters: { layout: 'fullscreen', url: `/auftraege/${header.id}/aufgaben` },
    args: {
        header,
        tasks,
        shootOptions: [{ value: 's-rahn', label: 'Hochzeit · 13.09.2026' }],
        staffOptions: [{ value: 'u1', label: 'Christian Zepter' }],
        anchors: [
            { value: 'shoot', label: 'Shooting', group: 'Termine', on: '2026-09-13' },
            { value: 'delivery', label: 'Übergabe', group: 'Termine', on: null },
        ],
    },
} satisfies Meta<typeof Tasks>

export default meta
type Story = StoryObj<typeof meta>

/** Eine überfällige, eine anstehende, eine abgehakte Aufgabe. */
export const Offen: Story = {}

/** Nichts steht mehr aus. */
export const AllesErledigt: Story = {
    args: {
        tasks: tasks.map((task) => ({ ...task, isDone: true, isOverdue: false, completedOn: '2026-09-10' })),
    },
}

/** Auftrag ohne Aufgaben. */
export const Keine: Story = {
    args: { tasks: [] },
}
