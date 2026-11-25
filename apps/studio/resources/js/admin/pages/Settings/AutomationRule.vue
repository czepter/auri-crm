<script setup lang="ts">
// Vorschau und Testmail kommen vom Server, damit die gezeigte Mail die versendete ist.
import { computed, nextTick, ref, watch } from 'vue'
import { useDebounceFn, useElementSize } from '@vueuse/core'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { Plus, X } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'
import { Combobox, ComboboxAnchor, ComboboxContent, ComboboxInput, ComboboxItem } from '@/ui/combobox'
import { ChoiceGroup } from '@/ui/choice-group'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/ui/popover'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Switch } from '@/ui/switch'
import MessageField from '@/admin/components/automation/MessageField.vue'
import Chip, { type ChipTone } from '@/ui/page/Chip.vue'

type Option = { value: string; label: string }
type Condition = Option & { clause: string }
type Anchor = Option & { noun: string; at: string; canBefore: boolean }

const props = defineProps<{
  rule: App.Automation.Data.AutomationRuleData
  eventClause: string | null
  anchors: Anchor[]
  recipients: Option[]
  conditions: Condition[]
  projectTypes: Option[]
  actions: Option[]
  mergeFields: { path: string; sample: string; group: string; label: string; needsPayment: boolean }[]
  previewProjects: Option[]
  testRecipient: string
  runs: App.Automation.Data.AutomationRunData[]
}>()

const { t } = useI18n()
const EXAMPLE = 'example'

const dated = computed(() => props.rule.triggerType === 'date_offset')
const brand = computed(() => String(usePage().props.brand ?? ''))

const form = useForm({
  name: props.rule.name,
  anchor: props.rule.anchor,
  offset_days: props.rule.offsetDays ?? 0,
  direction: props.rule.direction ?? 'before',
  recipient_type: props.rule.recipient,
  action: props.rule.action,
  condition: props.rule.condition,
  project_type: props.rule.projectType,
  notification_subject: props.rule.subject,
  notification_body: props.rule.body,
  is_active: props.rule.isActive,
})

const anchor = computed(() => (dated.value ? form.anchor : props.rule.anchor))
const fields = computed(() => props.mergeFields.filter((f) => !f.needsPayment || anchor.value === 'installment_due'))

function fieldProblem(text: string): string | undefined {
  const usable = new Set(fields.value.map((f) => f.path))
  const all = new Set(props.mergeFields.map((f) => f.path))
  const used = [...new Set([...text.matchAll(/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g)].map((m) => m[1]))]
  const label = (path: string) => props.mergeFields.find((f) => f.path === path)?.label ?? path
  const unknown = used.filter((path) => !all.has(path)).map(label).join(', ')
  const unavailable = used.filter((path) => all.has(path) && !usable.has(path)).map(label).join(', ')

  if (unknown) return t('automations.unknownField', { field: unknown })
  if (unavailable) return t('automations.unavailableField', { field: unavailable })
}

const subjectError = computed(() => fieldProblem(form.notification_subject) || form.errors.notification_subject || testErrors.value.subject || previewErrors.value.subject)
const bodyError = computed(() => fieldProblem(form.notification_body) || form.errors.notification_body || testErrors.value.body || previewErrors.value.body)

const days = computed(() => Number(form.offset_days) || 0)
const mode = computed(() => (days.value ? form.direction : 'on'))
const canBefore = computed(() => props.anchors.find((a) => a.value === form.anchor)?.canBefore ?? true)

function setMode(value: string) {
  if (value === 'on') form.offset_days = 0
  else {
    form.direction = value
    if (!days.value) form.offset_days = 1
  }
}

watch(canBefore, (ok) => {
  if (!ok && form.direction === 'before') form.direction = 'after'
}, { immediate: true })

