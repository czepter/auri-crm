import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Label } from '.'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'

/*
 * Zwei Beschriftungen und ein Satz: field und micro stehen über einem Feld,
 * inline daneben — und ist dann nicht Beschriftung, sondern das Geschaltete.
 */
const meta = {
  title: 'Formular/Label',
  component: Label,
  argTypes: {
    variant: { control: 'inline-radio', options: ['field', 'inline', 'micro'] },
    for: { control: 'text' },
  },
  args: { variant: 'field', for: 'projektname' },
  render: (args) => ({
    components: { Label },
    setup: () => ({ args }),
    template: '<Label v-bind="args">Projektname</Label>',
  }),
} satisfies Meta<typeof Label>

export default meta
type Story = StoryObj<typeof meta>

/** Über dem Feld im Formular — auch wenn das Feld gefüllt ist. */
export const Feld: Story = {
  render: (args) => ({
    components: { Label, Input },
    setup: () => ({ args }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 6px; width: 280px;">
        <Label v-bind="args">{{ $t('leads.projectName') }}</Label>
        <Input id="projektname" model-value="Hochzeit Rahn" />
      </div>
    `,
  }),
}

/** Die Kennzeichnung, die hinter der Beschriftung mitläuft, ohne mit ihr zu schreien. */
export const FeldOptional: Story = {
  render: (args) => ({
    components: { Label, Input },
    setup: () => ({ args }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 6px; width: 280px;">
        <Label v-bind="args">{{ $t('project.venueName') }} <em>optional</em></Label>
        <Input id="projektname" model-value="Gut Basthorst" />
      </div>
    `,
  }),
}

/** Auf den Admin-Screens, wo das Feld darunter dichter sitzt. */
export const Micro: Story = {
  args: { variant: 'micro' },
  render: (args) => ({
    components: { Label, Input },
    setup: () => ({ args }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 4px; width: 280px;">
        <Label v-bind="args">{{ $t('leads.contact') }}</Label>
        <Input id="projektname" size="sm" model-value="Lena Brandt" />
      </div>
    `,
  }),
}

/** Neben Checkbox oder Radio: der Satz, den das Kästchen schaltet. */
export const Inline: Story = {
  args: { variant: 'inline', for: 'galerie' },
  render: (args) => ({
    components: { Label, Checkbox },
    setup: () => ({ args }),
    template: `
      <div class="cbrow">
        <Checkbox id="galerie" :default-value="true" />
        <Label v-bind="args">{{ $t('contacts.portalKey.gallery_downloads') }}</Label>
      </div>
    `,
  }),
}

export const AlleVarianten: Story = {
  parameters: { layout: 'padded' },
  render: () => ({
    components: { Label, Input, Checkbox },
    template: `
      <div style="display: flex; flex-direction: column; gap: 22px; width: 300px;">
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <Label variant="field">{{ $t('leads.projectName') }} <em>optional</em></Label>
          <Input model-value="Hochzeit Rahn" />
          <code style="font-size: 11px; color: var(--muted);">field</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 4px;">
          <Label variant="micro">{{ $t('leads.contact') }}</Label>
          <Input size="sm" model-value="Lena Brandt" />
          <code style="font-size: 11px; color: var(--muted);">micro</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <div class="cbrow">
            <Checkbox :default-value="true" />
            <Label variant="inline">{{ $t('contacts.portalKey.gallery_downloads') }}</Label>
          </div>
          <code style="font-size: 11px; color: var(--muted);">inline</code>
        </div>
      </div>
    `,
  }),
}
