import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Button } from '@/ui/button'
import Section from './Section.vue'

/*
 * Im Abbau: die Fallbildschirme tragen ihre Teile inzwischen in `Card`, mit
 * Zusammenfassung und Bearbeiten-Zustand. Section steht nur noch auf den
 * Bildschirmen, die nicht umgestellt sind — Titel links, Inhalt rechts, ab
 * 1180px nebeneinander.
 */
const meta = {
  title: 'Struktur/Section',
  component: Section,
  parameters: { layout: 'padded' },
  args: { title: 'Termine' },
  render: (args) => ({
    components: { Section },
    setup: () => ({ args }),
    template: `
      <div style="width: 900px; max-width: 100%;">
        <Section v-bind="args">
          <div>11.06.2027 · Standesamt Hamburg · 11:00</div>
          <div>13.09.2026 · Gut Basthorst · 14:30</div>
        </Section>
      </div>
    `,
  }),
} satisfies Meta<typeof Section>

export default meta
type Story = StoryObj<typeof meta>

/** Was auf einem noch nicht umgestellten Bildschirm steht. */
export const Rest: Story = {}

/** Der `actions`-Slot über dem Inhalt — in einer Karte wäre das der Kopf. */
export const MitAktionen: Story = {
  render: (args) => ({
    components: { Section, Button },
    setup: () => ({ args }),
    template: `
      <div style="width: 900px; max-width: 100%;">
        <Section v-bind="args">
          <template #actions>
            <Button variant="outline" type="button">Termin hinzufügen</Button>
          </template>
          <div>11.06.2027 · Standesamt Hamburg · 11:00</div>
          <div>13.09.2026 · Gut Basthorst · 14:30</div>
        </Section>
      </div>
    `,
  }),
}
