<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'

type Stage = {
  id: string
  key: string
  name: string
  color: string
  css: string
  anchor: string | null
  anchorLabel: string | null
  isActive: boolean
}

type Pipeline = {
  value: string
  label: string
  stages: Stage[]
}

defineProps<{
  pipelines: Pipeline[]
  types: { id: string; key: string; name: string; isActive: boolean; isDefault: boolean }[]
  colors: { value: string; label: string; css: string }[]
}>()

const emit = defineEmits<{ close: [] }>()

// Which dialog is open, and over which row. One ref rather than three
// booleans: the three are mutually exclusive by construction.
const open = ref<'stage' | 'newStage' | 'deleteStage' | 'type' | 'newType' | null>(null)
const editing = ref<Stage | null>(null)
const editingPipeline = ref<Pipeline | null>(null)

const stageForm = useForm({ name: '', color: 'grey', is_active: true })
const newStageForm = useForm({ pipeline: 'lead', name: '', color: 'blue', after: null as string | null })
const deleteForm = useForm({ move_to: null as string | null })
const typeForm = useForm({ name: '', is_active: true, is_default: false })
const newTypeForm = useForm({ name: '' })
const editingType = ref<{ id: string; name: string } | null>(null)

function editStage(pipeline: Pipeline, stage: Stage) {
  editing.value = stage
  editingPipeline.value = pipeline
  stageForm.name = stage.name
  stageForm.color = stage.color
  stageForm.is_active = stage.isActive
  open.value = 'stage'
}

function addStage(pipeline: Pipeline) {
  editingPipeline.value = pipeline
  newStageForm.pipeline = pipeline.value
  newStageForm.name = ''
  newStageForm.after = pipeline.stages.at(-1)?.id ?? null
  open.value = 'newStage'
}

function saveStage() {
  stageForm.patch(`/stufen/${editing.value!.id}`, {
    preserveScroll: true,
    onSuccess: () => (open.value = null),
  })
}

function createStage() {
  newStageForm.post('/stufen', {
    preserveScroll: true,
    onSuccess: () => (open.value = null),
  })
}

function confirmDelete() {
  deleteForm.move_to = editingPipeline.value?.stages.find((one) => one.id !== editing.value?.id)?.id ?? null
  open.value = 'deleteStage'
}

function destroyStage() {
  deleteForm.delete(`/stufen/${editing.value!.id}`, {
    preserveScroll: true,
    onSuccess: () => (open.value = null),
  })
}

function editType(type: { id: string; name: string; isActive: boolean; isDefault: boolean }) {
  editingType.value = type
  typeForm.name = type.name
  typeForm.is_active = type.isActive
  typeForm.is_default = type.isDefault
  open.value = 'type'
}

function saveType() {
  typeForm.patch(`/projekttypen/${editingType.value!.id}`, {
    preserveScroll: true,
    onSuccess: () => (open.value = null),
  })
}

function createType() {
  newTypeForm.post('/projekttypen', {
    preserveScroll: true,
    onSuccess: () => (open.value = null),
  })
}

// Drag to reorder, on the chips themselves — the order is the meaning, so it
// is edited where it is read rather than in a number field. Native drag
// events: a list this short needs no library.
const dragging = ref<{ pipeline: string; id: string } | null>(null)

function drop(pipeline: Pipeline, target: Stage) {
  const held = dragging.value
  dragging.value = null

  if (held === null || held.pipeline !== pipeline.value || held.id === target.id) return

  const order = pipeline.stages.map((stage) => stage.id).filter((id) => id !== held.id)
  order.splice(order.indexOf(target.id), 0, held.id)

  router.patch('/stufen/reihenfolge', { pipeline: pipeline.value, order }, { preserveScroll: true })
}
</script>

