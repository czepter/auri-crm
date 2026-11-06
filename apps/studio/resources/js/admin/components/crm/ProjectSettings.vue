<script setup lang="ts">
import FormShell from '@/ui/page/FormShell.vue'
import { computed, ref, watchEffect } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useEventListener } from '@vueuse/core'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Switch } from '@/ui/switch'
import { Textarea } from '@/ui/textarea'
import WorkflowPanel from '@/admin/components/crm/WorkflowPanel.vue'

type Stage = {
  id: string
  key: string
  name: string
  color: string
  css: string
  anchor: string | null
  anchorLabel: string | null
  isActive: boolean
  count?: number
}

type Pipeline = {
  value: string
  label: string
  stages: Stage[]
}

type Field = {
  id: string
  key: string
  label: string
  type: string
  typeLabel: string
  options: string[]
  unit: string | null
  required: boolean
  allowNewOptions: boolean
  isActive: boolean
}

type Type = { id: string; key: string; name: string; isActive: boolean; isDefault: boolean; durationMinutes: number; fields: Field[] }

const props = defineProps<{
  pipelines: Pipeline[]
  types: Type[]
  colors: { value: string; label: string; css: string }[]
  fieldTypes: { value: string; label: string; hasOptions: boolean }[]
  workflow?: Record<string, unknown> | null
  canManageTasks?: boolean
  selection: { kind: 'pipeline' | 'type'; key: string }
  returnTo?: string | null
}>()

const emit = defineEmits<{ close: [] }>()

// C-25: one settings room instead of three buttons. What the left rail
// selects is what the right pane edits — a pipeline's stages, or one type
// with its own two tabs.
const selected = ref({ ...props.selection })
const tab = ref<'general' | 'workflow'>('general')

const pipeline = computed(() => props.pipelines.find((one) => one.value === selected.value.key) ?? null)
const type = computed(() => props.types.find((one) => one.key === selected.value.key) ?? null)

// Der Bildschirm unter dem Overlay folgt der Auswahl: Anfrage-Stufen liegen
// über /anfragen, Projekt-Stufen und Typen über der Projektliste.
function choose(next: { kind: 'pipeline' | 'type'; key: string }) {
  selected.value = next

  const path = next.kind === 'type'
    ? `/auftraege/einstellungen/typen/${next.key}`
    : `/${next.key === 'lead' ? 'anfragen' : 'auftraege'}/einstellungen/stufen`

  router.visit(path, {
    data: props.returnTo ? { return: props.returnTo } : {},
    replace: true,
    preserveState: true,
    preserveScroll: true,
    only: ['settingsSelection', 'returnTo'],
    onFinish: () => next.kind === 'type' && tab.value === 'workflow' && loadWorkflow(),
  })
}

function fillTypeForm(next: Type) {
  typeForm.defaults({ name: next.name, is_active: next.isActive, is_default: next.isDefault, duration_minutes: next.durationMinutes })
  typeForm.reset()
}

function chooseType(next: Type) {
  choose({ kind: 'type', key: next.key })
  fillTypeForm(next)
}

// Die Spalten kommen erst beim Öffnen des Tabs mit — der Panel-Prop ist
// optional, und ein Tab, den niemand aufschlägt, lädt nichts.
function openWorkflow() {
  tab.value = 'workflow'
  loadWorkflow()
}

function loadWorkflow() {
  router.reload({ only: ['workflow'], data: { workflow_typ: selected.value.key } })
}

const open = ref<'stage' | 'newStage' | 'deleteStage' | 'newType' | 'field' | null>(null)
const editing = ref<Stage | null>(null)

const stageForm = useForm({ name: '', color: 'grey', is_active: true })
const newStageForm = useForm({ pipeline: 'lead', name: '', color: 'blue', after: null as string | null })
const deleteForm = useForm({ move_to: null as string | null })
const typeForm = useForm({ name: '', is_active: true, is_default: false, duration_minutes: 120 })
const newTypeForm = useForm({ name: '' })

if (type.value) fillTypeForm(type.value)

// Capture: runs before a dialog's own Escape handler has closed it, so an
// open dialog still swallows the key. A key pressed in a field is the field's.
useEventListener(window, 'keydown', (event: KeyboardEvent) => {
  if (event.key !== 'Escape' || open.value !== null) return
  if ((event.target as HTMLElement).closest?.('input, textarea, select, [role=combobox], [role=listbox], [role=dialog]')) return

  emit('close')
}, { capture: true })

