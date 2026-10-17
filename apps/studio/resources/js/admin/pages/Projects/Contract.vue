<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import ContractWizard from '@/admin/components/ContractWizard.vue'
import DocumentEditor from '@/admin/components/DocumentEditor.vue'
import DocumentPreviewPane from '@/admin/components/DocumentPreviewPane.vue'
import { PreviewOverlay } from '@/ui/preview-overlay'
import Card from '@/ui/page/Card.vue'
import Rail from '@/ui/page/Rail.vue'
import Section from '@/ui/page/Section.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { NONE, fromOption, toOption } from '@/lib/select'
import { dayWithWeekday } from '@/admin/lib/dates'
import { htmlToBlocks } from '@/admin/documents/blocks/importHtml'
import { serialize } from '@/admin/documents/blocks/serialize'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

const props = defineProps<{
  header: App.Crm.Data.CaseHeaderData
  templates: { value: string; label: string }[]
  contract: App.Contracts.Data.ContractData | null
  versions: App.Contracts.Data.ContractData[]
  contacts: { value: string; label: string }[]
  palette: PaletteEntry[]
  mergeFields: { path: string; sample: string }[]
  clauses: { key: string; group: string; title: string; text: string; on: boolean }[]
}>()

const { t } = useI18n()

const generate = useForm({ template: props.templates[0]?.value ?? '' })
const signer = useForm({ contact: '' })

const doc = ref<EditorDocument>(blocksOf(props.contract))

function blocksOf(contract: App.Contracts.Data.ContractData | null): EditorDocument {
  if (contract === null) return { blocks: [] }

  return (contract.blocks as EditorDocument | null) ?? htmlToBlocks(contract.body)
}

// Saving against a sent Fassung answers with a new one — a different id, a
// different wording of record. The editor follows that row rather than
// keeping the document it was opened with.
watch(() => props.contract?.id, () => (doc.value = blocksOf(props.contract)))

const samples = computed(() =>
  Object.fromEntries(props.mergeFields.map((field) => [field.path, field.sample])),
)

// The preview reckons against this project by default; the sample values are
// the fallback for judging a placeholder that has nothing behind it yet.
const previewProject = ref<string | null>(props.header.id)

const signed = computed(() => props.versions.find((version) => version.state === 'valid' && version.signers.some((s) => s.signedAt)))

const documentSummary = computed(() =>
  props.contract === null
    ? null
    : [
        `${props.contract.templateName} · v${props.contract.templateVersion}`,
        t('project.contractVersionLabel', { version: props.contract.version }),
        props.contract.stateLabel,
      ].join(' · '),
)

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

function addSigner() {
  signer.post(`/vertraege/${props.contract!.id}/unterzeichner`, {
    preserveScroll: true,
    onSuccess: () => signer.reset(),
  })
}

function removeSigner(id: string) {
  router.delete(`/unterzeichner/${id}`, { preserveScroll: true })
}

function remind(id: string) {
  router.post(`/unterzeichner/${id}/erinnerung`, {}, { preserveScroll: true })
}

/** entwurf/gültig/ersetzt -> die drei Zustände, die tokens.css als Chip kennt. */
function chipClass(state: string): string {
  return ({ valid: 'done', superseded: 'off' } as Record<string, string>)[state] ?? 'now'
}

