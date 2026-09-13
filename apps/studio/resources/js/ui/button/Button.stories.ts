import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Button } from '.'

/*
 * Die Begründung je Variante steht in index.ts, wo sie auch gilt. Hier steht
 * nur, wie sie aussieht — und alle nebeneinander, damit auffällt, wenn zwei
 * sich zu ähnlich werden.
 */
const meta = {
  title: 'Basis/Button',
  component: Button,
  argTypes: {
    variant: {
      control: 'select',
      options: [
        'default',
        'outline',
        'ghost',
        'pill',
        'pill-muted',
        'portal',
        'portal-ghost',
        'portal-chip',
        'portal-nav',
        'portal-filter',
      ],
    },
    tone: { control: 'inline-radio', options: ['default', 'accent', 'destructive'] },
  },
  args: { variant: 'default', tone: 'default' },
  render: (args) => ({
    components: { Button },
    setup: () => ({ args }),
    template: '<Button v-bind="args">Angebot schreiben</Button>',
  }),
} satisfies Meta<typeof Button>

export default meta
type Story = StoryObj<typeof meta>

export const Standard: Story = {}

/** Die gefüllte Pille: eine pro Bildschirm, oben rechts neben dem Titel. */
export const Pille: Story = { args: { variant: 'pill' } }

/** Alles, was neben der Pille steht — und jedes „+ Zeile" unten in einer Karte. */
export const Outline: Story = { args: { variant: 'outline' } }

/** Zeilenaktion: trägt keine Fläche, weil die Zeile schon eine ist. */
export const Ghost: Story = { args: { variant: 'ghost' } }

/** Der eine Druck, der nicht zurückgeht: Shooting absagen, DSGVO-Daten löschen. */
export const Destruktiv: Story = { args: { tone: 'destructive' } }

export const AlleVarianten: Story = {
  parameters: { layout: 'padded' },
  render: () => ({
    components: { Button },
    setup: () => ({
      admin: ['default', 'outline', 'ghost', 'pill', 'pill-muted'] as const,
      portal: ['portal', 'portal-ghost', 'portal-chip', 'portal-nav', 'portal-filter'] as const,
    }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 28px;">
        <section style="display: flex; flex-direction: column; gap: 12px;">
          <h3 style="margin: 0; font-size: 10px; font-weight: 600; letter-spacing: .09em; text-transform: uppercase; color: var(--faint);">Admin</h3>
          <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px;">
            <div v-for="name in admin" :key="name" style="display: flex; flex-direction: column; gap: 6px;">
              <Button :variant="name">Angebot schreiben</Button>
              <code style="font-size: 11px; color: var(--muted);">{{ name }}</code>
            </div>
          </div>
        </section>

        <section style="display: flex; flex-direction: column; gap: 12px;">
          <h3 style="margin: 0; font-size: 10px; font-weight: 600; letter-spacing: .09em; text-transform: uppercase; color: var(--faint);">Portal</h3>
          <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px;">
            <div v-for="name in portal" :key="name" style="display: flex; flex-direction: column; gap: 6px;">
              <Button :variant="name">Galerie öffnen</Button>
              <code style="font-size: 11px; color: var(--muted);">{{ name }}</code>
            </div>
          </div>
        </section>
      </div>
    `,
  }),
}
