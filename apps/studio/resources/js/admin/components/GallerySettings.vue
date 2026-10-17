<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { router, useForm } from '@inertiajs/vue3'
import GalleryPreview from '@/admin/components/gallery/GalleryPreview.vue'
import { PreviewOverlay } from '@/ui/preview-overlay'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { ChoiceGroup } from '@/ui/choice-group'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import { Switch } from '@/ui/switch'

/*
 * G-14: über der Liste, die er einstellt — dieselbe Form, die Stufen und
 * Projekttypen auf /auftraege und /anfragen haben (ProjectSettings.vue).
 *
 * Breiter als jener Raum, 1180 statt 1000: Vorlagen braucht drei Spalten,
 * Liste, Formular und Vorschau, und 1180 ist das Maß, das --container-case
 * ohnehin führt.
 */
const props = defineProps<{
  settings: App.Settings.Data.GallerySettingsData
  presets: App.Gallery.Data.GalleryPresetData[]
  galleryHost: string
  previewTitle: string
  section: Section
}>()

const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()

type Section = 'standardwerte' | 'gestaltung' | 'vorlagen'

const SECTIONS: Section[] = ['standardwerte', 'gestaltung', 'vorlagen']

const DESIGN = [
  { key: 'cover', label: 'galleryRecord.cover', options: ['full', 'split', 'plain'], prefix: 'cover' },
  { key: 'colour', label: 'galleryRecord.colour', options: ['light', 'dark', 'warm'], prefix: 'colour' },
  { key: 'grid', label: 'galleryRecord.grid', options: ['wide', 'dense', 'single'], prefix: 'grid' },
] as const

const MODES = ['inherit', 'days', 'never']

const TRISTATE = [
  { value: '', key: 'inherit' },
  { value: '1', key: 'on' },
  { value: '0', key: 'off' },
]

const PRESET_GATES = [
  { field: 'downloads_enabled', label: 'gallerySettings.downloadsLabel' },
  { field: 'lists_enabled', label: 'gallerySettings.listsLabel' },
  { field: 'comments_enabled', label: 'gallerySettings.commentsLabel' },
] as const

const STUDIO_GATES = [
  { field: 'gallery_default_downloads_enabled', label: 'gallerySettings.downloadsLabel' },
  { field: 'gallery_default_lists_enabled', label: 'gallerySettings.listsLabel' },
  { field: 'gallery_default_comments_enabled', label: 'gallerySettings.commentsLabel' },
] as const

const open = ref<Section>(props.section)

const form = useForm({
  gallery_default_expiry_days: props.settings.expiryDays ?? ('' as string | number),
  gallery_default_downloads_enabled: props.settings.downloadsEnabled,
  gallery_default_lists_enabled: props.settings.listsEnabled,
  gallery_default_comments_enabled: props.settings.commentsEnabled,
  gallery_default_design: { ...props.settings.design },
})

function save() {
  form
    .transform((data) => ({
      ...data,
      gallery_default_expiry_days: data.gallery_default_expiry_days === '' ? null : data.gallery_default_expiry_days,
    }))
    .patch('/einstellungen/galerien', { preserveScroll: true, preserveState: true })
}

/* ---- Vorlagen ------------------------------------------------------ */

const chosen = ref(props.presets[0]?.id ?? '')
const preset = computed(() => props.presets.find((row) => row.id === chosen.value) ?? props.presets[0] ?? null)

function toTri(value: boolean | null) {
  return value === null ? '' : value ? '1' : '0'
}

function fields(row: App.Gallery.Data.GalleryPresetData) {
  return {
    name: row.name,
    expiry_mode: row.expiryMode,
    expiry_days: (row.expiryDays ?? '') as string | number,
    downloads_enabled: toTri(row.downloadsEnabled),
    lists_enabled: toTri(row.listsEnabled),
    comments_enabled: toTri(row.commentsEnabled),
    design: row.design as Record<string, string> | null,
  }
}

const presetForm = useForm(fields(props.presets[0] ?? {
  name: '', expiryMode: 'inherit', expiryDays: null, design: null,
  downloadsEnabled: null, listsEnabled: null, commentsEnabled: null,
} as App.Gallery.Data.GalleryPresetData))