function day(value: string | null): string {
  return dayWithWeekday(value, '') ?? ''
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectShell :header="header" active="documents" child="contract">
      <template #rail>
        <Rail v-if="contract">
          <h3 class="m-0 text-small leading-none font-semibold text-faint">
            {{ $t('project.contractSigners') }}
          </h3>

          <ul class="mt-3 flex list-none flex-col gap-2.5 p-0">
            <li v-for="entry in contract.signers" :key="entry.id" class="flex flex-col gap-0.5">
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

              <div v-if="header.canManageContracts && !entry.signedAt" class="flex gap-2">
                <Button variant="outline" type="button" @click="remind(entry.id)">
                  {{ $t('project.contractRemind') }}
                </Button>
                <Button variant="outline" type="button" @click="removeSigner(entry.id)">
                  {{ $t('common.remove') }}
                </Button>
              </div>
            </li>
          </ul>

          <form v-if="header.canManageContracts && contacts.length" class="mt-3 flex flex-col gap-1.5" @submit.prevent="addSigner">
            <Select :model-value="signer.contact" @update:model-value="(value) => (signer.contact = String(value))">
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in contacts" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <Button type="submit" variant="outline" :disabled="!signer.contact || signer.processing">
              {{ $t('project.contractSignerAdd') }}
            </Button>
          </form>

          <h3 class="mt-7 m-0 text-small leading-none font-semibold text-faint">
            {{ $t('project.contractDeadline') }}
          </h3>

          <DatePicker
            v-if="header.canManageContracts"
            class="mt-2"
            :model-value="contract.signDeadlineOn ?? ''"
            @update:model-value="setDeadline"
          />

          <h3 class="mt-7 m-0 text-small leading-none font-semibold text-faint">
            {{ $t('project.contractVersions') }}
          </h3>

          <ul class="mt-3 flex list-none flex-col gap-2.5 p-0">
            <li v-for="version in versions" :key="version.id" class="flex flex-col gap-0.5">
              <span class="text-small text-faint">{{ day(version.sentAt ?? version.renderedAt) }}</span>
              <div class="flex items-baseline gap-2">
                <span class="text-small">{{ $t('project.contractVersionLabel', { version: version.version }) }}</span>
                <span :class="['chip', chipClass(version.state)]">{{ version.stateLabel }}</span>
              </div>
              <a v-if="version.downloadUrl" class="text-small text-ink-2 underline" :href="version.downloadUrl" download>
                {{ $t('project.contractDownload') }}
              </a>
            </li>
          </ul>
        </Rail>
      </template>

      <Card v-if="contract" :title="$t('project.contract')" :summary="documentSummary">
        <template #action>
          <Button v-if="contract.downloadUrl" as="a" variant="outline" :href="contract.downloadUrl" download @click.stop>
            {{ $t('project.contractDownload') }}
          </Button>

          <template v-if="header.canManageContracts">
            <Button variant="outline" type="button" :disabled="saving" @click.stop="save">
              {{ $t('project.contractSave') }}
            </Button>
            <Button v-if="contract.state === 'draft'" variant="pill" type="button" @click.stop="send">
              {{ $t('project.contractSend') }}
            </Button>
          </template>
        </template>

        <p v-if="signed && contract.state === 'draft'" class="text-small leading-[1.45] text-faint">
          {{ $t('project.contractHoldsUntilSent', { version: signed.version }) }}
        </p>

        <div class="mt-4 grid min-h-0 grid-cols-[minmax(0,1fr)_7px_minmax(0,1fr)] gap-0 max-[1100px]:grid-cols-[minmax(0,1fr)]">
          <section class="flex min-w-0 flex-col">
            <div class="flex items-center gap-2.5 border-b border-border pb-2.5">
              <span class="mb-[5px] text-small leading-none font-semibold text-faint">{{ $t('documentTemplates.body') }}</span>
              <span class="ml-auto text-small leading-[1.45] text-faint">{{ $t('documentTemplates.bodyHint') }}</span>
            </div>
            <div class="mx-auto mt-6 w-full max-w-[520px] rounded-[2px] border border-border bg-white px-10 pb-10">
              <div class="-mx-10 mb-6 border-b border-dashed border-line-hover bg-[#FCFBFA] px-3 py-[9px] text-small leading-[1.4] font-medium text-faint">{{ $t('documentTemplates.letterhead') }}</div>
              <DocumentEditor :key="contract.id" v-model="doc" :palette="palette" :merge-fields="mergeFields" />
              <div class="-mx-10 -mb-10 mt-6 border-t border-dashed border-line-hover bg-[#FCFBFA] px-3 py-[9px] text-small leading-[1.4] font-medium text-faint">{{ $t('documentTemplates.footer') }}</div>
            </div>
          </section>

          <div class="flex items-center justify-center before:block before:h-full before:w-px before:bg-border before:content-[''] max-[1100px]:hidden" />

          <PreviewOverlay :min="1101" :label="$t('common.preview')" wrapper-class="contents">
            <DocumentPreviewPane type="contract" :doc="doc" :samples="samples" :project="previewProject">
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
          </PreviewOverlay>
        </div>
      </Card>

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
          <Button type="submit" :disabled="generate.processing">
            {{ $t('project.contractGenerate') }}
          </Button>
        </form>
        <Button v-else-if="header.canManageContracts" :as="Link" href="/dokumentvorlagen/neu">
          {{ $t('documentTemplates.create') }}
        </Button>
      </Section>
    </ProjectShell>
  </AdminLayout>
</template>