const restrictions = computed(() => [
  { key: 'project_type', label: t('automations.projectType'), set: form.project_type !== null, first: props.projectTypes[0]?.value },
  { key: 'condition', label: t('automations.condition'), set: form.condition !== null, first: props.conditions[0]?.value },
].filter((r) => !r.set && r.first))

function setRecipient(value: string) {
  form.recipient_type = value
  if (value !== 'client' && form.action === 'mail_draft') form.action = 'notice'
}

function addRestriction(key: string, value: string) {
  if (key === 'project_type') form.project_type = value
  else form.condition = value
}

function save() {
  form.patch(`/einstellungen/automationen/${props.rule.id}`, { preserveScroll: true })
}

function remove() {
  router.delete(`/einstellungen/automationen/${props.rule.id}`)
}

const fieldOpen = ref(false)
const fieldSearch = ref('')
const subject = ref<InstanceType<typeof MessageField> | null>(null)
const body = ref<InstanceType<typeof MessageField> | null>(null)
const last = ref<'subject' | 'body'>('body')

const sampleOf = (field: { path: string; sample: string }) => samples.value[field.path] ?? field.sample

const fieldGroups = computed(() => {
  const needle = fieldSearch.value.trim().toLowerCase()
  const groups = new Map<string, typeof props.mergeFields>()

  for (const field of fields.value) {
    if (needle && !`${field.label} ${sampleOf(field)}`.toLowerCase().includes(needle)) continue
    groups.set(field.group, [...(groups.get(field.group) ?? []), field])
  }

  return [...groups]
})

function insert(path: string) {
  fieldOpen.value = false
  fieldSearch.value = ''
  const target = last.value === 'subject' ? subject : body
  target.value?.insert(path)
}

const PREVIEW_WIDTH = { desktop: 600, mobile: 375 }

const device = ref<'desktop' | 'mobile'>('desktop')
const project = ref('')
const search = ref('')
const chosen = ref(props.previewProjects[0]?.label ?? '')
const samples = ref<Record<string, string>>({})
const projects = ref(props.previewProjects)
const previewError = ref('')
const previewErrors = ref<{ subject?: string; body?: string }>({})
const stale = computed(() => Boolean(previewError.value || previewErrors.value.subject || previewErrors.value.body))
const previewHtml = ref('')
const previewSubject = ref('')
const previewTo = ref('')
const previewDraft = ref(false)
const contentHeight = ref(0)
const stage = ref<HTMLElement | null>(null)
const frame = ref<HTMLIFrameElement | null>(null)
const { width: stageWidth } = useElementSize(stage)

const scale = computed(() => Math.min(1, stageWidth.value / PREVIEW_WIDTH[device.value]))

function pick(value: unknown) {
  const id = String(value)
  project.value = id === EXAMPLE ? '' : id
  chosen.value = projects.value.find((p) => (p.value || EXAMPLE) === id)?.label ?? ''
  search.value = ''
}

function fit() {
  contentHeight.value = frame.value?.contentDocument?.body?.scrollHeight ?? 0
}

watch([device, stageWidth], () => requestAnimationFrame(fit))

let pending: AbortController | null = null

function sampleParams(): Record<string, string> {
  return {
    ...(anchor.value ? { anchor: anchor.value } : {}),
    ...(project.value ? { project: project.value } : {}),
    project_type: form.project_type ?? '',
    recipient: form.recipient_type,
    ...(search.value ? { q: search.value } : {}),
  }
}

