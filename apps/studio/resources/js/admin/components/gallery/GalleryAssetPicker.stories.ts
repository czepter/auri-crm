import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { ref } from 'vue'
import GalleryAssetPicker from './GalleryAssetPicker.vue'

const assets = Array.from({ length: 12 }, (_, index) => ({
  id: `asset-${index}`,
  src: `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="hsl(${index * 30} 25% 60%)"/></svg>`)}`,
  filename: `IMG_${String(index).padStart(4, '0')}.jpg`,
}))

const meta = {
  title: 'Galerie/Bilderauswahl',
  component: GalleryAssetPicker,
  args: { assets, modelValue: { selected: new Set<string>(), anchor: null } },
  render: (args) => ({
    components: { GalleryAssetPicker },
    setup: () => ({ args, selection: ref({ selected: new Set(['asset-1', 'asset-2']), anchor: 'asset-1' }) }),
    template: `<GalleryAssetPicker :assets="args.assets" v-model="selection" />`,
  }),
} satisfies Meta<typeof GalleryAssetPicker>

export default meta

export const Auswahl: StoryObj<typeof meta> = {}
