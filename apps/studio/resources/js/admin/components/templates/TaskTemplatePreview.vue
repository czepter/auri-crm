<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Label } from '@/ui/label'
import { NONE, fromOption, toOption } from '@/lib/select'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

const props = defineProps<{
  shootKinds: { value: string; label: string }[]
  preview: { title: string; dueOn: string | null }[] | null
  previewDate: string | null
  previewShootKind: string | null
  selected: string
}>()

const form = useForm({
  preview_date: props.previewDate ?? '',
  preview_shoot_kind: props.previewShootKind ?? '',
})

function run() {
  router.get('/vorlagen', {
    typ: 'aufgaben',
    vorlage: props.selected,
    preview_date: form.preview_date || undefined,
    preview_shoot_kind: form.preview_shoot_kind || undefined,
  }, { preserveScroll: true, preserveState: true })
}
</script>

<template>
  <section>
    <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('taskTemplates.previewLabel') }}</div>

    <form class="flex flex-wrap items-end gap-4" @submit.prevent="run">
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label for="preview-date">{{ $t('taskTemplates.previewDateField') }}</Label>
        <DatePicker id="preview-date" v-model="form.preview_date" />
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label for="preview-kind">{{ $t('taskTemplates.shootKindField') }}</Label>
        <Select
          :model-value="toOption(form.preview_shoot_kind)"
          @update:model-value="(value) => (form.preview_shoot_kind = fromOption(value))"
        >
          <SelectTrigger id="preview-kind"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem :value="NONE">{{ $t('taskTemplates.shootKindAll') }}</SelectItem>
            <SelectItem v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <Button class="[&[data-slot=button][data-variant=default]]:ml-0" type="submit" variant="outline" :disabled="!form.preview_date">
        {{ $t('taskTemplates.previewButton') }}
      </Button>
    </form>

    <div v-if="preview" class="mt-4 rounded-md border border-border p-2.5">
      <p v-if="preview.length === 0" class="m-0 text-[11.5px] leading-[1.45] text-faint">{{ $t('taskTemplates.previewEmpty') }}</p>
      <div v-for="row in preview" :key="row.title" class="flex items-center gap-2.5 text-[12.5px] leading-[1.3]">
        <span class="min-w-0 flex-1 text-foreground">{{ row.title }}</span>
        <span class="flex-none text-[12px] tabular-nums text-faint">{{ row.dueOn ?? '—' }}</span>
      </div>
    </div>
  </section>
</template>
