<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/crm/ProjectShell.vue'
import ContractWizard from '@/admin/components/templates/ContractWizard.vue'
import DocumentEditor from '@/admin/components/templates/DocumentEditor.vue'
import DocumentPreviewPane from '@/admin/components/templates/DocumentPreviewPane.vue'
import Section from '@/ui/page/Section.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { NONE, fromOption, toOption } from '@/lib/select'
import { dayWithWeekday } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'
import { htmlToBlocks } from '@/admin/documents/blocks/importHtml'
import { serialize } from '@/admin/documents/blocks/serialize'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'
import Chip, { type ChipTone } from '@/ui/page/Chip.vue'

const props = defineProps<{
  header: App.Crm.Screens.CaseHeaderData
  templates: { value: string; label: string }[]
  contract: App.Contracts.Data.ContractData | null
  versions: App.Contracts.Data.ContractData[]
  contacts: { value: string; label: string }[]
  palette: PaletteEntry[]
  mergeFields: { path: string; sample: string }[]
  clauses: { key: string; group: string; title: string; text: string; on: boolean }[]
}>()
useLiveProps(['header', 'templates', 'contract', 'versions', 'contacts', 'clauses'])

const { t } = useI18n()

const generate = useForm({ template: props.templates[0]?.value ?? '' })

const samples = computed(() =>
  Object.fromEntries(props.mergeFields.map((field) => [field.path, field.sample])),
)

const doc = ref<EditorDocument>(blocksOf(props.contract))

function blocksOf(contract: App.Contracts.Data.ContractData | null): EditorDocument {
  if (contract === null) return { blocks: [] }

  return (contract.blocks as EditorDocument | null) ?? htmlToBlocks(contract.body, samples.value)
}

// Saving against a sent Fassung answers with a new one — a different id, a
// different wording of record. The editor follows that row rather than
// keeping the document it was opened with.
watch(() => props.contract?.id, () => (doc.value = blocksOf(props.contract)))

// The preview reckons against this project by default; the sample values are
// the fallback for judging a placeholder that has nothing behind it yet.
const previewProject = ref<string | null>(props.header.id)

const signed = computed(() => props.versions.find((version) => version.state === 'valid' && version.signers.some((s) => s.signedAt)))

const unsigned = computed(() => props.contract?.signers.filter((entry) => !entry.signedAt) ?? [])

const status = computed<'draft' | 'sent' | 'signed'>(() => {
  if (props.contract?.state === 'draft') return 'draft'

  return props.contract?.signers.length && !unsigned.value.length ? 'signed' : 'sent'
})

const statusChip = { draft: 'neutral', sent: 'warn', signed: 'success' } as const

const editing = ref(false)
// The editor's save is the one dark button while it is open.
const lead = computed(() => (editing.value ? 'outline' : 'default'))
const saving = ref(false)

function save() {
  saving.value = true
  router.patch(
    `/vertraege/${props.contract!.id}`,
    { body: serialize(doc.value, 'print'), blocks: doc.value },
    { preserveScroll: true, onFinish: () => (saving.value = false) },
  )
}

function setDeadline(value: string) {
  router.patch(
    `/vertraege/${props.contract!.id}/frist`,
    { sign_deadline_on: value === '' ? null : value },
    { preserveScroll: true }
  )
}

function send() {
  if (props.contract!.signers.length) {
    router.visit(`/vertraege/${props.contract!.id}/senden`)
    return
  }

  router.post(`/vertraege/${props.contract!.id}/versenden`, {}, { preserveScroll: true })
}

function addSigner(contact: string) {
  router.post(`/vertraege/${props.contract!.id}/unterzeichner`, { contact }, { preserveScroll: true })
}

