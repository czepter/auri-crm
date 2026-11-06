<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import { ChoiceGroup } from '@/ui/choice-group'
import ProjectTypeahead from '@/admin/components/crm/ProjectTypeahead.vue'
import { birthValues, expiryOn } from '@/admin/lib/galleryBirth'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { NONE, fromOption, toOption } from '@/lib/select'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  mode: 'internal' | 'external'
  project: App.Crm.Data.CaseSuggestionData | null
  shootId: string | null
  presets: App.Gallery.Data.GalleryPresetData[]
  studioDefaults: {
    downloads: boolean
    lists: boolean
    comments: boolean
    expiryDays: number | null
    design: Record<string, string>
  }
}>()

useLiveProps([])

const MODES = ['internal', 'external']

const page = usePage()

// The page URL, not '/galerien/neu': it carries projekt and shooting along.
function switchMode(next: string) {
  router.get(page.url, { art: next === 'external' ? 'extern' : 'intern' }, { preserveScroll: true, preserveState: true })
}

const project = ref<App.Crm.Data.CaseSuggestionData | null>(props.project)
// G-16: womit eine neue Galerie anfängt. Die Standard-Vorlage steht schon
// gewählt da, statt dass jeder sie aus der Liste sucht.
const presetId = ref(props.presets.find((row) => row.isDefault)?.id ?? '')

const preset = computed(() => props.presets.find((row) => row.id === presetId.value) ?? null)

const form = useForm({
  case_id: null as string | null,
  title: '',
  is_external: false,
  external_url: '',
  info: '',
  external_password: '',
  expires_at: '' as string,
  downloads_enabled: props.studioDefaults.downloads,
  lists_enabled: props.studioDefaults.lists,
  comments_enabled: props.studioDefaults.comments,
  design: { ...props.studioDefaults.design } as Record<string, string> | null,
})

watch([preset, project], () => {
  const born = birthValues(preset.value, project.value, props.studioDefaults)

  form.downloads_enabled = born.downloads
  form.lists_enabled = born.lists
  form.comments_enabled = born.comments
  form.design = preset.value?.design ?? { ...props.studioDefaults.design }
  form.expires_at = expiryOn(preset.value, props.studioDefaults, new Date())
}, { immediate: true })

// Eine externe Galerie liegt anderswo: sie hat weder eine Frist noch einen
// Download, den dieses System regeln koennte, also reist davon nichts mit.
function submit() {
  form
    .transform((data) => ({
      ...data,
      case_id: project.value?.id ?? null,
      shoot_id: project.value?.id === props.project?.id ? props.shootId : null,
      is_external: props.mode === 'external',
      ...(props.mode === 'external'
        ? {
            expires_at: '',
            design: null,
            downloads_enabled: true,
            lists_enabled: true,
            comments_enabled: true,
          }
        : {}),
    }))
    .post('/galerien')
}
</script>

<template>
  <AdminLayout active="galerien">
    <FormShell
      :title="$t('galleries.create')"
    >
      <form class="max-w-[620px]" @submit.prevent="submit">
        <ChoiceGroup
          :model-value="mode"
          :options="MODES.map((option) => ({ value: option, label: $t(`galleries.mode_${option}`) }))"
          @update:model-value="switchMode"
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

          <div v-if="mode === 'external'" class="flex flex-col gap-1.5">
            <Label for="gallery-password">{{ $t('galleries.passwordField') }}</Label>
            <Input id="gallery-password" v-model="form.external_password" type="text" />
          </div>

          <div v-if="mode === 'external'" class="flex flex-col gap-1.5">
            <Label for="gallery-info">{{ $t('galleries.infoField') }}</Label>
            <Textarea id="gallery-info" v-model="form.info" rows="3" />
          </div>

          <template v-else>
            <div class="flex flex-col gap-1.5">
              <Label for="gallery-preset">{{ $t('galleryPresets.title') }}</Label>
              <Select
                :model-value="toOption(presetId)"
                @update:model-value="(value) => presetId = fromOption(value)"
              >
                <SelectTrigger id="gallery-preset"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem :value="NONE">{{ $t('galleryPresets.none') }}</SelectItem>
                  <SelectItem v-for="row in presets" :key="row.id" :value="row.id">{{ row.name }}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex flex-col gap-1.5">
              <Label for="gallery-expiry">{{ $t('galleries.expiryField') }}</Label>
              <Input id="gallery-expiry" v-model="form.expires_at" type="date" />
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
    </FormShell>
  </AdminLayout>
</template>