watch(preset, (row) => {
  if (row === null) return

  presetForm.defaults(fields(row))
  presetForm.reset()
})

function savePreset() {
  presetForm
    .transform((data) => ({
      ...data,
      expiry_days: data.expiry_days === '' ? null : data.expiry_days,
      ...Object.fromEntries(PRESET_GATES.map((gate) => [
        gate.field,
        data[gate.field] === '' ? null : data[gate.field] === '1',
      ])),
    }))
    .patch(`/einstellungen/galerie-vorlagen/${preset.value!.id}`, { preserveScroll: true })
}

// Ganz oder gar nicht, wie die Vorlage es speichert: erben heißt null, und
// wer eine eigene Gestaltung anschaltet, fängt bei der Studio-Vorgabe an.
function toggleDesign(on: boolean) {
  presetForm.design = on ? { ...props.settings.design } : null
  savePreset()
}

function markDefault() {
  router.patch(`/einstellungen/galerie-vorlagen/${preset.value!.id}/standard`, {}, { preserveScroll: true })
}

// Ohne Dialog: die Zeile entsteht und wird im Formular daneben benannt —
// ein eigener Schritt nur für den Namen wäre einer zu viel.
function addPreset() {
  router.post('/einstellungen/galerie-vorlagen', {
    name: t('galleryPresets.untitled'), expiry_mode: 'inherit', design: null,
  }, {
    preserveScroll: true,
    onSuccess: () => (chosen.value = props.presets.at(-1)?.id ?? ''),
  })
}

function removePreset() {
  router.delete(`/einstellungen/galerie-vorlagen/${preset.value!.id}`, { preserveScroll: true })
}

// Was der Kunde sieht: die Vorlage, wo sie etwas sagt, sonst die Vorgabe.
const presetPreview = computed(() => ({
  design: presetForm.design ?? form.gallery_default_design,
  offers: {
    downloads: presetForm.downloads_enabled === '' ? props.settings.downloadsEnabled : presetForm.downloads_enabled === '1',
    lists: presetForm.lists_enabled === '' ? props.settings.listsEnabled : presetForm.lists_enabled === '1',
    comments: presetForm.comments_enabled === '' ? props.settings.commentsEnabled : presetForm.comments_enabled === '1',
  },
}))

const studioOffers = computed(() => ({
  downloads: form.gallery_default_downloads_enabled,
  lists: form.gallery_default_lists_enabled,
  comments: form.gallery_default_comments_enabled,
}))
</script>

