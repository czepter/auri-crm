import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'

const feld = (
  row: Pick<App.Crm.Data.FormInstanceFieldData, 'id' | 'label' | 'type' | 'typeLabel'> & Partial<App.Crm.Data.FormInstanceFieldData>,
): App.Crm.Data.FormInstanceFieldData => ({
  required: false,
  options: null,
  currentAnswer: null,
  answeredAt: null,
  groupPhotos: null,
  fileFormats: null,
  fileMaxSize: null,
  fileMultiple: false,
  files: [],
  ...row,
})

const rahn: App.Crm.Data.ProjectSuggestionData = {
  id: 'p-rahn',
  name: 'Rahn, Marie & Jonas',
  contactName: 'Marie Rahn',
  isBooked: true,
  galleryDefaults: { downloads: null, lists: null, comments: null },
}

const meta = {
  title: 'Bildschirme/Fragebögen/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/frageboegen/neu' },
  args: {
    project: rahn,
    formTemplates: [
      { value: 'ft-ablauf', label: 'Ablaufplan' },
      { value: 'ft-familie', label: 'Familienkonstellation' },
      { value: 'ft-gruppen', label: 'Wunschliste Gruppenfotos' },
      { value: 'ft-after', label: 'After-Wedding · Ablauf & Wünsche' },
    ],
    fieldTypes: [
      { value: 'text', label: 'Freitext' },
      { value: 'short_text', label: 'Kurzantwort' },
      { value: 'boolean', label: 'Ja / Nein' },
      { value: 'select', label: 'Auswahl' },
      { value: 'date', label: 'Datum' },
      { value: 'time', label: 'Uhrzeit' },
      { value: 'name_list', label: 'Namensliste' },
      { value: 'group_photos', label: 'Gruppenfotos' },
      { value: 'file', label: 'Datei' },
    ],
    instance: null,
  },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Aus der Übersicht geöffnet, noch ohne Projekt. */
export const Auswahl: Story = { args: { project: null } }

/** Projekt gewählt: Vorlage oder leer. */
export const Vorlage: Story = {}

/** Die Vorlage „Ablaufplan“ ist kopiert und wird für Rahn angepasst. */
export const Anpassen: Story = {
  args: {
    instance: {
      id: 'fi-rahn',
      name: 'Hochzeitstag — Ablauf',
      sourceTemplateName: 'Ablaufplan',
      updatedAt: '2026-09-22T10:00:00+02:00',
      sharedAt: '2026-09-22T10:05:00+02:00',
      fields: [
        feld({ id: 'f1', label: 'Wann beginnt die Trauung?', type: 'time', typeLabel: 'Uhrzeit', required: true }),
        feld({ id: 'f2', label: 'Wo findet die Trauung statt?', type: 'short_text', typeLabel: 'Kurzantwort', required: true }),
        feld({ id: 'f3', label: 'Gibt es einen First Look?', type: 'select', typeLabel: 'Auswahl', required: true, options: ['Ja, vor der Trauung', 'Nein', 'Noch offen'] }),
        feld({ id: 'f4', label: 'Wer soll unbedingt aufs Bild?', type: 'name_list', typeLabel: 'Namensliste' }),
        feld({
          id: 'f5',
          label: 'Gruppenfotos',
          type: 'group_photos',
          typeLabel: 'Gruppenfotos',
          groupPhotos: { help: null, suggestions: ['Brautpaar mit Eltern', 'Geschwister', 'Trauzeugen'], minutesPerGroup: 2 },
        }),
        feld({ id: 'f6', label: 'Dürfen wir Bilder im Portfolio zeigen?', type: 'boolean', typeLabel: 'Ja / Nein' }),
      ],
    },
  },
}
