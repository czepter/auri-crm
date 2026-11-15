import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Textarea } from '@/ui/textarea'
import {
  Dialog,
  DialogBody,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '.'

const parts = {
  Dialog,
  DialogBody,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  Button,
  Field,
  Input,
  Textarea,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
}

const meta = {
  title: 'Überlagerung/Dialog',
  component: Dialog,
  args: { defaultOpen: true, modal: true },
  parameters: { layout: 'centered' },
} satisfies Meta<typeof Dialog>

export default meta
type Story = StoryObj<typeof meta>

/** Ein Formular über der Liste, die man gerade liest — inline schöbe es die Liste weg. */
export const AufgabeAnlegen: Story = {
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <Dialog v-bind="args">
        <DialogTrigger as-child>
          <Button variant="secondary" type="button">Aufgabe</Button>
        </DialogTrigger>

        <DialogContent>
          <DialogHeader>
            <DialogTitle>Aufgabe</DialogTitle>
          </DialogHeader>

          <DialogBody>
            <form id="story-task-form" class="grid grid-cols-2 gap-x-6 gap-y-4" @submit.prevent>
              <Field wide label="Aufgabe" v-slot="{ id }">
                <Input :id="id" type="text" default-value="Rohdaten sichern" />
              </Field>

              <Field label="Betrifft" v-slot="{ id }">
                <Select default-value="internal">
                  <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="internal">intern</SelectItem>
                    <SelectItem value="client">Kunde</SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <Field label="Shooting" v-slot="{ id }">
                <Select default-value="none">
                  <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Ganzes Projekt</SelectItem>
                    <SelectItem value="trauung">Trauung</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
            </form>
          </DialogBody>

          <DialogFooter>
            <Button type="submit" form="story-task-form">Speichern</Button>
            <DialogClose as-child>
              <Button variant="ghost" type="button">Abbrechen</Button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    `,
  }),
}

/** Auch eine Absage ist hier ein Formular: der Grund landet im Datensatz und steht danach auf der Zeile. */
export const ShootingAbsagen: Story = {
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <Dialog v-bind="args">
        <DialogContent class="max-w-[440px]">
          <DialogHeader>
            <DialogTitle>Absagen</DialogTitle>
            <DialogDescription>Trauung</DialogDescription>
          </DialogHeader>

          <DialogBody>
            <form id="story-cancel-form" @submit.prevent>
              <Field label="Grund der Absage?" v-slot="{ id }">
                <Textarea :id="id" default-value="Termin vom Paar verschoben" />
              </Field>
            </form>
          </DialogBody>

          <DialogFooter>
            <Button type="submit" form="story-cancel-form" tone="destructive">Absagen</Button>
            <DialogClose as-child>
              <Button variant="ghost" type="button">Abbrechen</Button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    `,
  }),
}
