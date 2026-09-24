import type { Meta, StoryObj } from '@storybook/vue3-vite'
import AdminLayout from './AdminLayout.vue'

/*
 * Die Leiste, an der jeder Bildschirm hängt. Was sie zeigt, hängt an zwei
 * Werten: den Befugnissen der Rolle und der aktiven Zeile — und die aktive
 * Zeile entscheidet auch, welcher Zweig offen steht. Beides lässt sich im
 * Betrieb nur durch einen Rollenwechsel und einen Seitenwechsel sehen,
 * deshalb steht es hier nebeneinander.
 */
const alle: App.Enums.Capability[] = [
  'manage_leads',
  'manage_contacts',
  'view_all_cases',
  'book_project',
  'manage_shoots',
  'manage_crew',
  'manage_tasks',
  'manage_notes',
  'manage_messages',
  'manage_staff',
  'manage_packages',
  'manage_portal_access',
  'manage_quotes',
  'manage_contracts',
  'manage_settings',
  'manage_form_templates',
  'manage_dsgvo',
]

const user = {
  id: 'u1',
  name: 'Christian Zepter',
  email: 'christian@atelier-nordlicht.de',
}

const seite = (can: App.Enums.Capability[]) => ({ auth: { user, can } })

const meta = {
  title: 'Struktur/Navigation',
  component: AdminLayout,
  parameters: { layout: 'fullscreen', page: seite(alle) },
  args: { active: 'uebersicht' },
  render: (args) => ({
    components: { AdminLayout },
    setup: () => ({ args }),
    template: `
      <AdminLayout v-bind="args">
        <div class="p-10 text-[13px] text-ink-2">Bildschirm</div>
      </AdminLayout>
    `,
  }),
} satisfies Meta<typeof AdminLayout>

export default meta

type Story = StoryObj<typeof meta>

/** Alle Befugnisse, nichts aufgeklappt. */
export const Zu: Story = {}

/** Wer in den Unterlagen steht, sieht den Zweig offen. */
export const UnterlagenOffen: Story = {
  args: { active: 'rechnungen' },
}

/** Dasselbe für Studio, das sonst zugeklappt bleibt. */
export const StudioOffen: Story = {
  args: { active: 'einstellungen' },
}

/**
 * Ein Fotograf ohne Verwaltungsrechte: kein Studio-Block, keine Unterlagen,
 * keine Anfragen.
 */
export const OhneVerwaltung: Story = {
  parameters: {
    page: seite(['manage_shoots', 'manage_tasks', 'manage_notes', 'manage_messages']),
  },
}