<template>
  <!-- Over the list it orders, not a room of its own: the stages are read on
       the board, so they are edited on top of it. Fixed rather than inline so
       the list keeps its scroll position underneath. -->
  <div data-tokens class="fixed inset-0 z-40 overflow-y-auto bg-background">
    <div class="mx-auto w-full max-w-[720px] px-6 pb-16">
      <div class="flex items-center gap-4 py-[18px]">
        <button
          :aria-label="$t('stages.done')"
          class="inline-flex cursor-pointer border-0 bg-transparent p-1 text-ink-2"
          type="button"
          @click="emit('close')"
        >
          <svg class="size-[18px] fill-none stroke-current stroke-[1.75] [stroke-linecap:round]" viewBox="0 0 24 24">
            <path d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>
        <span class="text-[16px] leading-[1.3] font-semibold tracking-[-0.015em]">{{ $t('stages.title') }}</span>
        <Button type="button" @click="emit('close')">{{ $t('stages.done') }}</Button>
      </div>

      <div class="pt-10">
            <section v-for="pipeline in pipelines" :key="pipeline.value" class="mb-11">
              <h2 class="text-[15px] leading-[1.3] font-semibold tracking-[-0.015em]">
                {{ $t('stages.stagesSection', { pipeline: pipeline.label }) }}
              </h2>

              <div class="mt-3.5 flex flex-wrap gap-2 rounded-xl bg-sunk p-3">
                <button
                  v-for="stage in pipeline.stages"
                  :key="stage.id"
                  class="inline-flex cursor-pointer items-center gap-[7px] rounded-full border border-border bg-background px-3 py-1.5 text-[10.5px] leading-[1.4] font-semibold tracking-[0.05em] uppercase transition-colors hover:border-line-hover"
                  :class="stage.isActive ? 'text-ink-2' : 'text-faint'"
                  draggable="true"
                  type="button"
                  @click="editStage(pipeline, stage)"
                  @dragstart="dragging = { pipeline: pipeline.value, id: stage.id }"
                  @dragover.prevent
                  @drop.prevent="drop(pipeline, stage)"
                >
                  <span class="size-1.5 flex-none rounded-full" :style="{ background: stage.css }"></span>
                  {{ stage.name }}
                </button>
              </div>

              <Button class="mt-3.5 ml-0" type="button" variant="ghost" @click="addStage(pipeline)">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
                {{ $t('stages.addStage') }}
              </Button>
            </section>

            <section>
              <h2 class="text-[15px] leading-[1.3] font-semibold tracking-[-0.015em]">{{ $t('stages.typesSection') }}</h2>

              <div class="mt-3.5 flex flex-wrap gap-2">
                <button
                  v-for="type in types"
                  :key="type.id"
                  class="inline-flex cursor-pointer items-center gap-[7px] rounded-full border-0 bg-sunk-2 px-3.5 py-2 text-[13px] leading-[1.2] transition-colors hover:bg-[#E9E9EC]"
                  :class="type.isActive ? 'text-foreground' : 'text-faint'"
                  type="button"
                  @click="editType(type)"
                >
                  {{ type.name }}
                  <svg v-if="type.isDefault" class="size-3.5 text-ink-2" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                </button>
              </div>

              <Button class="mt-3.5 ml-0" type="button" variant="ghost" @click="((newTypeForm.name = ''), (open = 'newType'))">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
                {{ $t('stages.addType') }}
              </Button>
        </section>
      </div>
    </div>

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
              <span class="text-[13px] leading-[1.4] text-ink-2">{{ editing.anchorLabel }}</span>
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
          <DialogTitle>{{ $t('stages.newStage', { pipeline: editingPipeline?.label }) }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="new-stage-form" class="grid gap-4" @submit.prevent="createStage">
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="new-stage-name">{{ $t('stages.nameField') }}</Label>
              <Input id="new-stage-name" v-model="newStageForm.name" maxlength="120" type="text" />
              <p v-if="newStageForm.errors.name" class="text-[11.5px] leading-[1.45] text-destructive">{{ newStageForm.errors.name }}</p>
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
                  <SelectItem v-for="stage in editingPipeline?.stages ?? []" :key="stage.id" :value="stage.id">
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
                  v-for="stage in (editingPipeline?.stages ?? []).filter((one) => one.id !== editing?.id)"
                  :key="stage.id"
                  :value="stage.id"
                >
                  {{ stage.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="deleteForm.errors.move_to" class="text-[11.5px] leading-[1.45] text-destructive">{{ deleteForm.errors.move_to }}</p>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button class="ml-0" form="delete-stage-form" tone="destructive" type="submit">
            {{ $t('stages.deleteAndMove') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="open === 'type'" @update:open="open = null">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ editingType?.name }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="type-form" class="grid gap-4" @submit.prevent="saveType">
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="type-name">{{ $t('stages.nameField') }}</Label>
              <Input id="type-name" v-model="typeForm.name" maxlength="120" type="text" />
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="type-active" v-model="typeForm.is_active" />
              <Label for="type-active" variant="inline">{{ $t('stages.activeField') }}</Label>
            </div>

            <div class="flex items-center gap-2.5">
              <Checkbox id="type-default" v-model="typeForm.is_default" />
              <Label for="type-default" variant="inline">{{ $t('stages.defaultField') }}</Label>
            </div>

            <p v-if="$page.props.errors.type" class="text-[11.5px] leading-[1.45] text-destructive">{{ $page.props.errors.type }}</p>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button class="ml-0" form="type-form" type="submit">{{ $t('stages.save') }}</Button>
          <Button
            class="ml-auto"
            type="button"
            variant="outline"
            @click="router.delete(`/projekttypen/${editingType!.id}`, { preserveScroll: true, onSuccess: () => (open = null) })"
          >
            {{ $t('stages.delete') }}
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
            <p v-if="newTypeForm.errors.name" class="text-[11.5px] leading-[1.45] text-destructive">{{ newTypeForm.errors.name }}</p>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button class="ml-0" form="new-type-form" type="submit">{{ $t('stages.add') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
