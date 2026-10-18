<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import TaskTemplateForm from '@/admin/components/templates/TaskTemplateForm.vue'
import TemplateCreateDialog from '@/admin/components/templates/TemplateCreateDialog.vue'
import TaskTemplatePreview from '@/admin/components/templates/TaskTemplatePreview.vue'
import { Input } from '@/ui/input'

type Kind = 'aufgaben' | 'fragebogen' | 'dokumente' | 'galerien'

const KIND_LABEL: Record<Kind, string> = {
  aufgaben: 'templates.tasks',
  fragebogen: 'templates.forms',
  dokumente: 'templates.documents',
  galerien: 'templates.galleries',
}

type Row = {
  id: string
  name: string
  meta: string
  note: string
  active: boolean
  href?: string
}

const props = defineProps<{
  kind: Kind | null
  kinds: Kind[]
  counts: Record<Kind, number>
  taskTemplates: App.Crm.Data.TaskTemplateData[]
  formTemplates: App.Crm.Data.FormTemplateData[]
  documentTemplates: App.Documents.Data.DocumentTemplateData[]
  galleryPresets: App.Gallery.Data.GalleryPresetData[]
  audiences: { value: string; label: string }[]
  shootKinds: string[]
  projectTypes: { value: string; label: string }[]
  selected: string | null
  previewDate: string | null
  previewShootKind: string | null
  preview: { title: string; dueOn: string | null }[] | null
}>()

const { t } = useI18n()

const query = ref('')

function offset(days: number): string {
  if (days === 0) return t('templates.offsetSame')

  return days < 0
    ? t('templates.offsetBefore', { count: Math.abs(days) })
    : t('templates.offsetAfter', { count: days })
}

const rows = computed<Record<Kind, Row[]>>(() => ({
  aufgaben: props.taskTemplates.map((template) => ({
    id: template.id,
    name: template.title,
    meta: [template.audienceLabel, template.shootKind ?? t('taskTemplates.shootKindAll'), offset(template.dayOffset)].join(' · '),
    note: '',
    active: template.isActive,
  })),
  fragebogen: props.formTemplates.map((template) => ({
    id: template.id,
    name: template.name,
    meta: [template.projectTypeLabel ?? t('formTemplates.projectTypeAll'), t('templates.questions', template.fieldCount)].join(' · '),
    note: '',
    active: template.isActive,
    href: `/einstellungen/fragebogen-vorlagen/${template.id}`,
  })),
  dokumente: props.documentTemplates.map((template) => ({
    id: template.id,
    name: template.name,
    meta: [template.typeLabel, template.shootKind, t('templates.version', { version: template.version })]
      .filter(Boolean).join(' · '),
    note: '',
    active: true,
    href: `/dokumentvorlagen/${template.id}`,
  })),
  galerien: props.galleryPresets.map((preset) => ({
    id: preset.id,
    name: preset.name,
    meta: preset.expiryMode === 'days'
      ? t('galleryPresets.days', { count: preset.expiryDays })
      : t(`galleryPresets.mode_${preset.expiryMode}`),
    note: '',
    active: true,
    href: '/galerien?einstellungen=vorlagen',
  })),
}))

// Eine Suche über die geladene Liste, nicht über die Datenbank: 28 Vorlagen
// sind schon da, und eine Server-Runde für ein Wort wäre eine Runde mehr.
function matching(list: Row[]): Row[] {
  const needle = query.value.trim().toLowerCase()

  return needle === ''
    ? list
    : list.filter((row) => `${row.name} ${row.meta}`.toLowerCase().includes(needle))
}

const total = computed(() => props.kinds.reduce((sum, kind) => sum + props.counts[kind], 0))

// Nur Aufgaben werden hier bearbeitet; Fragebögen, Dokumente und seit G-16
// die Galerie-Vorlagen haben ihren eigenen Editor, den die Zeile öffnet.
const editable = computed(() => props.kind === 'aufgaben')

const current = computed(() => {
  if (!editable.value) return null

  return props.taskTemplates.find((entry) => entry.id === props.selected) ?? props.taskTemplates[0] ?? null
})

function open(row: Row) {
  if (row.href) {
    router.get(row.href)

    return
  }

  router.get('/vorlagen', { typ: props.kind, vorlage: row.id }, { preserveScroll: true, preserveState: true })
}
</script>

