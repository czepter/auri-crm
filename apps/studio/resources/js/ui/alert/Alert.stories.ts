import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Button } from '@/ui/button'
import { Alert, AlertDescription, AlertTitle } from '.'

const parts = { Alert, AlertTitle, AlertDescription, Button }

/*
 * Die einzige Überlagerung, die nichts überlagert: sie steht in der Seite und
 * bleibt stehen. Zwei Töne, mehr kennt alertVariants nicht.
 */
const meta = {
  title: 'Überlagerung/Alert',
  component: Alert,
  argTypes: {
    variant: { control: 'inline-radio', options: ['default', 'destructive'] },
  },
  args: { variant: 'default' },
  parameters: { layout: 'padded' },
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <Alert v-bind="args" class="max-w-[640px]">
        <AlertTitle>Rate überfällig</AlertTitle>
        <AlertDescription>Anzahlung 30 % · fällig am 01.05.2026</AlertDescription>
      </Alert>
    `,
  }),
} satisfies Meta<typeof Alert>

export default meta
type Story = StoryObj<typeof meta>

export const Standard: Story = {}

/** Der zweite Ton: die Meldung selbst ist die schlechte Nachricht. */
export const Destruktiv: Story = { args: { variant: 'destructive' } }

/** Der Stillstand über dem Projekt — Handlungsbedarf trägt den Schritt gleich mit. */
export const Stillstand: Story = {
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <Alert v-bind="args" class="flex max-w-[640px] items-center gap-4 border-0 bg-[var(--due-bg)] px-4 py-3.5">
        <div class="min-w-0">
          <AlertTitle class="text-[14px] font-semibold text-[var(--due)]">Trauung vor 12 Tagen</AlertTitle>
          <AlertDescription class="text-[12.5px] text-ink-2">
            14.06.2026 · Status Gebucht seit 02.06.2026
          </AlertDescription>
        </div>
        <div class="ml-auto flex flex-none items-center gap-2">
          <Button variant="default" type="button">Shooting gelaufen</Button>
        </div>
      </Alert>
    `,
  }),
}

/** Die Absage auf der Shooting-Zeile: der Ton sitzt in der Meldung, die Rücknahme daneben. */
export const Absage: Story = {
  args: { variant: 'destructive' },
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <Alert v-bind="args" class="flex max-w-[640px] items-center gap-3 border-0 bg-late-bg py-[7px] pl-3 pr-[9px]">
        <AlertTitle class="text-[12.5px] font-medium">Abgesagt — Termin vom Paar verschoben</AlertTitle>
        <Button class="ml-auto" variant="outline" type="button">Absage zurücknehmen</Button>
      </Alert>
    `,
  }),
}
