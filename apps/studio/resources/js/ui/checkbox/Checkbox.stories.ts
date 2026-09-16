import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Checkbox } from '.'
import { Label } from '@/ui/label'

/*
 * Das Kästchen steht selten allein: die Zeile aus Kästchen und Satz ist die
 * Form, in der es im Produkt vorkommt (forms.css .cbrow).
 */
const meta = {
  title: 'Formular/Checkbox',
  component: Checkbox,
  argTypes: {
    modelValue: { control: 'inline-radio', options: [false, true, 'indeterminate'] },
    disabled: { control: 'boolean' },
    id: { control: 'text' },
  },
  args: { modelValue: false, disabled: false, id: 'galerie' },
  render: (args) => ({
    components: { Checkbox, Label },
    setup: () => ({ args }),
    template: `
      <div class="cbrow">
        <Checkbox v-bind="args" />
        <Label variant="inline" for="galerie">{{ $t('contacts.portalKey.gallery_downloads') }}</Label>
      </div>
    `,
  }),
} satisfies Meta<typeof Checkbox>

export default meta
type Story = StoryObj<typeof meta>

/** Eine Einstellung, die aus ist, bis jemand sie anhakt. */
export const Standard: Story = {}

/** An. */
export const Angehakt: Story = { args: { modelValue: true } }

/** Über einer Liste, von der ein Teil angehakt ist. */
export const Unbestimmt: Story = { args: { modelValue: 'indeterminate' } }

/** Der Haken steht fest, solange etwas anderes ihn hält. */
export const Deaktiviert: Story = { args: { modelValue: true, disabled: true } }

export const AlleZustaende: Story = {
  parameters: { layout: 'padded' },
  render: () => ({
    components: { Checkbox, Label },
    setup: () => ({
      zeilen: [
        { key: 'accept_quotes', value: false, disabled: false, name: 'aus' },
        { key: 'payment_status', value: true, disabled: false, name: 'an' },
        { key: 'gallery_lists', value: 'indeterminate', disabled: false, name: 'indeterminate' },
        { key: 'gallery_comments', value: true, disabled: true, name: 'disabled' },
      ] as const,
    }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <div v-for="z in zeilen" :key="z.key" class="cbrow">
          <Checkbox :model-value="z.value" :disabled="z.disabled" />
          <Label variant="inline">{{ $t('contacts.portalKey.' + z.key) }}</Label>
          <code style="font-size: 11px; color: var(--muted);">{{ z.name }}</code>
        </div>
      </div>
    `,
  }),
}