<template>
  <AdminLayout active="vorlagen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('templates.title')">
          <div class="ml-auto flex items-center gap-2.5">
            <Input v-model="query" class="w-[220px]" type="search" :placeholder="$t('templates.search')" />
            <TemplateCreateDialog
              :kinds="kinds"
              :kind="kind"
              :audiences="audiences"
              :shoot-kinds="shootKinds"
              :project-types="projectTypes"
            />
          </div>
        </PageHead>

        <div class="flex flex-wrap items-center gap-2 px-6 pt-5">
          <Link
            class="rounded-lg px-2.5 py-[5px] text-small leading-[1.3] no-underline"
            :class="kind === null ? 'bg-foreground text-background' : 'bg-sunk-2 text-foreground'"
            href="/vorlagen"
          >
            {{ $t('templates.all') }}
            <span class="ml-1.5" :class="kind === null ? 'text-faint' : 'text-ink-2'">{{ total }}</span>
          </Link>
          <Link
            v-for="entry in kinds"
            :key="entry"
            class="rounded-lg px-2.5 py-[5px] text-small leading-[1.3] no-underline"
            :class="kind === entry ? 'bg-foreground text-background' : 'bg-sunk-2 text-foreground'"
            :href="`/vorlagen?typ=${entry}`"
          >
            {{ $t(KIND_LABEL[entry]) }}
            <span class="ml-1.5" :class="kind === entry ? 'text-faint' : 'text-ink-2'">{{ counts[entry] }}</span>
          </Link>
        </div>

        <div class="px-6 pt-6 pb-8">
          <!-- Übersicht: jede Art als eigene Karte, in der Reihenfolge der Chips. -->
          <div v-if="kind === null" class="max-w-[980px]">
            <section v-for="entry in kinds" :key="entry" class="mt-7 first:mt-0">
              <div class="mb-2.5 text-small leading-none font-semibold text-faint">
                {{ $t(KIND_LABEL[entry]) }}
              </div>

              <div v-if="matching(rows[entry]).length" class="overflow-hidden rounded-[var(--r)] bg-paper shadow-card">
                <component
                  :is="row.href ? Link : 'button'"
                  v-for="row in matching(rows[entry]).slice(0, 4)"
                  :key="row.id"
                  class="flex w-full cursor-pointer items-center gap-3.5 border-t border-border bg-transparent p-2.5 text-left no-underline transition-[background-color] duration-[.12s] ease-out first:border-t-0 hover:bg-muted"
                  v-bind="row.href ? { href: row.href } : { type: 'button', onClick: () => open(row) }"
                >
                  <span class="min-w-0 flex-1">
                    <span class="block text-small leading-[1.3] font-semibold" :class="!row.active && 'text-muted-foreground'">{{ row.name }}</span>
                    <span class="mt-px block text-small leading-[1.3] text-muted-foreground">{{ row.meta }}</span>
                  </span>
                  <span v-if="row.note" class="flex-none text-small tabular-nums text-faint">{{ row.note }}</span>
                  <span
                    v-if="!row.active"
                    class="flex-none rounded-[var(--r-sm)] bg-muted px-2 py-[3px] text-small leading-[1.5] font-semibold text-faint"
                  >{{ $t('formTemplates.paused') }}</span>
                </component>

                <Link
                  v-if="matching(rows[entry]).length > 4"
                  class="flex items-center gap-2.5 border-t border-border p-2.5 text-small leading-[1.3] text-muted-foreground no-underline hover:bg-muted"
                  :href="`/vorlagen?typ=${entry}`"
                >{{ $t('templates.more', { count: matching(rows[entry]).length - 4 }) }}</Link>
              </div>

              <p v-else class="m-0 text-small leading-[1.45] text-faint">{{ $t('templates.empty') }}</p>
            </section>
          </div>

          <!-- Eine Art: Liste links, ihr Editor rechts. -->
          <div v-else class="flex items-start gap-7 max-[1080px]:flex-col">
            <div class="flex w-[392px] flex-none flex-col gap-0.5 max-[1080px]:w-full">
              <component
                :is="row.href ? Link : 'button'"
                v-for="row in matching(rows[kind])"
                :key="row.id"
                class="flex cursor-pointer items-center gap-3.5 rounded-lg border-0 bg-transparent p-2.5 text-left no-underline transition-[background-color] duration-[.12s] ease-out hover:bg-muted"
                :class="current && row.id === current.id && 'bg-sunk-2'"
                v-bind="row.href ? { href: row.href } : { type: 'button', onClick: () => open(row) }"
              >
                <span class="min-w-0 flex-1">
                  <span class="block text-small leading-[1.3] font-semibold" :class="!row.active && 'text-muted-foreground'">{{ row.name }}</span>
                  <span class="mt-px block text-small leading-[1.3] text-muted-foreground">{{ row.meta }}</span>
                </span>
                <span v-if="row.note" class="flex-none text-small tabular-nums text-faint">{{ row.note }}</span>
                <span
                  v-if="!row.active"
                  class="flex-none rounded-[var(--r-sm)] bg-muted px-2 py-[3px] text-small leading-[1.5] font-semibold text-faint"
                >{{ $t('formTemplates.paused') }}</span>
              </component>

              <p v-if="!matching(rows[kind]).length" class="m-0 text-small leading-[1.45] text-faint">{{ $t('templates.empty') }}</p>
            </div>

            <div v-if="editable && current" class="min-w-0 max-w-[600px] flex-1">
              <TaskTemplateForm
                :key="current.id"
                :template="current"
                :audiences="audiences"
                :shoot-kinds="shootKinds"
              />

              <TaskTemplatePreview
                class="mt-8"
                :shoot-kinds="shootKinds"
                :preview="preview"
                :preview-date="previewDate"
                :preview-shoot-kind="previewShootKind"
                :selected="current.id"
              />
            </div>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
