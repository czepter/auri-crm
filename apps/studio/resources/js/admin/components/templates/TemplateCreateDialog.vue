<script setup lang="ts">
import { ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

type Kind = 'aufgaben' | 'fragebogen' | 'dokumente' | 'galerien'

const props = defineProps<{
  kinds: Kind[]
  kind: Kind | null
  audiences: { value: string; label: string }[]
  shootKinds: string[]
  projectTypes: { value: string; label: string }[]
}>()

const KIND_LABEL: Record<Kind, string> = {
  aufgaben: 'templates.tasks',
  fragebogen: 'templates.forms',
  dokumente: 'templates.documents',
  galerien: 'templates.galleries',
}

// Fragebogen und Dokument haben je einen eigenen Anlegeweg — der eine baut
// Fragen, der andere schreibt einen Text. Der Dialog führt dorthin, statt
// einen dritten zu erfinden.
const HREF: Partial<Record<Kind, string>> = {
  fragebogen: '/einstellungen/fragebogen-vorlagen/neu',
  dokumente: '/dokumentvorlagen/neu',
}

const open = ref(false)
const chosen = ref<Kind>(props.kind ?? props.kinds[0])

watch(open, (on) => {
  if (on) chosen.value = props.kind ?? props.kinds[0]
})

const task = useForm({
  title: '',
  audience: 'internal',
  day_offset: 0,
  shoot_kind: '',
  is_active: true,
  sort_order: 0,
})

const preset = useForm({
  name: '',
  expiry_mode: 'inherit',
  expiry_days: '' as string | number,
  requires_settled_balance: null,
  downloads_enabled: null,
  lists_enabled: null,
  comments_enabled: null,
  design: null,
})

function submit() {
  if (chosen.value === 'aufgaben') {
    task
      .transform((data) => ({ ...data, shoot_kind: data.shoot_kind || null }))
      .post('/einstellungen/aufgabenvorlagen', {
        preserveScroll: true,
        onSuccess: () => {
          task.reset()
          open.value = false
        },
      })

    return
  }

  preset.post('/einstellungen/galerie-vorlagen', {
    preserveScroll: true,
    onSuccess: () => {
      preset.reset()
      open.value = false
    },
  })
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button type="button" variant="pill">{{ $t('templates.create') }}</Button>
    </DialogTrigger>

    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('templates.create') }}</DialogTitle>
      </DialogHeader>

      <DialogBody>
        <div class="flex flex-col gap-1.5">
          <Label for="create-kind">{{ $t('templates.kindLabel') }}</Label>
          <Select :model-value="chosen" @update:model-value="(value) => (chosen = value as Kind)">
            <SelectTrigger id="create-kind"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="entry in kinds" :key="entry" :value="entry">{{ $t(KIND_LABEL[entry]) }}</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <form
          v-if="chosen === 'aufgaben'"
          id="template-create-task"
          class="mt-4 grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4"
          @submit.prevent="submit"
        >
          <div class="col-span-full flex min-w-0 flex-col gap-1.5">
            <Label for="create-title">{{ $t('taskTemplates.titleField') }}</Label>
            <Input id="create-title" v-model="task.title" type="text" maxlength="255" />
            <p v-if="task.errors.title" class="text-[11.5px] leading-[1.45] text-destructive">{{ task.errors.title }}</p>
          </div>

          <div class="flex min-w-0 flex-col gap-1.5">
            <Label for="create-audience">{{ $t('taskTemplates.audienceField') }}</Label>
            <Select :model-value="task.audience" @update:model-value="(value) => (task.audience = String(value))">
              <SelectTrigger id="create-audience"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="audience in audiences" :key="audience.value" :value="audience.value">{{ audience.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex min-w-0 flex-col gap-1.5">
            <Label for="create-kind-shoot">{{ $t('taskTemplates.shootKindField') }}</Label>
            <Input id="create-kind-shoot" v-model="task.shoot_kind" type="text" maxlength="255" list="create-shoot-kinds" :placeholder="$t('taskTemplates.shootKindAll')" />
            <datalist id="create-shoot-kinds">
              <option v-for="entry in shootKinds" :key="entry" :value="entry" />
            </datalist>
          </div>

          <div class="flex min-w-0 flex-col gap-1.5">
            <Label for="create-offset">{{ $t('taskTemplates.dayOffsetField') }}</Label>
            <InputGroup>
              <InputGroupInput id="create-offset" v-model.number="task.day_offset" class="tabular-nums" type="number" />
              <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
            </InputGroup>
            <span class="text-[11.5px] leading-[1.45] text-faint">{{ $t('taskTemplates.dayOffsetHint') }}</span>
          </div>
        </form>

        <form
          v-else-if="chosen === 'galerien'"
          id="template-create-preset"
          class="mt-4 grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4"
          @submit.prevent="submit"
        >
          <div class="col-span-full flex min-w-0 flex-col gap-1.5">
            <Label for="create-preset-name">{{ $t('galleryPresets.nameField') }}</Label>
            <Input id="create-preset-name" v-model="preset.name" type="text" />
            <p v-if="preset.errors.name" class="text-[11.5px] leading-[1.45] text-destructive">{{ preset.errors.name }}</p>
          </div>

          <div class="flex min-w-0 flex-col gap-1.5">
            <Label for="create-preset-mode">{{ $t('galleryPresets.expiryField') }}</Label>
            <Select v-model="preset.expiry_mode">
              <SelectTrigger id="create-preset-mode"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="mode in ['inherit', 'days', 'never']" :key="mode" :value="mode">{{ $t(`galleryPresets.mode_${mode}`) }}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div v-if="preset.expiry_mode === 'days'" class="flex min-w-0 flex-col gap-1.5">
            <Label for="create-preset-days">{{ $t('gallerySettings.expiryLabel') }}</Label>
            <Input id="create-preset-days" v-model="preset.expiry_days" class="tabular-nums" type="number" min="1" />
          </div>
        </form>
      </DialogBody>

      <DialogFooter>
        <Button
          v-if="HREF[chosen]"
          :as="Link"
          :href="HREF[chosen]!"
          variant="pill"
        >{{ $t('templates.create') }}</Button>
        <Button
          v-else
          type="submit"
          :form="chosen === 'aufgaben' ? 'template-create-task' : 'template-create-preset'"
          variant="pill"
          :disabled="task.processing || preset.processing"
        >{{ $t('templates.create') }}</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
