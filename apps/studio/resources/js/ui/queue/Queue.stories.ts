import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Queue, QueueGroup, QueueItem } from '.'

/*
 * Die Liste links neben jedem Fallbildschirm: welche Fälle offen sind, in
 * ihrer Gruppe, der geöffnete markiert. Ohne `href` fragt eine Zeile die Seite
 * (`select`) statt zu navigieren — in Storybook ist das die einzige Form, die
 * klickbar ist, weil der Inertia-Router ohne Anwendung nicht läuft.
 */
const meta = {
  title: 'Struktur/Queue',
  component: Queue,
  parameters: { layout: 'fullscreen' },
  render: () => ({
    components: { Queue, QueueGroup, QueueItem },
    setup: () => ({
      diese: [
        { id: 1, name: 'Hochzeit Rahn', meta: 'in 3 Tagen', alert: true, title: 'So, 13.09.2026', snippet: 'Gut Basthorst · Ablaufplan offen', active: true },
        { id: 2, name: 'Hochzeit Wendt', meta: 'in 6 Tagen', alert: true, title: 'Sa, 04.07.2026', snippet: 'Hof Sierksdorf · Crew steht', active: false },
      ],
      spaeter: [
        { id: 3, name: 'Paarshooting Kröger', meta: null, alert: false, title: 'Fr, 20.11.2026', snippet: 'Termin bestätigt', active: false },
      ],
    }),
    template: `
      <div style="display: flex; height: 100vh; background: var(--paper);">
        <Queue>
          <QueueGroup title="Diese Woche" :count="diese.length" />
          <QueueItem
            v-for="fall in diese"
            :key="fall.id"
            :name="fall.name"
            :meta="fall.meta"
            :alert="fall.alert"
            :title="fall.title"
            :snippet="fall.snippet"
            :active="fall.active"
          />

          <QueueGroup title="Später" :count="spaeter.length" />
          <QueueItem
            v-for="fall in spaeter"
            :key="fall.id"
            :name="fall.name"
            :meta="fall.meta"
            :title="fall.title"
            :snippet="fall.snippet"
          />
        </Queue>
        <div style="flex: 1;"></div>
      </div>
    `,
  }),
} satisfies Meta<typeof Queue>

export default meta
type Story = StoryObj<typeof meta>

/** Zwei Gruppen, der geöffnete Fall markiert. */
export const Fallliste: Story = {}

/** Eine einzelne Zeile: Ruhe, Hover und markiert nebeneinander. */
export const Zeile: Story = {
  render: () => ({
    components: { Queue, QueueItem },
    template: `
      <div style="display: flex; height: 100vh; background: var(--paper);">
        <Queue>
          <QueueItem
            name="Hochzeit Rahn"
            meta="in 3 Tagen"
            title="So, 13.09.2026"
            snippet="Gut Basthorst · Ablaufplan offen"
          />
          <QueueItem
            name="Hochzeit Wendt"
            meta="überfällig"
            alert
            title="Sa, 04.07.2026"
            snippet="Hof Sierksdorf · Restzahlung offen"
          />
          <QueueItem
            name="Paarshooting Kröger"
            title="Fr, 20.11.2026"
            snippet="Termin bestätigt"
            active
          />
        </Queue>
        <div style="flex: 1;"></div>
      </div>
    `,
  }),
}
