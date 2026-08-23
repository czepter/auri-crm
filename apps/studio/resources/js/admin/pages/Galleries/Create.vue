<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { ChoiceGroup } from '@/ui/choice-group'
import ProjectTypeahead from '@/admin/components/ProjectTypeahead.vue'
import { birthValues } from '@/admin/lib/galleryBirth'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { NativeSelect } from '@/ui/native-select'

const props = defineProps<{
  presets: App.Gallery.Data.GalleryPresetData[]
  studioDefaults: {
    downloads: boolean
    lists: boolean
    comments: boolean
    expiryDays: number | null
    requiresSettledBalance: boolean
  }
}>()

const MODES = ['internal', 'external']

const mode = ref('internal')
const project = ref<App.Crm.Data.ProjectSuggestionData | null>(null)
const presetId = ref('')

const preset = computed(() => props.presets.find((row) => row.id === presetId.value) ?? null)

const form = useForm({
  project_id: null as string | null,
  title: '',
  is_external: false,
  external_url: '',
  expires_at: '' as string,
  requires_settled_balance: props.studioDefaults.requiresSettledBalance,
  downloads_enabled: props.studioDefaults.downloads,
  lists_enabled: props.studioDefaults.lists,
  comments_enabled: props.studioDefaults.comments,
  design: null as Record<string, string> | null,
})

function expiryFrom(chosen: App.Gallery.Data.GalleryPresetData | null) {
  const days =
    chosen === null || chosen.expiryMode === 'inherit'
      ? props.studioDefaults.expiryDays
      : chosen.expiryMode === 'never'
        ? null
        : chosen.expiryDays

  if (days === null) {
    return ''
  }

  const date = new Date()
  date.setDate(date.getDate() + days)

  return date.toISOString().slice(0, 10)
}

watch([preset, project], () => {
  const born = birthValues(preset.value, project.value, props.studioDefaults)

  form.downloads_enabled = born.downloads
  form.lists_enabled = born.lists
  form.comments_enabled = born.comments
  form.requires_settled_balance =
    preset.value?.requiresSettledBalance ?? props.studioDefaults.requiresSettledBalance
  form.design = preset.value?.design ?? null
  form.expires_at = expiryFrom(preset.value)
})

function submit() {
  form
    .transform((data) => ({
      ...data,
      project_id: project.value?.id ?? null,
      is_external: mode.value === 'external',
    }))
    .post('/galerien')
}
</script>

<template>
  <AdminLayout active="galerien">
    <div data-tokens>
      <PageHead :title="$t('galleries.create')" />

      <form class="max-w-[620px] px-8 pt-7 pb-8" @submit.prevent="submit">
        <ChoiceGroup
          v-model="mode"
          :options="MODES.map((option) => ({ value: option, label: $t(`galleries.mode_${option}`) }))"
        />

        <div class="mt-6 flex flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <Label for="gallery-project">{{ $t('projects.title') }}</Label>
            <ProjectTypeahead id="gallery-project" v-model="project" />
          </div>

          <div class="flex flex-col gap-1.5">
            <Label for="gallery-title">{{ $t('galleries.titleField') }}</Label>
            <Input id="gallery-title" v-model="form.title" type="text" />
          </div>

          <div v-if="mode === 'external'" class="flex flex-col gap-1.5">
            <Label for="gallery-url">{{ $t('galleries.urlField') }}</Label>
            <Input id="gallery-url" v-model="form.external_url" type="url" />
          </div>

          <template v-else>
            <div class="flex flex-col gap-1.5">
              <Label for="gallery-preset">{{ $t('galleryPresets.title') }}</Label>
              <NativeSelect id="gallery-preset" v-model="presetId">
                <option value="">{{ $t('galleryPresets.none') }}</option>
                <option v-for="row in presets" :key="row.id" :value="row.id">{{ row.name }}</option>
              </NativeSelect>
            </div>

            <div class="flex flex-col gap-1.5">
              <Label for="gallery-expiry">{{ $t('galleries.expiryField') }}</Label>
              <Input id="gallery-expiry" v-model="form.expires_at" type="date" />
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="cb-new-balance" v-model="form.requires_settled_balance" />
              <Label variant="inline" for="cb-new-balance">{{ $t('gallerySettings.balanceLabel') }}</Label>
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="cb-new-downloads" v-model="form.downloads_enabled" />
              <Label variant="inline" for="cb-new-downloads">{{ $t('gallerySettings.downloadsLabel') }}</Label>
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="cb-new-lists" v-model="form.lists_enabled" />
              <Label variant="inline" for="cb-new-lists">{{ $t('gallerySettings.listsLabel') }}</Label>
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="cb-new-comments" v-model="form.comments_enabled" />
              <Label variant="inline" for="cb-new-comments">{{ $t('gallerySettings.commentsLabel') }}</Label>
            </div>
          </template>
        </div>

        <Button class="mt-6" type="submit" :disabled="form.processing || form.title === ''">
          {{ $t('galleries.create') }}
        </Button>
      </form>
    </div>
  </AdminLayout>
</template>
