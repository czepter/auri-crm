<script setup lang="ts">
import { watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { ChoiceGroup } from '@/ui/choice-group'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { fromOption, toOption } from '@/lib/select'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

const props = defineProps<{ preset: App.Gallery.Data.GalleryPresetData }>()

const MODES = ['inherit', 'days', 'never']
const COVERS = ['full', 'split', 'plain']
const COLOURS = ['light', 'dark', 'warm']
const GRIDS = ['wide', 'dense', 'single']

// Drei Zustände brauchen drei Optionen: eine Checkbox kann "erben" nicht
// darstellen, und genau das ist der Punkt einer Vorlage, die nur einen Teil
// der Werte setzt.
const TRISTATE = [
  { value: '', key: 'inherit' },
  { value: '1', key: 'on' },
  { value: '0', key: 'off' },
]

// Ganz oder gar nicht: der Record schreibt immer alle vier Schluessel, also
// sagt eine Vorlage zur Gestaltung entweder alles oder nichts.
const BLANK_DESIGN = { cover: 'full', type: 'serif', colour: 'light', grid: 'wide' }

function toTri(value: boolean | null) {
  return value === null ? '' : value ? '1' : '0'
}

function fromTri(value: string) {
  return value === '' ? null : value === '1'
}

function fields(preset: App.Gallery.Data.GalleryPresetData) {
  return {
    name: preset.name,
    expiry_mode: preset.expiryMode,
    expiry_days: (preset.expiryDays ?? '') as string | number,
    requires_settled_balance: toTri(preset.requiresSettledBalance),
    downloads_enabled: toTri(preset.downloadsEnabled),
    lists_enabled: toTri(preset.listsEnabled),
    comments_enabled: toTri(preset.commentsEnabled),
    design: preset.design as Record<string, string> | null,
  }
}

const form = useForm(fields(props.preset))

watch(
  () => props.preset,
  (preset) => {
    form.defaults(fields(preset))
    form.reset()
  },
)

function payload(data: Record<string, unknown>) {
  return {
    ...data,
    requires_settled_balance: fromTri(data.requires_settled_balance as string),
    downloads_enabled: fromTri(data.downloads_enabled as string),
    lists_enabled: fromTri(data.lists_enabled as string),
    comments_enabled: fromTri(data.comments_enabled as string),
  }
}

function submit() {
  form.transform(payload).patch(`/einstellungen/galerie-vorlagen/${props.preset.id}`, { preserveScroll: true })
}

function remove() {
  router.delete(`/einstellungen/galerie-vorlagen/${props.preset.id}`, {
    preserveScroll: true,
    onSuccess: () => router.get('/vorlagen', { typ: 'galerien' }, { preserveScroll: true }),
  })
}

function toggleDesign(on: unknown) {
  form.design = on ? { ...BLANK_DESIGN } : null
}
</script>

<template>
  <form :id="`gallery-preset-${preset.id}`" @submit.prevent="submit">
    <h2 class="m-0 mb-6 truncate text-[19px] leading-[1.2] font-semibold tracking-[-0.02em]">{{ preset.name }}</h2>

    <div class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4">
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`name-${preset.id}`">{{ $t('galleryPresets.nameField') }}</Label>
        <Input :id="`name-${preset.id}`" v-model="form.name" type="text" />
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`mode-${preset.id}`">{{ $t('galleryPresets.expiryField') }}</Label>
        <Select v-model="form.expiry_mode">
          <SelectTrigger :id="`mode-${preset.id}`"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="mode in MODES" :key="mode" :value="mode">{{ $t(`galleryPresets.mode_${mode}`) }}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="form.expiry_mode === 'days'" class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`days-${preset.id}`">{{ $t('gallerySettings.expiryLabel') }}</Label>
        <Input :id="`days-${preset.id}`" v-model="form.expiry_days" class="tabular-nums" type="number" min="1" />
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`balance-${preset.id}`">{{ $t('gallerySettings.balanceLabel') }}</Label>
        <Select
          :model-value="toOption(form.requires_settled_balance)"
          @update:model-value="(value) => (form.requires_settled_balance = fromOption(value))"
        >
          <SelectTrigger :id="`balance-${preset.id}`"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="state in TRISTATE" :key="state.value" :value="toOption(state.value)">{{ $t(`galleryPresets.${state.key}`) }}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`downloads-${preset.id}`">{{ $t('gallerySettings.downloadsLabel') }}</Label>
        <Select
          :model-value="toOption(form.downloads_enabled)"
          @update:model-value="(value) => (form.downloads_enabled = fromOption(value))"
        >
          <SelectTrigger :id="`downloads-${preset.id}`"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="state in TRISTATE" :key="state.value" :value="toOption(state.value)">{{ $t(`galleryPresets.${state.key}`) }}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`lists-${preset.id}`">{{ $t('gallerySettings.listsLabel') }}</Label>
        <Select
          :model-value="toOption(form.lists_enabled)"
          @update:model-value="(value) => (form.lists_enabled = fromOption(value))"
        >
          <SelectTrigger :id="`lists-${preset.id}`"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="state in TRISTATE" :key="state.value" :value="toOption(state.value)">{{ $t(`galleryPresets.${state.key}`) }}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`comments-${preset.id}`">{{ $t('gallerySettings.commentsLabel') }}</Label>
        <Select
          :model-value="toOption(form.comments_enabled)"
          @update:model-value="(value) => (form.comments_enabled = fromOption(value))"
        >
          <SelectTrigger :id="`comments-${preset.id}`"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="state in TRISTATE" :key="state.value" :value="toOption(state.value)">{{ $t(`galleryPresets.${state.key}`) }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>

    <div class="mt-6 flex flex-col gap-3">
      <div class="flex items-center gap-2.5">
        <Checkbox
          :id="`design-${preset.id}`"
          :model-value="form.design !== null"
          @update:model-value="toggleDesign"
        />
        <Label variant="inline" :for="`design-${preset.id}`">{{ $t('galleryPresets.designLabel') }}</Label>
      </div>

      <template v-if="form.design">
        <div class="flex flex-col gap-1">
          <span class="pl-0.5 text-[10.5px] leading-none tracking-[0.05em] uppercase text-ink-2">{{ $t('galleryRecord.cover') }}</span>
          <ChoiceGroup
            v-model="form.design!.cover"
            :options="COVERS.map((option) => ({ value: option, label: $t(`galleryRecord.cover_${option}`) }))"
          />
        </div>

        <div class="flex flex-col gap-1">
          <span class="pl-0.5 text-[10.5px] leading-none tracking-[0.05em] uppercase text-ink-2">{{ $t('galleryRecord.colour') }}</span>
          <ChoiceGroup
            v-model="form.design!.colour"
            :options="COLOURS.map((option) => ({ value: option, label: $t(`galleryRecord.colour_${option}`) }))"
          />
        </div>

        <div class="flex flex-col gap-1">
          <span class="pl-0.5 text-[10.5px] leading-none tracking-[0.05em] uppercase text-ink-2">{{ $t('galleryRecord.grid') }}</span>
          <ChoiceGroup
            v-model="form.design!.grid"
            :options="GRIDS.map((option) => ({ value: option, label: $t(`galleryRecord.grid_${option}`) }))"
          />
        </div>
      </template>
    </div>

    <div class="mt-6 flex items-center gap-4 [&_[data-slot=button][data-variant=default]]:ml-0">
      <Button type="submit" :disabled="!form.isDirty || form.processing" :style="!form.isDirty ? 'opacity:.4' : undefined">
        {{ $t('common.save') }}
      </Button>
      <span v-if="form.isDirty" class="flex-none rounded-[var(--r-sm)] bg-due-bg px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase text-due">{{ $t('settings.unsaved') }}</span>
      <Button type="button" variant="outline" @click="remove">{{ $t('galleryPresets.delete') }}</Button>
    </div>
  </form>
</template>
