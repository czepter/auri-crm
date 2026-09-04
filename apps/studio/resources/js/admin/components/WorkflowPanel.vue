<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
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

// Die Schreibrouten kehren mit back() zurueck, und ein gewoehnlicher Besuch
// traegt das Panel-Prop nicht — ohne das Nachladen stuende die Liste nach dem
// Speichern leer da.
function reload() {
  close()
  router.reload({ only: ['workflow'] })
}

function submit() {
  const payload = form.transform((data) => ({
    ...data,
    project_type: data.project_type || null,
    shoot_kind: data.shoot_kind || null,
  }))

  const done = { preserveScroll: true, onSuccess: () => reload() }

  editing.value
    ? payload.patch(`/einstellungen/aufgabenvorlagen/${editing.value.id}`, done)
    : payload.post('/einstellungen/aufgabenvorlagen', done)
}

function remove() {
  router.delete(`/einstellungen/aufgabenvorlagen/${editing.value!.id}`, {
    preserveScroll: true,
    onSuccess: () => reload(),
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
  <!-- Dieselbe Flaeche wie StageSettings, aus demselben Grund: die Aufgaben
       einer Stufe werden auf der Liste gelesen, also auch dort bearbeitet.
       Fest statt im Fluss, damit die Liste darunter ihre Scrollposition
       behaelt. -->
  <div v-if="workflow" data-tokens class="fixed inset-0 z-40 overflow-y-auto bg-background">
    <div class="mx-auto w-full max-w-[720px] px-6 pb-16">
      <!-- Bleibt stehen: die Liste ist so lang wie das Studio Stufen hat, und
           der Weg hier heraus darf nicht nach oben scrollen. -->
      <div class="sticky top-0 z-10 flex items-center gap-4 bg-background py-[18px]">
        <button
          :aria-label="$t('workflow.done')"
          class="inline-flex cursor-pointer border-0 bg-transparent p-1 text-ink-2"
          type="button"
          @click="emit('close')"
        >
          <svg class="size-[18px] fill-none stroke-current stroke-[1.75] [stroke-linecap:round]" viewBox="0 0 24 24">
            <path d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>
        <span class="text-[16px] leading-[1.3] font-semibold tracking-[-0.015em]">{{ $t('workflow.title') }}</span>
        <Button type="button" @click="emit('close')">{{ $t('workflow.done') }}</Button>
      </div>

      <div class="pt-10">
        <section class="mb-11">
          <h2 class="text-[15px] leading-[1.3] font-semibold tracking-[-0.015em]">{{ $t('workflow.typeSection') }}</h2>

          <div class="mt-3.5 flex flex-wrap gap-2">
            <button
              v-for="type in workflow.types"
              :key="type.value"
              class="inline-flex cursor-pointer items-center gap-[7px] rounded-full border-0 bg-sunk-2 px-3.5 py-2 text-[13px] leading-[1.2] transition-colors hover:bg-[#E9E9EC]"
              :class="type.value === workflow.type ? 'font-semibold text-foreground' : 'text-ink-2'"
              type="button"
              @click="chooseType(type.value)"
            >
              {{ type.label }}
            </button>
          </div>
        </section>

        <section v-for="column in workflow.columns" :key="column.stage" class="mb-11">
          <h2 class="text-[15px] leading-[1.3] font-semibold tracking-[-0.015em]">{{ column.label }}</h2>

          <div v-if="column.templates.length" class="mt-3.5 flex flex-col gap-2 rounded-xl bg-sunk p-3">
            <button
              v-for="template in column.templates"
              :key="template.id"
              class="cursor-pointer rounded-lg border border-border bg-background px-3 py-2.5 text-left transition-colors hover:border-line-hover"
              :class="!template.isActive && 'opacity-50'"
              type="button"
              @click="open(column, template)"
            >
              <span class="flex items-baseline gap-2.5">
                <span class="min-w-0 flex-1 text-[13.5px] leading-[1.3] font-semibold tracking-[-0.014em]">{{ template.title }}</span>
                <span class="flex-none text-[12px] tabular-nums text-ink-2">{{ meta(template) }}</span>
              </span>
              <span class="mt-1.5 flex items-center gap-1.5">
                <span class="size-1.5 flex-none rounded-full" :class="template.audience === 'client' ? 'bg-action' : 'bg-faint'" />
                <span class="text-[11.5px] leading-none text-muted-foreground">{{ template.audienceLabel }}</span>
              </span>
            </button>
          </div>

          <Button class="mt-3.5 ml-0" type="button" variant="ghost" @click="open(column, null)">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
            {{ $t('workflow.addTask') }}
          </Button>
        </section>
      </div>
    </div>

    <Dialog :open="stage !== ''" @update:open="(value) => !value && close()">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('workflow.taskTitle') }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="workflow-task-form" class="grid gap-4" @submit.prevent="submit">
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="workflow-title">{{ $t('taskTemplates.titleField') }}</Label>
              <Input id="workflow-title" v-model="form.title" maxlength="255" type="text" />
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

            <div class="flex items-center gap-2.5">
              <Checkbox id="workflow-active" v-model="form.is_active" />
              <Label for="workflow-active" variant="inline">{{ $t('taskTemplates.activeField') }}</Label>
            </div>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button class="ml-0" form="workflow-task-form" type="submit">{{ $t('taskTemplates.save') }}</Button>
          <Button v-if="editing" class="ml-auto" type="button" variant="outline" @click="remove">{{ $t('taskTemplates.delete') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