function pickSigners() {
  document.getElementById('contract-signers')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function removeSigner(id: string) {
  router.delete(`/unterzeichner/${id}`, { preserveScroll: true })
}

function remind(id: string) {
  router.post(`/unterzeichner/${id}/erinnerung`, {}, { preserveScroll: true })
}

function remindAll(ids = unsigned.value.map((entry) => entry.id)) {
  const [id, ...rest] = ids

  router.post(`/unterzeichner/${id}/erinnerung`, {}, { preserveScroll: true, onSuccess: () => rest.length && remindAll(rest) })
}

function chipTone(state: string): ChipTone {
  return ({ valid: 'success', superseded: 'neutral' } as Record<string, ChipTone>)[state] ?? 'warn'
}

function day(value: string | null): string {
  return dayWithWeekday(value, '') ?? ''
}
</script>

<template>
  <AdminLayout :active="header.isBooked ? 'projekte' : 'anfragen'">
    <ProjectShell :header="header" active="contract">
      <div v-if="contract" class="@container">
      <div class="grid items-start gap-6 @[1280px]:grid-cols-[560px_minmax(0,1fr)]">
        <div class="flex min-w-0 flex-col gap-3">
          <section class="surface-card p-6 max-[640px]:p-4">
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="m-0 text-[15px] leading-[1.2] font-semibold tracking-[-0.015em]">{{ $t('project.contract') }}</h2>
              <Chip :tone="statusChip[status]">{{ $t(`project.contractState.${status}`) }}</Chip>
              <span v-if="contract.sentAt" class="text-small text-faint">{{ $t('project.contractSentOn', { date: day(contract.sentAt) }) }}</span>

              <div v-if="header.canManageContracts" class="ml-auto flex items-center gap-2">
                <template v-if="status === 'draft'">
                  <Button v-if="!contract.signers.length" :variant="lead" type="button" @click="pickSigners">
                    {{ $t('project.contractSignersPick') }}
                  </Button>
                  <Button :variant="contract.signers.length ? lead : 'outline'" type="button" :disabled="!contract.signers.length" @click="send">
                    {{ $t('project.contractSend') }}
                  </Button>
                </template>
                <Button v-else-if="status === 'sent' && !contract.signers.length" :variant="lead" type="button" @click="pickSigners">
                  {{ $t('project.contractSignersPick') }}
                </Button>
                <Button v-else-if="status === 'sent' && unsigned.length" :variant="lead" type="button" @click="remindAll()">
                  {{ $t('project.contractRemind') }}
                </Button>
                <Button v-else-if="contract.downloadUrl" as="a" :variant="lead" :href="contract.downloadUrl" download>
                  {{ $t('project.contractDownload') }}
                </Button>
              </div>
            </div>

            <p v-if="signed && contract.state === 'draft'" class="mt-3 mb-0 text-small leading-[1.45] text-faint">
              {{ $t('project.contractHoldsUntilSent', { version: signed.version }) }}
            </p>

            <h3 id="contract-signers" class="mt-6 mb-0 text-small leading-none font-semibold text-faint">{{ $t('project.contractSigners') }}</h3>

            <ul class="mt-3 flex list-none flex-col gap-3 p-0">
              <li v-for="entry in contract.signers" :key="entry.id" class="flex items-center gap-3">
                <div class="flex min-w-0 flex-col gap-0.5">
                  <div class="flex items-baseline gap-2">
                    <span class="text-small font-medium">{{ entry.name }}</span>
                    <span v-if="entry.role" class="text-small text-faint">{{ entry.role }}</span>
                  </div>
                  <span v-if="entry.signedAt" class="text-small text-ok">
                    {{ $t('project.contractSigned', { date: day(entry.signedAt) }) }}
                  </span>
                  <span v-else-if="entry.remindedAt" class="text-small text-ink-2">
                    {{ $t('project.contractReminded', { date: day(entry.remindedAt) }) }}
                  </span>
                  <span v-else class="text-small text-faint">{{ $t('project.contractSignerPending') }}</span>
                </div>

                <div v-if="header.canManageContracts && !entry.signedAt" class="ml-auto flex gap-2">
                  <Button variant="outline" type="button" @click="remind(entry.id)">
                    {{ $t('project.contractRemind') }}
                  </Button>
                  <Button variant="outline" type="button" @click="removeSigner(entry.id)">
                    {{ $t('common.remove') }}
                  </Button>
                </div>
              </li>
            </ul>

            <ul v-if="header.canManageContracts && contacts.length" class="mt-3 flex list-none flex-col gap-3 p-0">
              <li v-for="option in contacts" :key="option.value" class="flex items-center gap-3">
                <span class="min-w-0 text-small font-medium">{{ option.label }}</span>
                <Button class="ml-auto" variant="outline" type="button" @click="addSigner(option.value)">
                  {{ $t('project.contractSignerAdd') }}
                </Button>
              </li>
            </ul>

            <h3 class="mt-6 mb-0 text-small leading-none font-semibold text-faint">{{ $t('project.contractDeadline') }}</h3>

            <DatePicker
              v-if="header.canManageContracts"
              class="mt-2"
              :model-value="contract.signDeadlineOn ?? ''"
              @update:model-value="setDeadline"
            />
            <p v-else class="mt-2 mb-0 text-small">{{ day(contract.signDeadlineOn) }}</p>
          </section>

          <section class="surface-card p-6 max-[640px]:p-4">
            <h3 class="m-0 text-small leading-none font-semibold text-faint">{{ $t('project.contractVersions') }}</h3>

            <ul class="mt-3 flex list-none flex-col gap-2.5 p-0">
              <li v-for="version in versions" :key="version.id" class="flex items-baseline gap-3">
                <span class="text-small">{{ $t('project.contractVersionLabel', { version: version.version }) }}</span>
                <Chip :tone="chipTone(version.state)">{{ version.stateLabel }}</Chip>
                <span class="text-small text-faint">{{ day(version.sentAt ?? version.renderedAt) }}</span>
                <a v-if="version.downloadUrl" class="ml-auto text-small text-ink-2 underline" :href="version.downloadUrl" download>
                  {{ $t('project.contractDownload') }}
                </a>
              </li>
            </ul>
          </section>
        </div>

        <div class="flex min-w-0 flex-col gap-3">
          <div v-if="header.canManageContracts" class="flex items-center justify-end gap-2">
            <Button v-if="editing" variant="default" type="button" :disabled="saving" @click="save">
              {{ $t('project.contractSave') }}
            </Button>
            <Button variant="outline" type="button" @click="editing = !editing">
              {{ $t(editing ? 'project.contractEditDone' : 'project.contractEdit') }}
            </Button>
          </div>

          <section v-if="editing" class="flex min-w-0 flex-col">
            <div class="flex items-center gap-2.5 border-b border-border pb-2.5">
              <span class="mb-[5px] text-small leading-none font-semibold text-faint">{{ $t('documentTemplates.body') }}</span>
              <span class="ml-auto text-small leading-[1.45] text-faint">{{ $t('documentTemplates.bodyHint') }}</span>
            </div>
            <div class="mx-auto mt-6 w-full max-w-[794px] rounded-[2px] border border-border bg-white px-10 pb-10">
              <div class="-mx-10 mb-6 border-b border-dashed border-line-hover bg-sunk px-3 py-[9px] text-small leading-[1.4] font-medium text-faint">{{ $t('documentTemplates.letterhead') }}</div>
              <DocumentEditor :key="contract.id" v-model="doc" :palette="palette" :merge-fields="mergeFields" />
              <div class="-mx-10 -mb-10 mt-6 border-t border-dashed border-line-hover bg-sunk px-3 py-[9px] text-small leading-[1.4] font-medium text-faint">{{ $t('documentTemplates.footer') }}</div>
            </div>
          </section>

          <DocumentPreviewPane v-else type="contract" :doc="doc" :samples="samples" :project="previewProject">
            <Select
              :model-value="toOption(previewProject)"
              @update:model-value="(value) => (previewProject = fromOption(value) || null)"
            >
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem :value="header.id">{{ $t('project.contractProjectData') }}</SelectItem>
                <SelectItem :value="NONE">{{ $t('documentTemplates.previewSamples') }}</SelectItem>
              </SelectContent>
            </Select>
          </DocumentPreviewPane>
        </div>
      </div>
      </div>

      <Section v-else :title="$t('project.contract')">
        <p
          v-if="!header.canManageContracts || !templates.length"
          class="text-small leading-[1.45] text-faint"
        >{{ $t('project.contractNone') }}</p>

        <div v-if="header.canManageContracts && templates.length" class="mb-6">
          <ContractWizard :project-id="header.id" :templates="templates" :clauses="clauses" />
        </div>

        <form v-if="header.canManageContracts && templates.length" @submit.prevent="generate.post(`/auftraege/${header.id}/vertraege`, { preserveScroll: true })">
          <div class="mb-6 flex min-w-0 max-w-[280px] flex-col gap-1.5">
            <Label for="template">{{ $t('project.contractTemplate') }}</Label>
            <Select :model-value="generate.template" @update:model-value="(value) => (generate.template = String(value))">
              <SelectTrigger id="template">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in templates" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <Button class="ml-auto" type="submit" :disabled="generate.processing">
            {{ $t('project.contractGenerate') }}
          </Button>
        </form>
        <Button class="ml-auto" v-else-if="header.canManageContracts" :as="Link" href="/dokumentvorlagen/neu">
          {{ $t('project.contractTemplateCreate') }}
        </Button>
      </Section>
    </ProjectShell>
  </AdminLayout>
</template>
