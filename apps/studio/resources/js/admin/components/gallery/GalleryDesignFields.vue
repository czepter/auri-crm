<script setup lang="ts">
import { computed, ref } from 'vue'
import { ChoiceGroup } from '@/ui/choice-group'
import { Button } from '@/ui/button'
import GalleryFocalDialog from '@/admin/components/gallery/GalleryFocalDialog.vue'
import {
  COLOUR_KEYS, COLOURS, COVERS, FLOWS, FONTS, SIZES, SPACINGS, STYLES, TYPES,
  styleOf, withStyle, type GalleryDesign,
} from '@/admin/lib/galleryDesign'

const props = defineProps<{
  modelValue: GalleryDesign
  /** Das Titelbild der Galerie; nur dort lässt sich ein Fokus setzen. */
  cover?: { src: string; focal: { x: number; y: number } | null } | null
}>()

const emit = defineEmits<{
  'update:modelValue': [design: GalleryDesign]
  focal: [focal: { x: number; y: number }]
}>()

const style = computed(() => styleOf(props.modelValue))
const focusing = ref(false)

function set(key: keyof GalleryDesign, value: string) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

function swatch(option: string) {
  const one = STYLES.find((style) => style.key === option) ?? props.modelValue

  return { background: COLOURS[one.colour]?.bg, color: COLOURS[one.colour]?.ink, fontFamily: FONTS[one.type]?.family }
}

const heading = 'text-small leading-none font-semibold text-ink-2'
const tile = 'cursor-pointer rounded-md border bg-paper p-2 text-left text-small transition-colors'
const on = 'border-ink'
const off = 'border-border hover:border-line-hover'

// Schematisch: wo Bild und Titel stehen, nicht wie sie aussehen.
const COVER_BOX: Record<string, string> = {
  zentriert: 'grid',
  links: 'grid',
  rahmen: 'grid p-1',
  geteilt: 'grid grid-cols-2',
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <section>
      <div class="mb-2 flex items-baseline justify-between">
        <h3 :class="heading">{{ $t('galleryDesign.cover') }}</h3>
        <Button v-if="cover" type="button" variant="ghost" @click="focusing = true">{{ $t('galleryDesign.focus') }}</Button>
      </div>
      <div class="grid grid-cols-4 gap-2">
        <button
          v-for="option in COVERS"
          :key="option"
          :aria-pressed="modelValue.cover === option"
          :class="[tile, modelValue.cover === option ? on : off]"
          type="button"
          @click="set('cover', option)"
        >
          <span :class="['mb-1.5 h-9 w-full overflow-hidden rounded-[3px] bg-sunk-2', COVER_BOX[option]]">
            <span :class="['bg-line-hover', option === 'rahmen' ? 'h-full w-full rounded-[3px]' : 'h-full w-full']" />
            <span v-if="option === 'geteilt'" class="m-auto h-0.5 w-4 bg-ink" />
          </span>
          {{ $t(`galleryDesign.cover_${option}`) }}
        </button>
      </div>
      <GalleryFocalDialog
        v-if="cover"
        v-model:open="focusing"
        :focal="cover.focal"
        :src="`${cover.src}?w=1200&fmt=jpeg`"
        @save="emit('focal', $event)"
      />
    </section>

    <section>
      <h3 :class="[heading, 'mb-2']">{{ $t('galleryDesign.style') }}</h3>
      <div class="grid grid-cols-4 gap-2">
        <button
          v-for="option in [...STYLES.map((one) => one.key), ...(style === 'eigene' ? ['eigene'] : [])]"
          :key="option"
          :aria-pressed="style === option"
          :class="[tile, style === option ? on : off]"
          :disabled="option === 'eigene'"
          type="button"
          @click="emit('update:modelValue', withStyle(modelValue, option))"
        >
          <span class="mb-1.5 grid h-9 place-items-center rounded-[3px] text-title leading-none" :style="swatch(option)">Aa</span>
          {{ $t(`galleryDesign.style_${option}`) }}
        </button>
      </div>
    </section>

    <ChoiceGroup
      :label="$t('galleryDesign.type')"
      :model-value="modelValue.type"
      :options="TYPES.map((option) => ({ value: option, label: $t(`galleryDesign.type_${option}`) }))"
      @update:model-value="set('type', $event)"
    >
      <template #option="{ option }">
        <span :style="{ fontFamily: FONTS[option.value]?.family }">{{ option.label }}</span>
      </template>
    </ChoiceGroup>

    <section>
      <h3 :class="[heading, 'mb-2']">
        {{ $t('galleryDesign.colour') }}
        <span class="ml-1 font-normal">{{ $t(`galleryDesign.colour_${modelValue.colour}`) }}</span>
      </h3>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="option in COLOUR_KEYS"
          :key="option"
          :aria-label="$t(`galleryDesign.colour_${option}`)"
          :aria-pressed="modelValue.colour === option"
          class="size-7 cursor-pointer rounded-full border border-line-hover p-0"
          :class="modelValue.colour === option && 'outline-2 outline-offset-2 outline-ink'"
          :style="{ background: COLOURS[option].hero }"
          type="button"
          @click="set('colour', option)"
        />
      </div>
    </section>

    <section class="flex flex-col gap-3">
      <h3 :class="heading">{{ $t('galleryDesign.grid') }}</h3>
      <ChoiceGroup
        :label="$t('galleryDesign.size')"
        :model-value="modelValue.size"
        :options="SIZES.map((option) => ({ value: option, label: $t(`galleryDesign.size_${option}`) }))"
        @update:model-value="set('size', $event)"
      />
      <ChoiceGroup
        :label="$t('galleryDesign.spacing')"
        :model-value="modelValue.spacing"
        :options="SPACINGS.map((option) => ({ value: option, label: $t(`galleryDesign.spacing_${option}`) }))"
        @update:model-value="set('spacing', $event)"
      />
      <ChoiceGroup
        :label="$t('galleryDesign.flow')"
        :model-value="modelValue.flow"
        :options="FLOWS.map((option) => ({ value: option, label: $t(`galleryDesign.flow_${option}`) }))"
        @update:model-value="set('flow', $event)"
      />
    </section>
  </div>
</template>