<template>
  <!-- Über der Liste, die er einstellt, nicht ein Raum für sich: fixed, damit
       die Liste darunter ihre Scrollposition behält. -->
  <div data-tokens class="fixed inset-0 z-40 overflow-y-auto bg-background">
    <div class="mx-auto w-full max-w-case px-6 pb-16">
      <div class="sticky top-0 z-10 flex items-center gap-4 bg-background py-[18px]">
        <button
          :aria-label="$t('common.close')"
          class="inline-flex cursor-pointer border-0 bg-transparent p-1 text-ink-2"
          type="button"
          @click="emit('close')"
        >
          <svg class="size-[18px] fill-none stroke-current stroke-[1.75] [stroke-linecap:round]" viewBox="0 0 24 24">
            <path d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>
        <span class="text-body leading-[1.3] font-semibold tracking-[-0.015em]">{{ $t('gallerySettings.roomTitle') }}</span>
        <span v-if="form.isDirty" class="flex-none rounded-[var(--r-sm)] bg-due-bg px-2 py-[3px] text-small leading-[1.5] font-semibold text-due">
          {{ $t('settings.unsaved') }}
        </span>
        <Button class="ml-auto" type="button" :disabled="form.processing" @click="form.isDirty ? save() : emit('close')">
          {{ form.isDirty ? $t('common.save') : $t('stages.done') }}
        </Button>
      </div>

      <div class="flex gap-10 pt-6 max-[720px]:flex-col max-[720px]:gap-6">
        <nav class="w-[168px] flex-none">
          <h3 class="m-0 mb-[7px] text-small leading-none font-semibold text-ink-2">
            {{ $t('galleries.title') }}
          </h3>
          <button
            v-for="one in SECTIONS"
            :key="one"
            class="block w-full cursor-pointer rounded-lg border-0 bg-transparent px-2.5 py-[7px] text-left text-small leading-[1.3]"
            :class="open === one ? 'bg-sunk-2 font-semibold' : 'text-ink-2'"
            type="button"
            @click="open = one"
          >
            {{ $t(`gallerySettings.nav_${one}`) }}
          </button>
        </nav>

        <section v-if="open === 'standardwerte'" class="flex min-w-0 flex-1 gap-8 max-[1120px]:flex-col">
          <div class="w-[300px] flex-none max-[1120px]:w-full">
            <h2 class="m-0 mb-6 text-title leading-[1.2] font-semibold tracking-[-0.02em]">
              {{ $t('gallerySettings.nav_standardwerte') }}
            </h2>

            <div class="mb-6">
              <Field v-slot="{ id }" :error="form.errors.gallery_default_expiry_days">
                <Label :for="id">{{ $t('gallerySettings.expiryLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
                <InputGroup>
                  <InputGroupInput :id v-model="form.gallery_default_expiry_days" class="tabular-nums" min="1" type="number" @change="save" />
                  <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
                </InputGroup>
              </Field>
            </div>

            <div v-for="gate in STUDIO_GATES" :key="gate.field" class="flex items-center gap-4 border-b border-border py-2.5">
              <Label class="flex-1" :for="gate.field" variant="inline">{{ $t(gate.label) }}</Label>
              <Switch :id="gate.field" v-model="form[gate.field]" @update:model-value="save" />
            </div>
          </div>

          <PreviewOverlay :min="1121" :label="$t('common.preview')" wrapper-class="min-w-0 flex-1">
            <div class="mb-2.5 text-small leading-none font-semibold text-faint">
              {{ $t('galleryRecord.preview') }}
            </div>
            <GalleryPreview
              :design="form.gallery_default_design"
              :offers="studioOffers"
              :title="previewTitle"
              :url="galleryHost"
            />
          </PreviewOverlay>
        </section>

        <section v-else-if="open === 'gestaltung'" class="flex min-w-0 flex-1 gap-8 max-[1120px]:flex-col">
          <div class="w-[260px] flex-none max-[1120px]:w-full">
            <h2 class="m-0 mb-6 text-title leading-[1.2] font-semibold tracking-[-0.02em]">
              {{ $t('gallerySettings.nav_gestaltung') }}
            </h2>

            <div v-for="group in DESIGN" :key="group.key" class="mb-4">
              <ChoiceGroup
                v-model="form.gallery_default_design[group.key]"
                :label="$t(group.label)"
                :options="group.options.map((option) => ({ value: option, label: $t(`galleryRecord.${group.prefix}_${option}`) }))"
                @update:model-value="save"
              />
            </div>
          </div>

          <PreviewOverlay :min="1121" :label="$t('common.preview')" wrapper-class="min-w-0 flex-1">
            <div class="mb-2.5 text-small leading-none font-semibold text-faint">
              {{ $t('galleryRecord.preview') }}
            </div>
            <GalleryPreview
              :design="form.gallery_default_design"
              :offers="studioOffers"
              :title="previewTitle"
              :url="galleryHost"
            />
          </PreviewOverlay>
        </section>

        <section v-else class="min-w-0 flex-1">
          <h2 class="m-0 mb-6 text-title leading-[1.2] font-semibold tracking-[-0.02em]">
            {{ $t('gallerySettings.nav_vorlagen') }}
          </h2>

          <div class="flex gap-8 max-[1120px]:flex-col">
            <div class="w-[150px] flex-none max-[1120px]:w-full">
              <button
                v-for="row in presets"
                :key="row.id"
                class="flex w-full cursor-pointer items-center gap-2 rounded-lg border-0 bg-transparent px-2.5 py-[7px] text-left text-small leading-[1.3]"
                :class="chosen === row.id ? 'bg-sunk-2 font-semibold' : 'text-ink-2'"
                type="button"
                @click="chosen = row.id"
              >
                <span class="min-w-0 flex-1 truncate">{{ row.name }}</span>
                <span v-if="row.isDefault" class="flex-none rounded-[var(--r-sm)] bg-accent px-1.5 py-0.5 text-small leading-[1.4] font-semibold text-action">
                  {{ $t('gallerySettings.defaultMark') }}
                </span>
              </button>

              <Button class="mt-1.5 ml-0" type="button" variant="ghost" @click="addPreset">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
                {{ $t('galleryPresets.add') }}
              </Button>
            </div>

            <div v-if="preset" class="w-[300px] flex-none max-[1120px]:w-full">
              <div class="flex items-center gap-4 border-b border-border py-2.5">
                <Label class="flex-1" for="preset-name" variant="inline">{{ $t('galleryPresets.nameField') }}</Label>
                <Input id="preset-name" v-model="presetForm.name" class="max-w-[160px]" maxlength="120" type="text" @change="savePreset" />
              </div>

              <div class="flex items-center gap-4 border-b border-border py-2.5">
                <Label class="flex-1" for="preset-default" variant="inline">{{ $t('gallerySettings.defaultLabel') }}</Label>
                <Switch id="preset-default" :model-value="preset.isDefault" :disabled="preset.isDefault" @update:model-value="markDefault" />
              </div>

              <div class="flex items-center gap-4 border-b border-border py-2.5">
                <span class="flex-1 text-small leading-[1.35]">{{ $t('galleryPresets.expiryField') }}</span>
                <ChoiceGroup
                  v-model="presetForm.expiry_mode"
                  :options="MODES.map((option) => ({ value: option, label: $t(`galleryPresets.mode_${option}`) }))"
                  @update:model-value="savePreset"
                />
              </div>

              <div v-if="presetForm.expiry_mode === 'days'" class="flex items-center gap-4 border-b border-border py-2.5">
                <Label class="flex-1" for="preset-days" variant="inline">{{ $t('gallerySettings.expiryLabel') }}</Label>
                <InputGroup class="max-w-[140px]">
                  <InputGroupInput id="preset-days" v-model="presetForm.expiry_days" class="tabular-nums" min="1" type="number" @change="savePreset" />
                  <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
                </InputGroup>
              </div>

              <div v-for="gate in PRESET_GATES" :key="gate.field" class="flex items-center gap-4 border-b border-border py-2.5">
                <span class="min-w-0 flex-1 text-small leading-[1.35]">{{ $t(gate.label) }}</span>
                <ChoiceGroup
                  v-model="presetForm[gate.field]"
                  :options="TRISTATE.map((option) => ({ value: option.value, label: $t(`galleryPresets.${option.key}`) }))"
                  @update:model-value="savePreset"
                />
              </div>

              <div class="flex items-center gap-4 border-b border-border py-2.5">
                <Label class="flex-1" for="preset-design" variant="inline">{{ $t('galleryPresets.designLabel') }}</Label>
                <Switch id="preset-design" :model-value="presetForm.design !== null" @update:model-value="toggleDesign" />
              </div>

              <div v-if="presetForm.design" class="pt-3.5">
                <div v-for="group in DESIGN" :key="group.key" class="mb-3.5">
                  <ChoiceGroup
                    v-model="presetForm.design[group.key]"
                    :label="$t(group.label)"
                    :options="group.options.map((option) => ({ value: option, label: $t(`galleryRecord.${group.prefix}_${option}`) }))"
                    @update:model-value="savePreset"
                  />
                </div>
              </div>

              <Button class="mt-4 ml-0" type="button" variant="ghost" @click="removePreset">
                {{ $t('galleryPresets.delete') }}
              </Button>
            </div>

            <PreviewOverlay v-if="preset" :min="1121" :label="$t('common.preview')" wrapper-class="min-w-0 flex-1">
              <div class="mb-2.5 flex items-baseline gap-2">
                <span class="text-small leading-none font-semibold text-faint">
                  {{ $t('galleryRecord.preview') }}
                </span>
                <span v-if="presetForm.design === null" class="text-small leading-none text-faint">
                  {{ $t('gallerySettings.previewInherited') }}
                </span>
              </div>
              <GalleryPreview
                :design="presetPreview.design"
                :offers="presetPreview.offers"
                :title="previewTitle"
                :url="galleryHost"
              />
            </PreviewOverlay>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
