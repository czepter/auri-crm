<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { router, useForm } from '@inertiajs/vue3'
import GalleryDesignFields from '@/admin/components/gallery/GalleryDesignFields.vue'
import GalleryPreview from '@/admin/components/gallery/GalleryPreview.vue'
import GalleryReleaseFields, { type ReleasePatch } from '@/admin/components/gallery/GalleryReleaseFields.vue'
import { X } from 'lucide-vue-next'
import { addSet, releaseOf, withAccess, withoutSet, DEFAULT_RELEASE, type PresetRelease, type PresetSet } from '@/admin/lib/galleryPreset'
import { complete, type GalleryDesign } from '@/admin/lib/galleryDesign'
import { PreviewOverlay } from '@/ui/preview-overlay'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { ChoiceGroup } from '@/ui/choice-group'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Switch } from '@/ui/switch'
import Chip from '@/ui/page/Chip.vue'

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
  mailTemplates: { id: string; name: string }[]
  presetId?: string | null
  galleryHost: string
  previewTitle: string
  section: Section
}>()

const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()

type Section = 'standardwerte' | 'gestaltung' | 'vorlagen' | 'startseite'

const SECTIONS: Section[] = ['standardwerte', 'gestaltung', 'vorlagen', 'startseite']

const HOME_MODES = ['none', 'redirect', 'overview'] as const

const MODES = ['inherit', 'days', 'never']

const SET_ACCESS = ['internal', 'guests'] as const

const HEADING = 'mt-6 mb-1 text-small leading-none font-semibold text-faint'

const STUDIO_GATES = [
  { field: 'gallery_default_lists_enabled', label: 'gallerySettings.listsLabel' },
  { field: 'gallery_default_comments_enabled', label: 'gallerySettings.commentsLabel' },
] as const

const RULES = ['open', 'after_payment', 'off'] as const

const open = ref<Section>(props.section)

const form = useForm({
  gallery_default_expiry_days: props.settings.expiryDays ?? ('' as string | number),
  gallery_default_download_rule: props.settings.downloadRule,
  gallery_default_lists_enabled: props.settings.listsEnabled,
  gallery_default_comments_enabled: props.settings.commentsEnabled,
  gallery_default_design: complete(props.settings.design),
  gallery_home_mode: props.settings.homeMode,
  gallery_home_redirect_url: props.settings.homeRedirectUrl ?? '',
  gallery_home_noindex: props.settings.homeNoindex,
})

function save() {
  form
    .transform((data) => ({
      ...data,
      gallery_default_expiry_days: data.gallery_default_expiry_days === '' ? null : data.gallery_default_expiry_days,
      gallery_home_redirect_url: data.gallery_home_mode !== 'redirect' || data.gallery_home_redirect_url === '' ? null : data.gallery_home_redirect_url,
    }))
    .patch('/einstellungen/galerien', { preserveScroll: true, preserveState: true })
}

/* ---- Vorlagen ------------------------------------------------------ */

const chosen = ref(props.presets.find((row) => row.id === props.presetId)?.id ?? props.presets[0]?.id ?? '')
const preset = computed(() => props.presets.find((row) => row.id === chosen.value) ?? props.presets[0] ?? null)

const NO_MAIL = 'none'

// Offen gelassen heißt null, wie bei der Gestaltung: die Vorlage sagt zur
// Freigabe nichts, und die Galerie beginnt mit den Vorgaben.
const OPEN_RELEASE = {
  view_mode: null, download_rule: null, download_sizes: null, permissions: null, intro_enabled: null,
  downloads_enabled: null, lists_enabled: null, comments_enabled: null,
}

function fields(row: App.Gallery.Data.GalleryPresetData) {
  return {
    name: row.name,
    expiry_mode: row.expiryMode,
    expiry_days: (row.expiryDays ?? '') as string | number,
    expiry_reminder: row.expiryReminder ?? false,
    design: row.design as GalleryDesign | null,
    sets: (row.sets ?? []) as PresetSet[],
    release: releaseOf(row) as PresetRelease | null,
    mail_template_family_id: row.mailTemplateFamilyId ?? NO_MAIL,
  }
}

const presetForm = useForm(fields(preset.value ?? {
  name: '', expiryMode: 'inherit', expiryDays: null, design: null, expiryReminder: null, sets: null,
  viewMode: null, downloadRule: null, downloadSizes: null, permissions: null, introEnabled: null, mailTemplateFamilyId: null,
} as unknown as App.Gallery.Data.GalleryPresetData))

watch(preset, (row) => {
  if (row === null) return

  presetForm.defaults(fields(row))
  presetForm.reset()
})

