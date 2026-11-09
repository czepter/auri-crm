import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { ref } from 'vue'
import GalleryReleaseFields from './GalleryReleaseFields.vue'
import { DEFAULT_RELEASE } from '@/admin/lib/galleryPreset'

const meta = {
  title: 'Galerie/Freigabe',
  component: GalleryReleaseFields,
  args: { modelValue: { ...DEFAULT_RELEASE } },
  render: (args) => ({
    components: { GalleryReleaseFields },
    setup: () => ({ args, values: ref({ ...DEFAULT_RELEASE }) }),
    template: `<GalleryReleaseFields v-bind="args" :model-value="values" @change="values = { ...values, ...$event }" />`,
  }),
} satisfies Meta<typeof GalleryReleaseFields>

export default meta

/** In einer Vorlage: ohne Rechnung und ohne eigenen Wasserzeichentext. */
export const Vorlage: StoryObj<typeof meta> = { args: { watermarkField: false } }

/** An einer Galerie mit Projekt: die Rechnung ist wählbar. */
export const MitRechnung: StoryObj<typeof meta> = {
  args: {
    modelValue: { ...DEFAULT_RELEASE, download_rule: 'after_payment' },
    invoices: [{ id: 'inv-1', number: 'RE-2027-014', status: 'open' }],
    invoiceId: null,
  },
}
