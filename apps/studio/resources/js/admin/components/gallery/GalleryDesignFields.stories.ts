import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { ref } from 'vue'
import GalleryDesignFields from './GalleryDesignFields.vue'
import GalleryPreview from './GalleryPreview.vue'
import { DEFAULT_DESIGN } from '@/admin/lib/galleryDesign'

const meta = {
  title: 'Galerie/Gestaltung',
  component: GalleryDesignFields,
  args: { modelValue: { ...DEFAULT_DESIGN } },
  render: () => ({
    components: { GalleryDesignFields, GalleryPreview },
    setup: () => ({ design: ref({ ...DEFAULT_DESIGN }) }),
    template: `
      <div style="display:flex;gap:32px;align-items:flex-start">
        <div style="width:360px"><GalleryDesignFields v-model="design" /></div>
        <div style="flex:1"><GalleryPreview :design="design" :offers="{ downloads: true, lists: true, comments: false }" title="Hochzeit Rahn" url="bilder.example.test/rahn" /></div>
      </div>`,
  }),
} satisfies Meta<typeof GalleryDesignFields>

export default meta

export const Standard: StoryObj<typeof meta> = {}
