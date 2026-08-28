<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import { NONE, fromOption, toOption } from '@/lib/select'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

type Option = { value: string; label: string }
type Column = { stage: string; label: string; templates: App.Crm.Data.TaskTemplateData[] }

const props = defineProps<{
  workflow?: {
    type: string
    pipeline: string
    types: Option[]
    audiences: Option[]
    anchors: Option[]
    shootKinds: Option[]
    columns: Column[]
  } | null
}>()

const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()

// Der Typ steht in der URL, nicht im Component-State: ein Wechsel laedt die
// Spalten nach, und der zurueckkommende Prop ist die einzige Wahrheit.
function chooseType(type: string) {
  router.reload({ only: ['workflow'], data: { workflow_typ: type } })
}

const editing = ref<App.Crm.Data.TaskTemplateData | null>(null)
const stage = ref<string>('')

const form = useForm({
  title: '',
  audience: 'internal',
  project_type: '',
  pipeline: '',
  stage: '',
  day_offset: 0,
  anchor: 'stage',
  shoot_kind: '',
  is_active: true,
  sort_order: 0,
})

// Nur im Projekt-Zweig gibt es einen Termin, gegen den ein Versatz zaehlen
// kann. In den Lead-Phasen bleibt der Phasenwechsel der einzige Anker.
const anchors = computed(() =>
  props.workflow?.pipeline === 'lead'
    ? props.workflow.anchors.filter((anchor) => anchor.value === 'stage')
    : (props.workflow?.anchors ?? []),
)

function open(column: Column, template: App.Crm.Data.TaskTemplateData | null) {
  editing.value = template
  stage.value = column.stage

  form.defaults({
    title: template?.title ?? '',
    audience: template?.audience ?? 'internal',
    project_type: template?.projectType ?? '',
    pipeline: props.workflow!.pipeline,
    stage: column.stage,
    day_offset: template?.dayOffset ?? 0,
    anchor: template?.anchor ?? anchors.value[0].value,
    shoot_kind: template?.shootKind ?? '',
    is_active: template?.isActive ?? true,
    sort_order: template?.sortOrder ?? column.templates.length,
  })
  form.reset()
}

function submit() {
  const payload = form.transform((data) => ({
    ...data,
    project_type: data.project_type || null,
    shoot_kind: data.shoot_kind || null,
  }))

  const done = { preserveScroll: true, onSuccess: () => close() }

  editing.value
    ? payload.patch(`/einstellungen/aufgabenvorlagen/${editing.value.id}`, done)
    : payload.post('/einstellungen/aufgabenvorlagen', done)
}

function remove() {
  router.delete(`/einstellungen/aufgabenvorlagen/${editing.value!.id}`, {
    preserveScroll: true,
    onSuccess: () => close(),
  })
}

function close() {
  stage.value = ''
  editing.value = null
}

function meta(template: App.Crm.Data.TaskTemplateData) {
  const days = template.dayOffset > 0 ? `+${template.dayOffset}` : String(template.dayOffset)

  return `${days} ${t('common.unitDays')} · ${template.anchorLabel}`
}
</script>

