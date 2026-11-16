<script setup lang="ts">
import { watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import Chip from '@/ui/page/Chip.vue'

const props = defineProps<{
  template: App.Crm.Data.TaskTemplateData
  audiences: { value: string; label: string }[]
  shootKinds: string[]
}>()

// Keyed by the row on screen rather than one form per template: the hub shows
// one editor at a time, so a second draft has nothing to hold.
const form = useForm({
  title: props.template.title,
  audience: props.template.audience,
  day_offset: props.template.dayOffset,
  shoot_kind: props.template.shootKind ?? '',
  is_active: props.template.isActive,
  sort_order: props.template.sortOrder,
})

watch(
  () => props.template,
  (template) => {
    form.defaults({
      title: template.title,
      audience: template.audience,
      day_offset: template.dayOffset,
      shoot_kind: template.shootKind ?? '',
      is_active: template.isActive,
      sort_order: template.sortOrder,
    })
    form.reset()
  },
)

function submit() {
  form
    .transform((data) => ({ ...data, shoot_kind: data.shoot_kind || null }))
    .patch(`/einstellungen/aufgabenvorlagen/${props.template.id}`, { preserveScroll: true })
}

function remove() {
  router.delete(`/einstellungen/aufgabenvorlagen/${props.template.id}`, {
    preserveScroll: true,
    // The deleted row is the one the URL points at; without dropping it the
    // hub reopens an editor for a template that is gone.
    onSuccess: () => router.get('/vorlagen', { typ: 'aufgaben' }, { preserveScroll: true }),
  })
}
</script>

<template>
  <form :id="`task-template-${template.id}`" @submit.prevent="submit">
    <div class="mb-6 flex items-center gap-3">
      <h2 class="m-0 min-w-0 flex-1 truncate text-title leading-[1.2] font-semibold tracking-[-0.02em]">{{ template.title }}</h2>
      <div class="flex flex-none items-center gap-2.5">
        <Label variant="inline" :for="`active-${template.id}`">{{ $t('taskTemplates.activeField') }}</Label>
        <Checkbox :id="`active-${template.id}`" v-model="form.is_active" />
      </div>
    </div>

    <div class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4">
      <div class="col-span-full flex min-w-0 flex-col gap-1.5">
        <Label :for="`title-${template.id}`">{{ $t('taskTemplates.titleField') }}</Label>
        <Input :id="`title-${template.id}`" v-model="form.title" type="text" maxlength="255" />
        <p v-if="form.errors.title" class="text-small leading-[1.45] text-destructive">{{ form.errors.title }}</p>
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`audience-${template.id}`">{{ $t('taskTemplates.audienceField') }}</Label>
        <Select :model-value="form.audience" @update:model-value="(value) => (form.audience = String(value))">
          <SelectTrigger :id="`audience-${template.id}`"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="audience in audiences" :key="audience.value" :value="audience.value">{{ audience.label }}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`kind-${template.id}`">{{ $t('taskTemplates.shootKindField') }}</Label>
        <Input :id="`kind-${template.id}`" v-model="form.shoot_kind" type="text" maxlength="255" :list="`shoot-kinds-${template.id}`" :placeholder="$t('taskTemplates.shootKindAll')" />
        <datalist :id="`shoot-kinds-${template.id}`">
          <option v-for="kind in shootKinds" :key="kind" :value="kind" />
        </datalist>
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`offset-${template.id}`">{{ $t('taskTemplates.dayOffsetField') }}</Label>
        <InputGroup>
          <InputGroupInput :id="`offset-${template.id}`" v-model.number="form.day_offset" class="tabular-nums" type="number" />
          <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
        </InputGroup>
        <span class="text-small leading-[1.45] text-faint">{{ $t('taskTemplates.dayOffsetHint') }}</span>
        <p v-if="form.errors.day_offset" class="text-small leading-[1.45] text-destructive">{{ form.errors.day_offset }}</p>
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`sort-${template.id}`">{{ $t('taskTemplates.sortOrderField') }}</Label>
        <Input :id="`sort-${template.id}`" v-model.number="form.sort_order" class="tabular-nums" type="number" min="0" />
        <p v-if="form.errors.sort_order" class="text-small leading-[1.45] text-destructive">{{ form.errors.sort_order }}</p>
      </div>
    </div>

    <div class="mt-6 flex items-center gap-4 [&_[data-slot=button][data-variant=default]]:ml-0">
      <template v-if="form.isDirty">
        <Button class="ml-auto" type="submit" :disabled="form.processing">
          {{ $t('taskTemplates.save') }}
        </Button>
        <Chip tone="warn">{{ $t('settings.unsaved') }}</Chip>
      </template>
      <Button class="ml-auto" type="button" variant="outline" @click="remove">{{ $t('taskTemplates.delete') }}</Button>
    </div>
  </form>
</template>
