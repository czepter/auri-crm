import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Switch } from '.'
import { Label } from '@/ui/label'

/*
 * An und aus statt angehakt und nicht angehakt: was der Schalter umlegt,
 * gilt sofort, ohne Formular darunter.
 */
const meta = {
  title: 'Formular/Switch',
  component: Switch,
  argTypes: {
    modelValue: { control: 'boolean' },
    disabled: { control: 'boolean' },
    id: { control: 'text' },
  },
  args: { modelValue: false, disabled: false, id: 'sichtbar' },
  render: (args) => ({
    components: { Switch, Label },
    setup: () => ({ args }),
    template: `
      <div class="cbrow">
        <Switch v-bind="args" />
        <Label variant="inline" for="sichtbar">{{ $t('contacts.portalKey.portal_visible') }}</Label>
      </div>
    `,
  }),
} satisfies Meta<typeof Switch>

export default meta
type Story = StoryObj<typeof meta>

/** Aus. */
export const Standard: Story = {}

/** An — die Wirkung tritt unter dem Zeigefinger ein. */
export const An: Story = { args: { modelValue: true } }

/** Die Stellung steht fest, solange etwas anderes sie hält. */
export const Deaktiviert: Story = { args: { modelValue: true, disabled: true } }

export const AlleZustaende: Story = {
  parameters: { layout: 'padded' },
  render: () => ({
    components: { Switch, Label },
    setup: () => ({
      zeilen: [
        { key: 'portal_visible', value: false, disabled: false, name: 'aus' },
        { key: 'payment_status', value: true, disabled: false, name: 'an' },
        { key: 'gallery_downloads', value: true, disabled: true, name: 'disabled' },
      ] as const,
    }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <div v-for="z in zeilen" :key="z.key" class="cbrow">
          <Switch :model-value="z.value" :disabled="z.disabled" />
          <Label variant="inline">{{ $t('contacts.portalKey.' + z.key) }}</Label>
          <code style="font-size: 11px; color: var(--muted);">{{ z.name }}</code>
        </div>
      </div>
    `,
  }),
}