function editStage(stage: Stage) {
  editing.value = stage
  stageForm.name = stage.name
  stageForm.color = stage.color
  stageForm.is_active = stage.isActive
  open.value = 'stage'
}

function addStage() {
  newStageForm.pipeline = pipeline.value!.value
  newStageForm.name = ''
  newStageForm.after = pipeline.value!.stages.at(-1)?.id ?? null
  open.value = 'newStage'
}

const done = { preserveScroll: true, onSuccess: () => (open.value = null) }

function saveStage() {
  stageForm.patch(`/stufen/${editing.value!.id}`, done)
}

function createStage() {
  newStageForm.post('/stufen', done)
}

function confirmDelete() {
  deleteForm.move_to = pipeline.value?.stages.find((one) => one.id !== editing.value?.id)?.id ?? null
  open.value = 'deleteStage'
}

function destroyStage() {
  deleteForm.delete(`/stufen/${editing.value!.id}`, done)
}

function saveType() {
  typeForm.patch(`/projekttypen/${type.value!.id}`, { preserveScroll: true })
}

function createType() {
  newTypeForm.post('/projekttypen', {
    preserveScroll: true,
    onSuccess: () => {
      open.value = null
      newTypeForm.reset()
    },
  })
}

// C-25's field dialog. `key` never travels: it is derived from the label once
// and the stored values hang on it.
const editingField = ref<Field | null>(null)
const fieldForm = useForm({
  label: '',
  type: 'text',
  options: '',
  unit: '',
  required: false,
  allow_new_options: false,
  is_active: true,
})

const fieldHasOptions = computed(
  () => props.fieldTypes.find((one) => one.value === fieldForm.type)?.hasOptions ?? false,
)

function editField(field: Field | null) {
  editingField.value = field
  fieldForm.defaults({
    label: field?.label ?? '',
    type: field?.type ?? 'text',
    options: (field?.options ?? []).join('\n'),
    unit: field?.unit ?? '',
    required: field?.required ?? false,
    allow_new_options: field?.allowNewOptions ?? false,
    is_active: field?.isActive ?? true,
  })
  fieldForm.reset()
  open.value = 'field'
}

function saveField() {
  const payload = fieldForm.transform((data) => ({
    ...data,
    options: data.options.split('\n').map((line) => line.trim()).filter(Boolean),
    unit: data.unit || null,
  }))

  const base = `/projekttypen/${type.value!.id}/felder`

  editingField.value ? payload.patch(`${base}/${editingField.value.id}`, done) : payload.post(base, done)
}

function destroyField() {
  router.delete(`/projekttypen/${type.value!.id}/felder/${editingField.value!.id}`, done)
}

/** Was rechts auf der Karte steht: die Art des Feldes und wovon es zehrt. */
function fieldMeta(field: Field) {
  if (field.type === 'number' && field.unit) return `${field.typeLabel} · ${field.unit}`
  if (field.type === 'select') return `${field.typeLabel} · ${field.options.length} Optionen`
  if (field.type === 'tags') return `${field.typeLabel} · ${field.options.length}`

  return field.typeLabel
}

// Drag to reorder, on the rows themselves — the order IS the meaning, so it
// is edited where it is read rather than in a number field.
//
// Pointer events rather than HTML5 drag-and-drop: that API has no touch at
// all, needs a dataTransfer payload to start a drag in some browsers, and
// fires a click on the row afterwards — which here would open the edit
// dialog on every drop. One pointer stream answers all three.
//
// One implementation for both lists: only one of them is on screen at a
// time, and both are a list of ids that ends in a PATCH.
const rows = ref<{ id: string }[]>([])
const held = ref<{ id: string; x: number; y: number; moved: boolean } | null>(null)

watchEffect(() => {
  if (held.value !== null) return

  rows.value = selected.value.kind === 'pipeline'
    ? [...(pipeline.value?.stages ?? [])]
    : [...(type.value?.fields ?? [])]
})

const stageRows = computed(() => rows.value as Stage[])
const fieldRows = computed(() => rows.value as Field[])

function grab(event: PointerEvent, id: string) {
  if (event.button !== 0) return

  held.value = { id, x: event.clientX, y: event.clientY, moved: false }
  ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
}