const refreshPreview = useDebounceFn(async () => {
  pending?.abort()
  pending = new AbortController()
  const query = new URLSearchParams({ subject: form.notification_subject, body: form.notification_body, ...sampleParams() })
  const response = await fetch(`/einstellungen/automationen/${props.rule.id}/vorschau?${query}`, {
    headers: { Accept: 'application/json' },
    signal: pending.signal,
  }).catch((e) => {
    if (e.name === 'AbortError') return null
    previewError.value = t('automations.previewFailed')
    return null
  })
  if (!response) return
  const data = await response.json().catch(() => null)
  if (!response.ok) {
    const errors: Record<string, string[]> = response.status === 422 ? (data?.errors ?? {}) : {}
    previewErrors.value = { subject: errors.subject?.[0], body: errors.body?.[0] }
    const unowned = Object.entries(errors).filter(([key]) => key !== 'subject' && key !== 'body').flatMap(([, messages]) => messages)
    previewError.value = unowned.join(' ') || (Object.keys(errors).length ? '' : t('automations.previewFailed'))
    return
  }
  previewError.value = ''
  previewErrors.value = {}
  testErrors.value = { ...testErrors.value, project: undefined, anchor: undefined, project_type: undefined }
  previewSubject.value = data.subject
  previewTo.value = data.to ?? ''
  previewDraft.value = Boolean(data.draft)
  previewHtml.value = data.html
  samples.value = data.samples ?? {}
  if (data.projects) {
    projects.value = data.projects
    if (project.value && !data.projects.some((p: Option) => p.value === project.value)) {
      project.value = ''
      chosen.value = data.projects[0]?.label ?? ''
    }
  }
}, 300)

watch(() => [form.notification_subject, form.notification_body, project.value, anchor.value, form.project_type, form.recipient_type, search.value], refreshPreview, { immediate: true })

const sending = ref(false)
const testErrors = ref<Record<string, string | undefined>>({})
const testSampleError = computed(() => testErrors.value.project || testErrors.value.anchor || testErrors.value.project_type)
watch(() => form.notification_subject, () => {
  testErrors.value = { ...testErrors.value, subject: undefined }
  form.clearErrors('notification_subject')
})
watch(() => form.notification_body, () => {
  testErrors.value = { ...testErrors.value, body: undefined }
  form.clearErrors('notification_body')
})

