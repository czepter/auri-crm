import type { Meta, StoryObj } from '@storybook/vue3-vite'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '.'

const parts = { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger }

const meta = {
  title: 'Überlagerung/DropdownMenu',
  component: DropdownMenu,
  args: { defaultOpen: true, modal: false },
  parameters: { layout: 'centered' },
} satisfies Meta<typeof DropdownMenu>

export default meta
type Story = StoryObj<typeof meta>

/** Eine Liste von Zielen hinter den drei Punkten im Kopf — was danach kommt, fragt ein AlertDialog. */
export const Dreipunktmenue: Story = {
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <div style="height: 220px;">
        <DropdownMenu v-bind="args">
          <DropdownMenuTrigger
            class="flex size-8 flex-none cursor-pointer items-center justify-center rounded-[var(--r)] bg-sunk-2"
            aria-label="Weitere Aktionen"
          >
            <svg class="size-4 fill-current" viewBox="0 0 24 24">
              <circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" />
            </svg>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-[208px]">
            <DropdownMenuItem>Schließen …</DropdownMenuItem>
            <DropdownMenuItem class="text-destructive">Löschen …</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    `,
  }),
}
