import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogBody,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '.'

const parts = {
  AlertDialog,
  AlertDialogAction,
  AlertDialogBody,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
  Button,
  Field,
  Input,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
}

const meta = {
  title: 'Überlagerung/AlertDialog',
  component: AlertDialog,
  args: { defaultOpen: true },
  parameters: { layout: 'centered' },
} satisfies Meta<typeof AlertDialog>

export default meta
type Story = StoryObj<typeof meta>

/** Eine Frage, zwei Antworten, kein Weg daran vorbei — kein Kreuz in der Ecke, kein Escape. */
export const Buchen: Story = {
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <AlertDialog v-bind="args">
        <AlertDialogTrigger as-child>
          <Button variant="default" type="button">Als gebucht markieren</Button>
        </AlertDialogTrigger>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Projekt als gebucht markieren? Alle offenen Shootings werden auf „Gebucht“ gesetzt.</AlertDialogTitle>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogAction>Als gebucht markieren</AlertDialogAction>
            <AlertDialogCancel>Abbrechen</AlertDialogCancel>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    `,
  }),
}

/** Die Entscheidung trägt hier zwei Angaben mit sich; sie bleibt trotzdem eine Entscheidung. */
export const Schliessen: Story = {
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <AlertDialog v-bind="args">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Projekt schließen</AlertDialogTitle>
          </AlertDialogHeader>
          <AlertDialogBody>
            <Field label="Ausgang" v-slot="{ id }">
              <Select default-value="lost">
                <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="lost">Verloren</SelectItem>
                  <SelectItem value="declined">Abgelehnt</SelectItem>
                  <SelectItem value="closed">Geschlossen</SelectItem>
                </SelectContent>
              </Select>
            </Field>
            <Field label="Grund" v-slot="{ id }">
              <Input :id="id" type="text" default-value="Termin anderweitig vergeben" />
            </Field>
          </AlertDialogBody>
          <AlertDialogFooter>
            <AlertDialogAction>Schließen</AlertDialogAction>
            <AlertDialogCancel>Abbrechen</AlertDialogCancel>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    `,
  }),
}

/** Der Ton sitzt auf der bestätigenden Taste, nicht auf dem Panel. */
export const Loeschen: Story = {
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <AlertDialog v-bind="args">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Projekt löschen?</AlertDialogTitle>
            <AlertDialogDescription>Hochzeit Berger — 14.06.2026</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogAction tone="destructive">Löschen</AlertDialogAction>
            <AlertDialogCancel>Abbrechen</AlertDialogCancel>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    `,
  }),
}