const mark = (html: string) => html.replace(/\{\{[^{}]*\}\}/g, (m) => `<mark style="background:#fde68a;color:inherit">${m}</mark>`)
const markedHtml = computed(() => mark(previewHtml.value))
const markedSubject = computed(() => mark(previewSubject.value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')))

function focusError() {
  if (!subjectError.value && !bodyError.value) return
  if (subjectError.value) subject.value?.focus()
  else body.value?.focus()
}

function sendTest() {
  if (subjectError.value || bodyError.value) return focusError()

  router.post(
    `/einstellungen/automationen/${props.rule.id}/testmail`,
    { subject: form.notification_subject, body: form.notification_body, ...sampleParams() },
    {
      preserveScroll: true,
      onStart: () => {
        sending.value = true
        testErrors.value = {}
      },
      onError: (errors) => {
        testErrors.value = errors
        nextTick(focusError)
      },
      onFinish: () => (sending.value = false),
    },
  )
}

function chipTone(status: string): ChipTone {
  return ({ sent: 'success', failed: 'danger', skipped: 'neutral' } as Record<string, ChipTone>)[status] ?? 'warn'
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('automations.title')" kicker-href="/einstellungen/automationen" :title="form.name">
          <template #title>
            <input
              v-model="form.name"
              class="field-sizing-content min-w-40 rounded-[var(--r-sm)] border border-transparent bg-transparent px-1.5 py-0 -mx-1.5 text-inherit [font:inherit] tracking-[inherit] outline-none hover:border-line-hover focus:border-ring focus:bg-background"
              type="text"
              :size="Math.max(form.name.length, 12)"
              :aria-label="$t('automations.ruleName')"
              :aria-invalid="Boolean(form.errors.name)"
            >
          </template>
          <span v-if="form.errors.name" class="text-small text-destructive" role="alert">{{ form.errors.name }}</span>
          <div class="ml-auto flex flex-wrap items-center gap-3 max-[640px]:ml-0">
            <span
              v-if="form.isDirty"
              class="flex-none rounded-[var(--r-sm)] bg-due-bg px-2 py-[3px] text-small leading-[1.5] font-semibold text-due"
            >{{ $t('settings.unsaved') }}</span>
            <Link v-if="rule.drafts > 0" class="text-small text-foreground underline underline-offset-2" href="/posteingang">{{ $t('automations.draftsOpen', rule.drafts) }}</Link>
            <Switch id="sw-rule-active" v-model="form.is_active" />
            <label class="text-small" for="sw-rule-active">{{ $t('automations.active') }}</label>
            <Button class="max-w-full min-w-0" :class="{ 'animate-pulse': sending }" variant="outline" type="button" :disabled="sending" :aria-busy="sending" @click="sendTest">
              <span class="truncate">{{ $t('automations.testMailTo', { email: testRecipient }) }}</span>
            </Button>
            <Button class="ml-auto" type="button" :disabled="form.processing" @click="save">
              {{ $t('common.save') }}
            </Button>
          </div>
        </PageHead>

        <div class="flex items-start gap-10 px-6 pt-7 pb-8 max-[900px]:flex-col">
          <form class="max-w-[900px] min-w-0 flex-1" @submit.prevent="save">
            <div class="flex flex-wrap items-center gap-x-1.5 gap-y-2.5 text-ui">
              <template v-if="dated">
                <template v-if="mode !== 'on'">
                  <Input v-model="form.offset_days" class="w-16 flex-none" type="number" min="1" :aria-label="$t('automations.days', 2)" :aria-invalid="Boolean(form.errors.offset_days)" />
                  <span>{{ $t('automations.days', days) }}</span>
                </template>
                <Select :model-value="mode" @update:model-value="(v) => setMode(String(v))">
                  <SelectTrigger class="w-auto" :aria-label="$t('automations.direction')"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-if="canBefore" value="before">{{ $t('automations.before') }}</SelectItem>
                    <SelectItem value="after">{{ $t('automations.after') }}</SelectItem>
                    <SelectItem value="on">{{ $t('automations.on') }}</SelectItem>
                  </SelectContent>
                </Select>
                <Select :key="mode === 'on' ? 'at' : 'noun'" :model-value="form.anchor ?? undefined" @update:model-value="(v) => (form.anchor = String(v))">
                  <SelectTrigger class="w-auto" :aria-label="$t('automations.anchor')"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in anchors" :key="option.value" :value="option.value">
                      {{ mode === 'on' ? option.at : option.noun }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </template>
              <span v-else>{{ eventClause }}</span>

              <span>{{ $t('automations.to') }}</span>
              <Select :model-value="form.recipient_type" @update:model-value="(v) => setRecipient(String(v))">
                <SelectTrigger class="w-auto" :aria-label="$t('automations.recipient')"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in recipients" :key="option.value" :value="option.value">
                    {{ $t(`automations.recipientTo.${option.value}`) }}
                  </SelectItem>
                </SelectContent>
              </Select>

              <span>{{ $t('automations.as') }}</span>
              <span v-if="form.recipient_type === 'client'">{{ $t('automations.draft') }}{{ form.project_type !== null || form.condition !== null ? ',' : '' }}</span>
              <span v-else class="inline-flex items-center">
                <Select :model-value="form.action" @update:model-value="(v) => (form.action = String(v))">
                  <SelectTrigger class="w-auto" :aria-label="$t('automations.action')"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in actions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <span v-if="form.project_type !== null || form.condition !== null">,</span>
              </span>

              <span v-if="form.project_type !== null" class="inline-flex items-center gap-2">
                <span>{{ $t('automations.onlyFor') }}</span>
                <Select :model-value="form.project_type" @update:model-value="(v) => (form.project_type = String(v))">
                  <SelectTrigger class="w-auto" :aria-label="$t('automations.projectType')"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in projectTypes" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <span class="inline-flex items-center">
                  <button class="cursor-pointer text-faint hover:text-foreground" type="button" :aria-label="$t('common.remove')" @click="form.project_type = null"><X class="size-3.5" /></button>
                  <span v-if="form.condition !== null">,</span>
                </span>
              </span>

              <span v-if="form.condition !== null" class="inline-flex items-center gap-2">
                <span>{{ $t('automations.whenCondition') }}</span>
                <Select :model-value="form.condition" @update:model-value="(v) => (form.condition = String(v))">
                  <SelectTrigger class="w-auto" :aria-label="$t('automations.condition')"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in conditions" :key="option.value" :value="option.value">
                      {{ option.clause }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <button class="cursor-pointer text-faint hover:text-foreground" type="button" :aria-label="$t('common.remove')" @click="form.condition = null"><X class="size-3.5" /></button>
              </span>

              <Button v-if="restrictions.length === 1" variant="ghost" type="button" @click="addRestriction(restrictions[0].key, restrictions[0].first!)">
                <Plus />{{ $t('automations.restrict') }}
              </Button>
              <DropdownMenu v-else-if="restrictions.length">
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" type="button"><Plus />{{ $t('automations.restrict') }}</Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                  <DropdownMenuItem v-for="r in restrictions" :key="r.key" @select="addRestriction(r.key, r.first!)">{{ r.label }}</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            <p v-if="form.errors.offset_days" class="mt-1.5 text-small text-destructive" role="alert">{{ form.errors.offset_days }}</p>

            <div class="mt-8 mb-2.5 flex max-w-[700px] items-center justify-between">
              <span class="text-small leading-none font-semibold text-foreground">{{ $t('automations.messageSection') }}</span>
              <Popover v-model:open="fieldOpen">
                <PopoverTrigger as-child>
                  <Button variant="outline" type="button">{{ $t('automations.field') }}</Button>
                </PopoverTrigger>
                <PopoverContent class="w-[360px]" align="end">
                  <Input v-model="fieldSearch" type="search" :aria-label="$t('common.search')" :placeholder="$t('common.search')" />
                  <div class="mt-2 max-h-[340px] overflow-y-auto">
                    <div v-for="[group, fields] in fieldGroups" :key="group" class="mb-2 last:mb-0">
                      <div class="px-2 py-1 text-small font-semibold text-faint">{{ group }}</div>
                      <button
                        v-for="field in fields"
                        :key="field.path"
                        class="block w-full cursor-pointer rounded-[var(--r-sm)] px-2 py-1.5 text-left hover:bg-sunk"
                        type="button"
                        @click="insert(field.path)"
                      >
                        <span class="flex items-baseline justify-between gap-3 text-small">
                          <span class="font-medium">{{ field.label }}</span>
                          <span class="min-w-0 truncate text-ink-2">{{ sampleOf(field) }}</span>
                        </span>
                      </button>
                    </div>
                  </div>
                </PopoverContent>
              </Popover>
            </div>

            <div class="grid max-w-[700px] grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4">
              <Field v-slot="{ id }" wide :label="$t('automations.subject')" :error="subjectError">
                <MessageField :id="id" ref="subject" v-model="form.notification_subject" single-line :fields="mergeFields" :samples="samples" :invalid="Boolean(subjectError)" @focus="last = 'subject'" />
              </Field>

              <Field v-slot="{ id }" wide :label="$t('automations.body')" :error="bodyError">
                <MessageField :id="id" ref="body" v-model="form.notification_body" :fields="mergeFields" :samples="samples" :invalid="Boolean(bodyError)" @focus="last = 'body'" />
              </Field>
            </div>

            <div class="mt-10">
              <Button class="border-destructive text-destructive" variant="outline" type="button" @click="remove">
                {{ $t('common.remove') }}
              </Button>
            </div>
          </form>

          <aside class="w-[664px] min-w-0 shrink self-start min-[900px]:sticky min-[900px]:top-6 max-[900px]:w-full">
            <div class="flex items-center justify-between">
              <span class="text-small leading-none font-semibold text-foreground">{{ $t('automations.preview') }}</span>
              <ChoiceGroup
                v-model="device"
                :options="[
                  { value: 'desktop', label: $t('portalSettings.device.desktop') },
                  { value: 'mobile', label: $t('portalSettings.device.mobile') },
                ]"
              />
            </div>

            <div class="mt-2 rounded-[10px] bg-sunk-2 p-3">
              <Field v-if="projects.length > 1 || search" v-slot="{ id }" class="mb-3" :label="$t('automations.previewWith')">
                <Combobox ignore-filter open-on-focus :model-value="project || EXAMPLE" @update:model-value="pick">
                  <ComboboxAnchor>
                    <ComboboxInput
                      :id="id"
                      :display-value="() => chosen"
                      @focus="($event.target as HTMLInputElement).select()"
                      @input="search = ($event.target as HTMLInputElement).value"
                    />
                  </ComboboxAnchor>
                  <ComboboxContent>
                    <ComboboxItem v-for="option in projects" :key="option.value" :value="option.value || EXAMPLE">
                      {{ option.label }}
                    </ComboboxItem>
                  </ComboboxContent>
                </Combobox>
              </Field>
              <div v-if="previewError || testSampleError" class="mb-3 flex items-center justify-between gap-3">
                <p class="text-small text-destructive" role="alert">{{ previewError || testSampleError }}</p>
                <Button v-if="!previewHtml" variant="outline" type="button" @click="refreshPreview">{{ $t('automations.reload') }}</Button>
              </div>
              <dl v-if="previewHtml" class="mb-3 grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3 gap-y-1 text-small" :class="{ 'opacity-40': stale }">
                <dt class="text-faint">{{ $t('automations.from') }}</dt>
                <dd class="m-0 truncate">{{ brand }}</dd>
                <dt class="text-faint">{{ $t('automations.toLabel') }}</dt>
                <dd class="m-0 flex min-w-0 items-center gap-2"><span v-if="previewTo" class="truncate">{{ previewTo }}</span><span v-else class="text-destructive">{{ $t('automations.noRecipient') }}</span><span v-if="previewDraft && previewTo" class="chip now flex-none">{{ $t('automations.draft') }}</span></dd>
                <dt class="text-faint">{{ $t('automations.subject') }}</dt>
                <dd class="m-0 truncate font-medium" v-html="markedSubject" />
              </dl>
              <div v-if="previewHtml" ref="stage" class="overflow-hidden" :class="{ 'opacity-40': stale }" :style="{ height: `${contentHeight * scale}px` }">
                <iframe
                  ref="frame"
                  :srcdoc="markedHtml"
                  sandbox="allow-same-origin"
                  scrolling="no"
                  @load="fit"
                  :title="$t('automations.preview')"
                  class="block origin-top-left border-0 bg-paper"
                  :style="{ width: `${PREVIEW_WIDTH[device]}px`, height: `${contentHeight}px`, transform: `scale(${scale})`, marginLeft: `${Math.max(0, (stageWidth - PREVIEW_WIDTH[device] * scale) / 2)}px` }"
                />
              </div>
            </div>

            <template v-if="runs.length">
            <div class="mt-7 text-small leading-none font-semibold text-foreground">
              {{ $t('automations.runs') }}
            </div>

            <div class="mt-2 overflow-hidden surface-card">
              <div
                v-for="run in runs"
                :key="run.id"
                class="flex items-center gap-3 border-b border-rule px-3 py-2.5 text-small last:border-b-0"
              >
                <span class="w-24 flex-none text-ink-2 tabular-nums">{{ run.firedAt }}</span>
                <span class="min-w-0 flex-1 truncate">{{ run.anchorLabel }}</span>
                <Link v-if="run.draftUrl" class="no-underline hover:underline" :href="run.draftUrl"><Chip :tone="chipTone(run.status)">{{ run.statusLabel }}</Chip></Link>
                <Chip v-else :tone="chipTone(run.status)">{{ run.statusLabel }}</Chip>
              </div>
            </div>
            </template>
          </aside>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
