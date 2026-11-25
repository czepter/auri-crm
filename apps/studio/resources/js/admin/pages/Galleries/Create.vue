<script setup lang="ts">
import { ref, watch } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormSection from '@/ui/page/FormSection.vue'
import FormShell from '@/ui/page/FormShell.vue'
import CreateSummary from '@/admin/components/CreateSummary.vue'
import { ChoiceGroup } from '@/ui/choice-group'
import ProjectTypeahead from '@/admin/components/crm/ProjectTypeahead.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  mode: 'internal' | 'external'
  project: App.Crm.Data.CaseSuggestionData | null
  siblings: App.Gallery.Data.GalleryRowData[]
  address: string
  shootId: string | null
}>()

useLiveProps([])

// Die Liste und das Präfix gehören zum gewählten Projekt, das nur der Server kennt.
function loadSiblings(id: string | undefined) {
  router.get(page.url.split('?')[0], { projekt: id, art: props.mode === 'external' ? 'extern' : undefined }, { only: ['siblings', 'address'], preserveState: true, preserveScroll: true, replace: true })
}

const MODES = ['internal', 'external']

const page = usePage()

// The page URL, not '/galerien/neu': it carries projekt and shooting along.
function switchMode(next: string) {
  router.get(page.url, { art: next === 'external' ? 'extern' : 'intern' }, { preserveScroll: true, preserveState: true })
}

const project = ref<App.Crm.Data.CaseSuggestionData | null>(props.project)

const form = useForm({
  case_id: null as string | null,
  title: '',
  is_external: false,
  external_url: '',
  info: '',
  external_password: '',
})

watch(project, (next, previous) => next?.id !== previous?.id && loadSiblings(next?.id))

function submit() {
  form
    .transform((data) => ({
      ...data,
      case_id: project.value?.id ?? null,
      shoot_id: project.value?.id === props.project?.id ? props.shootId : null,
      is_external: props.mode === 'external',
    }))
    .post('/galerien')
}
</script>

<template>
  <AdminLayout active="galerien">
    <FormShell
      :kicker="$t('galleries.title')" kicker-href="/galerien" :title="$t('galleries.create')" wide
    >
      <form class="min-w-0" @submit.prevent="submit">
        <FormSection :title="$t('galleries.title')">
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
            <Input id="gallery-title" v-model="form.title" type="text" required />
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

        </div>
        </FormSection>

        <Button class="ml-auto mt-6" type="submit" :disabled="form.processing">
          {{ $t('galleries.create') }}
        </Button>
      </form>

      <template #aside>
        <CreateSummary
          :title="$t('common.summary')"
          :rows="[
            { label: $t('galleries.titleField'), value: form.title },
            { label: $t('projects.title'), value: project?.name },
            { label: $t('galleries.linkField'), value: mode === 'internal' ? address : null },
          ]"
        />

        <section v-if="siblings.length" class="mt-4 rounded-[var(--r)] bg-paper p-5 shadow-card">
          <h2 class="m-0 mb-2 text-ui font-semibold">{{ $t('galleries.title') }}</h2>
          <Link
            v-for="row in siblings"
            :key="row.id"
            :href="`/galerien/${row.id}`"
            class="flex justify-between gap-4 py-2 text-ui text-foreground no-underline shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
          >
            <span class="min-w-0 truncate">{{ row.title }}</span>
            <span class="flex-none text-ink-2">{{ row.assetCount }}</span>
          </Link>
        </section>
      </template>
    </FormShell>
  </AdminLayout>
</template>