function move(event: PointerEvent) {
  const drag = held.value
  if (drag === null) return

  // Four pixels of slack: a press that wanders by one is a click on the way
  // to the edit dialog, not a drag.
  if (!drag.moved && Math.hypot(event.clientX - drag.x, event.clientY - drag.y) < 4) return
  drag.moved = true

  const over = document.elementFromPoint(event.clientX, event.clientY)?.closest<HTMLElement>('[data-drag]')
  const from = rows.value.findIndex((row) => row.id === drag.id)
  const to = rows.value.findIndex((row) => row.id === over?.dataset.drag)

  if (from < 0 || to < 0 || from === to) return

  rows.value.splice(to, 0, ...rows.value.splice(from, 1))
}

function release(row: Stage | Field) {
  const drag = held.value
  held.value = null

  if (drag === null) return

  // A press that never moved is the way into the dialog. The row carries both
  // gestures because it is the only thing on the line worth touching.
  if (!drag.moved) {
    selected.value.kind === 'pipeline' ? editStage(row as Stage) : editField(row as Field)

    return
  }

  const order = rows.value.map((one) => one.id)

  selected.value.kind === 'pipeline'
    ? router.patch('/stufen/reihenfolge', { pipeline: pipeline.value!.value, order }, { preserveScroll: true })
    : router.patch(`/projekttypen/${type.value!.id}/felder/reihenfolge`, { order }, { preserveScroll: true })
}
</script>