<template>
  <div v-if="workflow" data-tokens>
    <!-- Legt sich auf den Inhalt, nicht auf den Seitenkopf: die Spalten stehen
         ueber den Spalten, die sie steuern. -->
    <div class="absolute inset-x-0 bottom-0 top-[70px] z-40 bg-[rgb(25_25_25/0.26)]" @click="emit('close')" />

    <div class="absolute inset-x-[18px] bottom-[18px] top-[106px] z-50 flex flex-col overflow-hidden rounded-xl border border-border bg-paper shadow-[0_24px_64px_rgb(25_25_25/0.22)]">
      <div class="flex items-center gap-3.5 border-b border-border px-5 py-4">
        <h2 class="m-0 text-[16px] leading-[1.2] font-semibold tracking-[-0.022em]">{{ $t('workflow.title') }}</h2>

        <div class="flex gap-[3px] rounded-[9px] border border-border p-[3px]">
          <button
            v-for="type in workflow.types"
            :key="type.value"
            class="cursor-pointer rounded-md border-0 px-3 py-[5px] text-[13px]"
            :class="type.value === workflow.type ? 'bg-foreground font-semibold text-paper' : 'bg-transparent font-medium text-muted-foreground'"
            type="button"
            @click="chooseType(type.value)"
          >{{ type.label }}</button>
        </div>

        <Button class="ml-auto" type="button" @click="emit('close')">{{ $t('workflow.done') }}</Button>
      </div>

      <div class="flex flex-1 items-stretch gap-2.5 overflow-x-auto p-[18px_18px_20px]">
        <section
          v-for="column in workflow.columns"
          :key="column.stage"
          class="flex min-w-0 flex-[1_0_216px] flex-col gap-2 rounded-xl bg-sunk p-2.5"
        >
          <header class="flex items-baseline gap-1.5 px-1 pt-0.5 pb-1">
            <b class="text-[11px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">{{ column.label }}</b>
            <span class="ml-auto text-[11px] tabular-nums text-ink-2">{{ column.templates.length || '—' }}</span>
          </header>

          <button
            v-for="template in column.templates"
            :key="template.id"
            class="block cursor-pointer rounded-lg border border-border bg-paper px-2.5 py-[9px] text-left"
            :class="!template.isActive && 'opacity-50'"
            type="button"
            @click="open(column, template)"
          >
            <span class="block text-[13.5px] leading-[1.3] font-semibold tracking-[-0.014em]">{{ template.title }}</span>
            <span class="mt-[3px] block text-[12px] leading-[1.35] tabular-nums text-ink-2">{{ meta(template) }}</span>
            <span class="mt-1.5 flex items-center gap-1.5">
              <span class="size-1.5 flex-none rounded-full" :class="template.audience === 'client' ? 'bg-action' : 'bg-faint'" />
              <span class="text-[11.5px] leading-none text-muted-foreground">{{ template.audienceLabel }}</span>
            </span>
          </button>

          <button
            class="flex cursor-pointer items-center gap-1.5 rounded-lg border-0 bg-transparent px-1 py-[7px] text-[12.5px] font-medium text-faint"
            type="button"
            @click="open(column, null)"
          >
            <svg class="size-3.5 fill-none stroke-current stroke-2 [stroke-linecap:round]" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            {{ $t('workflow.addTask') }}
          </button>
        </section>
      </div>
    </div>

    <Dialog :open="stage !== ''" @update:open="(value) => !value && close()">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('workflow.taskTitle') }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="workflow-task-form" class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4" @submit.prevent="submit">
            <div class="col-span-full flex min-w-0 flex-col gap-1.5">
              <Label for="workflow-title">{{ $t('taskTemplates.titleField') }}</Label>
              <Input id="workflow-title" v-model="form.title" type="text" maxlength="255" />
              <p v-if="form.errors.title" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.title }}</p>
            </div>

            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="workflow-audience">{{ $t('taskTemplates.audienceField') }}</Label>
              <Select :model-value="form.audience" @update:model-value="(value) => (form.audience = String(value))">
                <SelectTrigger id="workflow-audience"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="audience in workflow.audiences" :key="audience.value" :value="audience.value">{{ audience.label }}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="workflow-kind">{{ $t('taskTemplates.shootKindField') }}</Label>
              <Select :model-value="toOption(form.shoot_kind)" @update:model-value="(value) => (form.shoot_kind = fromOption(value))">
                <SelectTrigger id="workflow-kind"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem :value="NONE">{{ $t('taskTemplates.shootKindAll') }}</SelectItem>
                  <SelectItem v-for="kind in workflow.shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="workflow-anchor">{{ $t('workflow.anchorField') }}</Label>
              <Select :model-value="form.anchor" @update:model-value="(value) => (form.anchor = String(value))">
                <SelectTrigger id="workflow-anchor"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="anchor in anchors" :key="anchor.value" :value="anchor.value">{{ anchor.label }}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="workflow-offset">{{ $t('taskTemplates.dayOffsetField') }}</Label>
              <InputGroup>
                <InputGroupInput id="workflow-offset" v-model.number="form.day_offset" class="tabular-nums" type="number" />
                <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
              </InputGroup>
              <p v-if="form.errors.day_offset" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.day_offset }}</p>
            </div>

            <div class="col-span-full flex items-center gap-2.5">
              <Checkbox id="workflow-active" v-model="form.is_active" />
              <Label variant="inline" for="workflow-active">{{ $t('taskTemplates.activeField') }}</Label>
            </div>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button type="submit" form="workflow-task-form" :disabled="form.processing">{{ $t('taskTemplates.save') }}</Button>
          <DialogClose as-child>
            <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
          </DialogClose>
          <Button v-if="editing" class="ml-auto" variant="ghost" type="button" @click="remove">{{ $t('taskTemplates.delete') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
