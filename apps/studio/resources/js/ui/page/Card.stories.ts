import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import Card from './Card.vue'

/*
 * Die Zusammenfassung sagt zugeklappt, was drinsteht — eine Karte ohne sie
 * zwingt zum Öffnen, um zu erfahren, ob sich das Öffnen lohnt. Der
 * Bearbeiten-Zustand gehört dem Aufrufer: nur er weiß, ob sein Request
 * durchging, also schließt auch nur er die Felder wieder.
 */
const meta = {
  title: 'Karte/Card',
  component: Card,
  parameters: { layout: 'padded' },
  args: {
    title: 'Termine',
    summary: '2 Termine · ab 11.06.2027',
    defaultOpen: true,
    editable: false,
  },
  render: (args) => ({
    components: { Card },
    setup: () => ({ args }),
    template: `
      <div style="width: 680px; max-width: 100%;">
        <Card v-bind="args">
          <div style="display: flex; flex-direction: column; gap: 2px;">
            <div>11.06.2027 · Standesamt Hamburg · 11:00</div>
            <div>13.09.2026 · Gut Basthorst · 14:30</div>
          </div>
        </Card>
      </div>
    `,
  }),
} satisfies Meta<typeof Card>

export default meta
type Story = StoryObj<typeof meta>

/** Der Normalfall: offen, mit Leseansicht unter dem Titel. */
export const Lesen: Story = {}

/** Für alles, was auf dem Fall liegt, aber gerade nicht dran ist. */
export const Zugeklappt: Story = { args: { defaultOpen: false } }

/** Der `edit`-Slot ersetzt die Leseansicht, nicht ergänzt sie. */
export const Bearbeiten: Story = {
  args: { editable: true, editing: true },
  render: (args) => ({
    components: { Card, Field, Input },
    setup: () => ({ args }),
    template: `
      <div style="width: 680px; max-width: 100%;">
        <Card v-bind="args">
          <template #edit>
            <div style="display: flex; gap: 14px;">
              <Field dense label="Trauung" v-slot="{ id }">
                <Input :id="id" type="date" default-value="2027-06-11" />
              </Field>
              <Field dense label="Feier" v-slot="{ id }">
                <Input :id="id" type="date" default-value="2026-09-13" />
              </Field>
            </div>
          </template>
        </Card>
      </div>
    `,
  }),
}

/** Ein Status, der zur Karte gehört und nicht in die Zusammenfassung passt. */
export const MitAktion: Story = {
  render: (args) => ({
    components: { Card },
    setup: () => ({ args }),
    template: `
      <div style="width: 680px; max-width: 100%;">
        <Card v-bind="args">
          <template #action>
            <span style="border-radius: var(--r-sm); background: var(--sunk-2); padding: 3px 8px; font-size: 10px; line-height: 1.5; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;">Gebucht</span>
          </template>
          <div>13.09.2026 · Gut Basthorst · 14:30</div>
        </Card>
      </div>
    `,
  }),
}

/** Ohne Zusammenfassung trägt die Kopfzeile nur den Titel. */
export const OhneZusammenfassung: Story = { args: { summary: null } }

export const AlleZustaende: Story = {
  render: () => ({
    components: { Card, Field, Input },
    setup: () => ({
      zeilen: [
        '11.06.2027 · Standesamt Hamburg · 11:00',
        '13.09.2026 · Gut Basthorst · 14:30',
      ],
    }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 18px; width: 680px; max-width: 100%;">
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <code style="font-size: 11px; color: var(--muted);">offen</code>
          <Card title="Termine" summary="2 Termine · ab 11.06.2027">
            <div v-for="zeile in zeilen" :key="zeile">{{ zeile }}</div>
          </Card>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px;">
          <code style="font-size: 11px; color: var(--muted);">zugeklappt</code>
          <Card title="Termine" summary="2 Termine · ab 11.06.2027" :default-open="false">
            <div v-for="zeile in zeilen" :key="zeile">{{ zeile }}</div>
          </Card>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px;">
          <code style="font-size: 11px; color: var(--muted);">editable</code>
          <Card title="Termine" summary="2 Termine · ab 11.06.2027" editable>
            <div v-for="zeile in zeilen" :key="zeile">{{ zeile }}</div>
          </Card>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px;">
          <code style="font-size: 11px; color: var(--muted);">editing</code>
          <Card title="Termine" summary="2 Termine · ab 11.06.2027" editable :editing="true">
            <template #edit>
              <div style="display: flex; gap: 14px;">
                <Field dense label="Trauung" v-slot="{ id }">
                  <Input :id="id" type="date" default-value="2027-06-11" />
                </Field>
                <Field dense label="Feier" v-slot="{ id }">
                  <Input :id="id" type="date" default-value="2026-09-13" />
                </Field>
              </div>
            </template>
          </Card>
        </div>
      </div>
    `,
  }),
}