<template>
  <FormShell wide :title="$t('stages.title')">
    <template #head>
      <Button class="ml-auto" type="button" @click="emit('close')">{{ $t('stages.done') }}</Button>
    </template>

    <template #nav>
        <nav class="w-[168px] flex-none">
          <h3 class="m-0 mb-[7px] text-small leading-none font-semibold text-ink-2">
            {{ $t('stages.stagesNav') }}
          </h3>
          <button
            v-for="one in pipelines"
            :key="one.value"
            class="block w-full cursor-pointer rounded-lg border-0 bg-transparent px-2.5 py-[7px] text-left text-small leading-[1.3]"
            :class="selected.kind === 'pipeline' && selected.key === one.value ? 'bg-sunk-2 font-semibold' : 'text-ink-2'"
            type="button"
            @click="choose({ kind: 'pipeline', key: one.value })"
          >
            {{ one.label }}
          </button>

          <h3 class="m-0 mt-6 mb-[7px] text-small leading-none font-semibold text-ink-2">
            {{ $t('stages.typesSection') }}
          </h3>
          <button
            v-for="one in types"
            :key="one.id"
            class="block w-full cursor-pointer rounded-lg border-0 bg-transparent px-2.5 py-[7px] text-left text-small leading-[1.3]"
            :class="[
              selected.kind === 'type' && selected.key === one.key ? 'bg-sunk-2 font-semibold' : 'text-ink-2',
              one.isActive ? '' : 'text-ink-2',
            ]"
            type="button"
            @click="chooseType(one)"
          >
            {{ one.name }}
          </button>

          <Button class="mt-1.5 ml-0" type="button" variant="ghost" @click="((newTypeForm.name = ''), (open = 'newType'))">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
            {{ $t('stages.typeShort') }}
          </Button>
        </nav>
    </template>

        <section v-if="pipeline" class="min-w-0 flex-1">
          <h2 class="m-0 text-title leading-[1.2] font-semibold tracking-[-0.02em]">
            {{ $t('stages.stagesSection', { pipeline: pipeline.label }) }}
          </h2>

          <div class="mt-4 grid grid-cols-[minmax(0,420px)_minmax(0,1fr)] items-start gap-8 max-[1100px]:grid-cols-1">
          <div class="flex flex-col gap-2 rounded-xl bg-sunk p-3">
            <button
              v-for="stage in stageRows"
              :key="stage.id"
              class="flex touch-none cursor-grab items-center gap-3 rounded-[var(--r-sm)] border border-border bg-background px-3 py-2 text-left text-small leading-[1.4] font-semibold transition-[border-color,opacity] select-none hover:border-line-hover active:cursor-grabbing"
              :class="[
                stage.isActive ? 'text-foreground' : 'text-ink-2',
                held?.id === stage.id && held?.moved ? 'opacity-40' : '',
              ]"
              :data-drag="stage.id"
              type="button"
              @pointercancel="held = null"
              @pointerdown="grab($event, stage.id)"
              @pointermove="move"
              @pointerup="release(stage)"
            >
              <svg class="size-3.5 flex-none fill-ink-2" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="9" cy="6" r="1.6" /><circle cx="15" cy="6" r="1.6" /><circle cx="9" cy="12" r="1.6" /><circle cx="15" cy="12" r="1.6" /><circle cx="9" cy="18" r="1.6" /><circle cx="15" cy="18" r="1.6" />
              </svg>
              <span class="size-2 flex-none rounded-full" :style="{ background: stage.css }"></span>
              <span class="flex-1">{{ stage.name }}</span>
              <span class="flex-none font-normal tabular-nums text-ink-2">{{ stage.count ?? 0 }}</span>
            </button>
          </div>

          <div class="flex flex-wrap gap-2 rounded-xl bg-sunk p-3">
            <div
              v-for="stage in stageRows.filter((one) => one.isActive)"
              :key="stage.id"
              class="flex min-w-[140px] flex-1 items-center gap-2 border-b-2 bg-background px-3 py-2 text-small font-semibold"
              :style="{ borderBottomColor: stage.css }"
            >
              <span class="flex-1">{{ stage.name }}</span>
              <span class="flex-none font-normal tabular-nums text-ink-2">{{ stage.count ?? 0 }}</span>
            </div>
          </div>
          </div>

          <Button class="mt-3.5 ml-0" type="button" variant="ghost" @click="addStage">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
            {{ $t('stages.addStage') }}
          </Button>
        </section>

        <section v-else-if="type" class="min-w-0 flex-1">
          <div class="flex items-baseline gap-5">
            <h2 class="m-0 text-title leading-[1.2] font-semibold tracking-[-0.02em]">{{ type.name }}</h2>
            <button
              class="cursor-pointer border-0 bg-transparent p-0 text-small leading-[1.3]"
              :class="tab === 'general' ? 'font-semibold text-foreground' : 'text-ink-2'"
              type="button"
              @click="tab = 'general'"
            >
              {{ $t('stages.tabGeneral') }}
            </button>
            <button
              v-if="canManageTasks"
              class="cursor-pointer border-0 bg-transparent p-0 text-small leading-[1.3]"
              :class="tab === 'workflow' ? 'font-semibold text-foreground' : 'text-ink-2'"
              type="button"
              @click="openWorkflow"
            >
              {{ $t('stages.tabWorkflow') }}
            </button>
          </div>

          <template v-if="tab === 'general'">
            <div class="mt-7">
              <div class="flex items-center gap-4 border-b border-border py-3">
                <Label class="flex-1" for="type-name" variant="inline">{{ $t('stages.nameField') }}</Label>
                <Input
                  id="type-name"
                  v-model="typeForm.name"
                  class="max-w-[240px]"
                  maxlength="120"
                  type="text"
                  @change="saveType"
                />
              </div>
              <div class="flex items-center gap-4 border-b border-border py-3">
                <Label class="flex-1" for="type-active" variant="inline">{{ $t('stages.activeField') }}</Label>
                <Switch
                  id="type-active"
                  :model-value="typeForm.is_active"
                  @update:model-value="((typeForm.is_active = $event), saveType())"
                />
              </div>
              <div class="flex items-center gap-4 border-b border-border py-3">
                <Label class="flex-1" for="type-default" variant="inline">{{ $t('stages.defaultOnNew') }}</Label>
                <Switch
                  id="type-default"
                  :model-value="typeForm.is_default"
                  @update:model-value="((typeForm.is_default = $event), saveType())"
                />
              </div>
              <div class="flex items-center gap-4 border-b border-border py-3">
                <Label class="flex-1" for="type-duration" variant="inline">{{ $t('stages.durationField') }}</Label>
                <InputGroup class="max-w-[140px]">
                  <InputGroupInput
                    id="type-duration"
                    v-model.number="typeForm.duration_minutes"
                    class="tabular-nums"
                    max="1440"
                    min="1"
                    type="number"
                    @change="saveType"
                  />
                  <InputGroupAddon align="inline-end">{{ $t('common.unitMinutes') }}</InputGroupAddon>
                </InputGroup>
              </div>
            </div>

            <h3 class="mt-9 mb-3.5 text-body leading-[1.3] font-semibold tracking-[-0.015em]">
              {{ $t('stages.fieldsSection') }}
            </h3>

            <div v-if="fieldRows.length" class="flex flex-col gap-2 rounded-xl bg-sunk p-3">
              <button
                v-for="field in fieldRows"
                :key="field.id"
                class="cursor-grab touch-none rounded-lg border border-border bg-background px-3 py-2.5 text-left transition-[border-color,opacity] select-none hover:border-line-hover active:cursor-grabbing"
                :class="[
                  field.isActive ? '' : 'opacity-50',
                  held?.id === field.id && held?.moved ? 'opacity-40' : '',
                ]"
                :data-drag="field.id"
                type="button"
                @pointercancel="held = null"
                @pointerdown="grab($event, field.id)"
                @pointermove="move"
                @pointerup="release(field)"
              >
                <span class="flex items-baseline gap-2.5">
                  <span class="min-w-0 flex-1 text-ui leading-[1.3] font-semibold tracking-[-0.014em]">{{ field.label }}</span>
                  <span class="flex-none text-small text-ink-2">{{ fieldMeta(field) }}</span>
                </span>
                <span v-if="field.required || field.allowNewOptions" class="mt-1.5 flex items-center gap-1.5">
                  <span class="size-1.5 flex-none rounded-full" :class="field.required ? 'bg-action' : 'bg-faint'" />
                  <span class="text-small leading-none text-muted-foreground">
                    {{ field.required ? $t('stages.fieldRequired') : $t(`stages.fieldOpenList_${field.type}`) }}
                  </span>
                </span>
              </button>
            </div>

            <Button class="mt-3.5 ml-0" type="button" variant="ghost" @click="editField(null)">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
              {{ $t('stages.fieldShort') }}
            </Button>
          </template>

          <WorkflowPanel v-else :workflow="workflow as never" />
        </section>
  </FormShell>

    <Dialog :open="open === 'stage'" @update:open="open = null">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('stages.editStage') }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="stage-form" class="grid gap-4" @submit.prevent="saveStage">
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="stage-name">{{ $t('stages.nameField') }}</Label>
              <Input id="stage-name" v-model="stageForm.name" maxlength="120" type="text" />
            </div>

            <div class="flex min-w-0 flex-col gap-1.5">
              <Label>{{ $t('stages.colorField') }}</Label>
              <div class="flex gap-2">
                <button
                  v-for="color in colors"
                  :key="color.value"
                  :aria-label="color.label"
                  class="size-[22px] cursor-pointer rounded-full border-0"
                  :class="stageForm.color === color.value ? 'ring-2 ring-foreground ring-offset-2 ring-offset-background' : ''"
                  :style="{ background: color.css }"
                  type="button"
                  @click="stageForm.color = color.value"
                ></button>
              </div>
            </div>

            <div v-if="editing?.anchorLabel" class="flex min-w-0 flex-col gap-1.5">
              <Label>{{ $t('stages.anchorField') }}</Label>
              <span class="text-small leading-[1.4] text-ink-2">{{ editing.anchorLabel }}</span>
            </div>

            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="stage-key">{{ $t('stages.keyField') }}</Label>
              <Input id="stage-key" class="font-mono text-faint" disabled :model-value="editing?.key" type="text" />
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="stage-active" v-model="stageForm.is_active" />
              <Label for="stage-active" variant="inline">{{ $t('stages.activeField') }}</Label>
            </div>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button class="ml-0" form="stage-form" type="submit">{{ $t('stages.save') }}</Button>
          <Button v-if="editing?.anchor === null" class="ml-auto" type="button" variant="outline" @click="confirmDelete">
            {{ $t('stages.delete') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="open === 'newStage'" @update:open="open = null">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('stages.newStage', { pipeline: pipeline?.label }) }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="new-stage-form" class="grid gap-4" @submit.prevent="createStage">
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="new-stage-name">{{ $t('stages.nameField') }}</Label>
              <Input id="new-stage-name" v-model="newStageForm.name" maxlength="120" type="text" />
              <p v-if="newStageForm.errors.name" class="text-small leading-[1.45] text-destructive">{{ newStageForm.errors.name }}</p>
            </div>

            <div class="flex min-w-0 flex-col gap-1.5">
              <Label>{{ $t('stages.colorField') }}</Label>
              <div class="flex gap-2">
                <button
                  v-for="color in colors"
                  :key="color.value"
                  :aria-label="color.label"
                  class="size-[22px] cursor-pointer rounded-full border-0"
                  :class="newStageForm.color === color.value ? 'ring-2 ring-foreground ring-offset-2 ring-offset-background' : ''"
                  :style="{ background: color.css }"
                  type="button"
                  @click="newStageForm.color = color.value"
                ></button>
              </div>
            </div>

            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="new-stage-after">{{ $t('stages.positionField') }}</Label>
              <Select
                :model-value="newStageForm.after ?? ''"
                @update:model-value="(value) => (newStageForm.after = String(value) || null)"
              >
                <SelectTrigger id="new-stage-after">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="stage in pipeline?.stages ?? []" :key="stage.id" :value="stage.id">
                    {{ $t('stages.positionAfter', { stage: stage.name }) }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button class="ml-0" form="new-stage-form" type="submit">{{ $t('stages.add') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="open === 'deleteStage'" @update:open="open = null">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('stages.deleteStage', { name: editing?.name }) }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="delete-stage-form" class="flex min-w-0 flex-col gap-1.5" @submit.prevent="destroyStage">
            <Label for="move-to">{{ $t('stages.moveToField') }}</Label>
            <Select
              :model-value="deleteForm.move_to ?? ''"
              @update:model-value="(value) => (deleteForm.move_to = String(value) || null)"
            >
              <SelectTrigger id="move-to">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="stage in (pipeline?.stages ?? []).filter((one) => one.id !== editing?.id)"
                  :key="stage.id"
                  :value="stage.id"
                >
                  {{ stage.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="deleteForm.errors.move_to" class="text-small leading-[1.45] text-destructive">{{ deleteForm.errors.move_to }}</p>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button class="ml-0" form="delete-stage-form" tone="destructive" type="submit">
            {{ $t('stages.deleteAndMove') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="open === 'newType'" @update:open="open = null">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('stages.addType') }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="new-type-form" class="flex min-w-0 flex-col gap-1.5" @submit.prevent="createType">
            <Label for="new-type-name">{{ $t('stages.nameField') }}</Label>
            <Input id="new-type-name" v-model="newTypeForm.name" maxlength="120" type="text" />
            <p v-if="newTypeForm.errors.name" class="text-small leading-[1.45] text-destructive">{{ newTypeForm.errors.name }}</p>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button class="ml-0" form="new-type-form" type="submit">{{ $t('stages.add') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="open === 'field'" @update:open="open = null">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('stages.fieldShort') }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="field-form" class="grid gap-4" @submit.prevent="saveField">
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="field-label">{{ $t('stages.fieldLabel') }}</Label>
              <Input id="field-label" v-model="fieldForm.label" maxlength="120" type="text" />
              <p v-if="fieldForm.errors.label" class="text-small leading-[1.45] text-destructive">{{ fieldForm.errors.label }}</p>
            </div>

            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="field-type">{{ $t('stages.fieldType') }}</Label>
              <Select :model-value="fieldForm.type" @update:model-value="(value) => (fieldForm.type = String(value))">
                <SelectTrigger id="field-type"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="one in fieldTypes" :key="one.value" :value="one.value">{{ one.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="fieldForm.errors.type" class="text-small leading-[1.45] text-destructive">{{ fieldForm.errors.type }}</p>
            </div>

            <div v-if="fieldHasOptions" class="flex min-w-0 flex-col gap-1.5">
              <Label for="field-options">{{ $t('stages.fieldOptions') }}</Label>
              <Textarea id="field-options" v-model="fieldForm.options" />
              <p class="text-small leading-[1.45] text-ink-2">{{ $t('stages.fieldOptionsHint') }}</p>
            </div>

            <div v-if="fieldForm.type === 'number'" class="flex min-w-0 flex-col gap-1.5">
              <Label for="field-unit">{{ $t('stages.fieldUnit') }}</Label>
              <Input id="field-unit" v-model="fieldForm.unit" maxlength="40" type="text" />
            </div>

            <div v-if="fieldHasOptions" class="flex items-center gap-2.5">
              <Checkbox id="field-open" v-model="fieldForm.allow_new_options" />
              <Label for="field-open" variant="inline">{{ $t(`stages.fieldOpenList_${fieldForm.type}`) }}</Label>
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="field-required" v-model="fieldForm.required" />
              <Label for="field-required" variant="inline">{{ $t('stages.fieldRequiredField') }}</Label>
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="field-active" v-model="fieldForm.is_active" />
              <Label for="field-active" variant="inline">{{ $t('stages.activeField') }}</Label>
            </div>

            <p v-if="$page.props.errors.field" class="text-small leading-[1.45] text-destructive">{{ $page.props.errors.field }}</p>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button class="ml-0" form="field-form" type="submit">{{ $t('stages.save') }}</Button>
          <Button v-if="editingField" class="ml-auto" type="button" variant="outline" @click="destroyField">
            {{ $t('stages.delete') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
</template>