function savePreset() {
  presetForm
    .transform(({ release, mail_template_family_id: mail, expiry_days: days, ...data }) => ({
      ...data,
      expiry_days: days === '' ? null : days,
      mail_template_family_id: mail === NO_MAIL ? null : mail,
      ...(release === null ? OPEN_RELEASE : release),
    }))
    .patch(`/einstellungen/galerie-vorlagen/${preset.value!.id}`, { preserveScroll: true })
}

// Ganz oder gar nicht, wie die Vorlage es speichert: erben heißt null, und
// wer eine eigene Gestaltung anschaltet, fängt bei der Studio-Vorgabe an.
function toggleDesign(on: boolean) {
  presetForm.design = on ? complete(props.settings.design) : null
  savePreset()
}

function toggleRelease(on: boolean) {
  presetForm.release = on ? { ...DEFAULT_RELEASE } : null
  savePreset()
}

function changeRelease(patch: ReleasePatch) {
  presetForm.release = { ...presetForm.release!, ...patch }
  savePreset()
}

const newSet = ref('')

function changeSets(sets: PresetSet[]) {
  presetForm.sets = sets
  savePreset()
}

function appendSet() {
  changeSets(addSet(presetForm.sets, newSet.value))
  newSet.value = ''
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
const presetPreview = computed(() => {
  const release = presetForm.release
  const any = (action: 'favorites' | 'comments') => release !== null && (release.permissions.client[action] || release.permissions.guest[action])

  return {
    design: presetForm.design ?? form.gallery_default_design,
    offers: release
      ? { downloads: release.download_rule !== 'off', lists: any('favorites'), comments: any('comments') }
      : {
          downloads: (preset.value?.downloadRule ?? props.settings.downloadRule) !== 'off',
          lists: preset.value?.listsEnabled ?? props.settings.listsEnabled,
          comments: preset.value?.commentsEnabled ?? props.settings.commentsEnabled,
        },
  }
})

const studioOffers = computed(() => ({
  downloads: form.gallery_default_download_rule !== 'off',
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
        <Chip v-if="form.isDirty" tone="warn">
          {{ $t('settings.unsaved') }}
        </Chip>
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

            <ChoiceGroup
              v-model="form.gallery_default_download_rule"
              class="mb-4"
              :label="$t('galleryRecord.downloadsHeading')"
              :options="RULES.map((rule) => ({ value: rule, label: $t(`galleries.download.${rule}`) }))"
              @update:model-value="save"
            />

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
          <div class="w-[360px] flex-none max-[1120px]:w-full">
            <h2 class="m-0 mb-6 text-title leading-[1.2] font-semibold tracking-[-0.02em]">
              {{ $t('gallerySettings.nav_gestaltung') }}
            </h2>

            <GalleryDesignFields v-model="form.gallery_default_design" @update:model-value="save" />
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

        <section v-else-if="open === 'startseite'" class="min-w-0 flex-1">
          <h2 class="m-0 mb-6 text-title leading-[1.2] font-semibold tracking-[-0.02em]">
            {{ $t('gallerySettings.nav_startseite') }}
            <a v-if="form.gallery_home_mode !== 'none'" class="ml-2 text-small font-normal text-ring no-underline hover:underline" :href="`https://${galleryHost}`">{{ galleryHost }}</a>
          </h2>

          <div class="w-[300px] max-[720px]:w-full">
            <ChoiceGroup
              v-model="form.gallery_home_mode"
              class="mb-4"
              :label="$t('gallerySettings.homeMode')"
              :options="HOME_MODES.map((mode) => ({ value: mode, label: $t(`gallerySettings.homeMode_${mode}`) }))"
              @update:model-value="save"
            />

            <Field v-if="form.gallery_home_mode === 'redirect'" v-slot="{ id }" :error="form.errors.gallery_home_redirect_url">
              <Label :for="id">{{ $t('gallerySettings.homeRedirectUrl') }}</Label>
              <Input :id v-model="form.gallery_home_redirect_url" type="url" @blur="save" />
            </Field>

            <div v-if="form.gallery_home_mode === 'overview'" class="flex items-center gap-4 border-b border-border py-2.5">
              <Label class="flex-1" for="home-noindex" variant="inline">{{ $t('gallerySettings.homeNoindex') }}</Label>
              <Switch id="home-noindex" v-model="form.gallery_home_noindex" @update:model-value="save" />
            </div>
          </div>
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
                <Chip v-if="row.isDefault" tone="info">
                  {{ $t('gallerySettings.defaultMark') }}
                </Chip>
              </button>

              <Button class="mt-1.5 ml-0" type="button" variant="ghost" @click="addPreset">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
                {{ $t('galleryPresets.add') }}
              </Button>
            </div>

            <div v-if="preset" class="w-[340px] flex-none max-[1120px]:w-full">
              <div class="flex items-center gap-4 border-b border-border py-2.5">
                <Label class="flex-1" for="preset-name" variant="inline">{{ $t('galleryPresets.nameField') }}</Label>
                <Input id="preset-name" v-model="presetForm.name" class="max-w-[160px]" maxlength="120" type="text" @change="savePreset" />
              </div>

              <div class="flex items-center gap-4 border-b border-border py-2.5">
                <Label class="flex-1" for="preset-default" variant="inline">{{ $t('gallerySettings.defaultLabel') }}</Label>
                <Switch id="preset-default" :model-value="preset.isDefault" :disabled="preset.isDefault" @update:model-value="markDefault" />
              </div>

              <h3 :class="HEADING">{{ $t('galleryPresets.structure') }}</h3>
              <div v-for="(set, index) in presetForm.sets" :key="index" class="flex items-center gap-2 border-b border-border py-2">
                <Input
                  :model-value="set.name"
                  :aria-label="$t('galleryPresets.nameField')"
                  class="min-w-0 flex-1"
                  maxlength="120"
                  type="text"
                  @change="(event: Event) => changeSets(presetForm.sets.map((one, at) => at === index ? { ...one, name: (event.target as HTMLInputElement).value.trim() || one.name } : one))"
                />
                <ChoiceGroup
                  :model-value="set.access"
                  :options="SET_ACCESS.map((option) => ({ value: option, label: $t(`galleryRecord.setAccess.${option}`) }))"
                  @update:model-value="(access) => changeSets(withAccess(presetForm.sets, index, access as PresetSet['access']))"
                />
                <button
                  :aria-label="$t('galleryPresets.removeSet')"
                  class="inline-flex cursor-pointer border-0 bg-transparent p-1 text-ink-2"
                  type="button"
                  @click="changeSets(withoutSet(presetForm.sets, index))"
                >
                  <X class="size-4" />
                </button>
              </div>
              <form class="flex items-center gap-2 py-2" @submit.prevent="appendSet">
                <Input v-model="newSet" :aria-label="$t('galleryPresets.nameField')" class="min-w-0 flex-1" maxlength="120" type="text" />
                <Button type="submit" variant="ghost" :disabled="newSet.trim() === ''">{{ $t('galleryPresets.addSet') }}</Button>
              </form>

              <h3 :class="HEADING">{{ $t('galleryPresets.design') }}</h3>
              <div class="flex items-center gap-4 border-b border-border py-2.5">
                <Label class="flex-1" for="preset-design" variant="inline">{{ $t('galleryPresets.designLabel') }}</Label>
                <Switch id="preset-design" :model-value="presetForm.design !== null" @update:model-value="toggleDesign" />
              </div>

              <div v-if="presetForm.design" class="pt-3.5">
                <GalleryDesignFields v-model="presetForm.design" @update:model-value="savePreset" />
              </div>

              <h3 :class="HEADING">{{ $t('galleryPresets.release') }}</h3>
              <div class="flex items-center gap-4 border-b border-border py-2.5">
                <Label class="flex-1" for="preset-release" variant="inline">{{ $t('galleryPresets.releaseLabel') }}</Label>
                <Switch id="preset-release" :model-value="presetForm.release !== null" @update:model-value="toggleRelease" />
              </div>

              <div v-if="presetForm.release" class="pt-3.5">
                <GalleryReleaseFields :model-value="presetForm.release" :watermark-field="false" @change="changeRelease" />
              </div>

              <h3 :class="HEADING">{{ $t('galleryPresets.expiryField') }}</h3>
              <div class="flex items-center gap-4 border-b border-border py-2.5">
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

              <div v-if="presetForm.expiry_mode !== 'never'" class="flex items-center gap-4 border-b border-border py-2.5">
                <Label class="flex-1" for="preset-reminder" variant="inline">{{ $t('galleryRecord.expiryReminder') }}</Label>
                <Switch id="preset-reminder" v-model="presetForm.expiry_reminder" @update:model-value="savePreset" />
              </div>

              <h3 :class="HEADING">{{ $t('galleryPresets.send') }}</h3>
              <div class="flex items-center gap-4 border-b border-border py-2.5">
                <Label class="flex-1" for="preset-mail" variant="inline">{{ $t('galleryPresets.mailTemplate') }}</Label>
                <Select v-model="presetForm.mail_template_family_id" @update:model-value="savePreset">
                  <SelectTrigger id="preset-mail" class="max-w-[180px]"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="NO_MAIL">{{ $t('galleryPresets.none') }}</SelectItem>
                    <SelectItem v-for="template in mailTemplates" :key="template.id" :value="template.id">{{ template.name }}</SelectItem>
                  </SelectContent>
                </Select>
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
