import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { ChevronDown } from 'lucide-vue-next'
import { Button } from '@/ui/button'
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

/** Nächste Stufe als Knopf, alle Stufen hinter dem Pfeil — der Kopf einer Anfrage. */
export const Stufenwahl: Story = {
  render: (args) => ({
    components: { ...parts, Button, ChevronDown },
    setup: () => ({ args }),
    template: `
      <div style="height: 220px;">
        <div class="flex">
          <Button variant="pill" type="button" class="rounded-r-none">Angebot</Button>
          <DropdownMenu v-bind="args">
            <DropdownMenuTrigger as-child>
              <Button variant="pill" type="button" class="rounded-l-none border-l border-white/30 px-2" aria-label="Stufe">
                <ChevronDown class="size-3.5" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-[208px]">
              <DropdownMenuItem>Neu</DropdownMenuItem>
              <DropdownMenuItem disabled>Kontaktiert</DropdownMenuItem>
              <DropdownMenuItem>Angebot</DropdownMenuItem>
              <DropdownMenuItem>Angebot angenommen</DropdownMenuItem>
              <DropdownMenuItem>Gebucht</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    `,
  }),
}
