import type { Meta, StoryObj } from '@storybook/vue3-vite'
import StageTrack from './StageTrack.vue'

const labels: [string, string][] = [
  ['lead', 'Neu'],
  ['lead', 'Kontaktiert'],
  ['lead', 'Angebot'],
  ['lead', 'Angebot angenommen'],
  ['lead', 'Vertrag'],
  ['project', 'Gebucht'],
  ['project', 'Vorbereitung'],
  ['project', 'Fotografiert'],
  ['project', 'Auswahl'],
  ['project', 'Bearbeitung'],
  ['project', 'Fertig'],
  ['project', 'Geliefert'],
  ['project', 'Abgeschlossen'],
]

const track = (at: number) =>
  labels.map(([pipeline, label], index) => ({
    key: label,
    label,
    pipeline,
    state: index < at ? 'done' : index === at ? 'current' : 'todo',
  }))

const meta = {
  title: 'Muster/Stufenleiste',
  component: StageTrack,
  args: { steps: track(2) },
  decorators: [() => ({ template: '<div style="width: 578px"><story /></div>' })],
} satisfies Meta<typeof StageTrack>

export default meta
type Story = StoryObj<typeof meta>

export const Anfrage: Story = {}

export const Projekt: Story = { args: { steps: track(9) } }

export const Abgeschlossen: Story = { args: { steps: track(12) } }

export const Telefon: Story = {
  decorators: [() => ({ template: '<div style="width: 350px"><story /></div>' })],
  args: { steps: track(3) },
}
