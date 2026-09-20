import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Absences from './Absences.vue'

const staff: App.Data.StaffProfileData[] = [
  { id: 'u1', name: 'Christian Zepter', email: 'christian@atelier-nordlicht.de', role: 'owner', mfaConfirmed: true, lastQuoteAction: null },
  { id: 'u2', name: 'Marie Ohlsen', email: 'marie@atelier-nordlicht.de', role: 'second_shooter', mfaConfirmed: false, lastQuoteAction: null },
]

const meta = {
  title: 'Bildschirme/Abwesenheiten',
  component: Absences,
  parameters: { layout: 'fullscreen', url: '/abwesenheiten' },
  args: {
    canManageCrew: true,
    staff,
    absences: [
      {
        id: 'a1',
        userId: 'u1',
        userName: 'Christian Zepter',
        category: 'holiday',
        categoryLabel: 'Urlaub',
        startsOn: '2026-10-05',
        endsOn: '2026-10-16',
        canManage: true,
      },
      {
        id: 'a2',
        userId: 'u2',
        userName: 'Marie Ohlsen',
        category: 'holiday',
        categoryLabel: 'Urlaub',
        startsOn: '2026-09-21',
        endsOn: '2026-09-25',
        canManage: true,
      },
    ],
  },
} satisfies Meta<typeof Absences>

export default meta
type Story = StoryObj<typeof meta>

/** Zwei eingetragene Abwesenheiten, beide änderbar. */
export const Eingetragen: Story = {}

/** Ein Studio aus einer Person: keine Namensspalte, nichts eingetragen. */
export const EinePerson: Story = {
  parameters: { page: { soloStudio: true } },
  args: { absences: [], staff: [staff[0]] },
}

/** Ein frisch eingerichtetes Studio: nichts eingetragen. */
export const Leer: Story = { args: { absences: [] } }
