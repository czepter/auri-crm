<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { Field } from '@/ui/field'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'

// Shared by Create and Edit, so the two forms cannot drift into building a
// form field two different ways. optionsText is a newline-separated string
// rather than the array FormField.options actually is — a textarea can only
// bind a string, and only "select" ever reads it; the page transforms it back
// to an array (or null) on submit, the same shape PackageItemsEditor's page
// transforms euros back to cents.
export type FormFieldRow = {
  label: string
  type: string
  required: boolean
  optionsText: string
}

const props = defineProps<{ fieldTypes: { value: string; label: string }[] }>()

const fields = defineModel<FormFieldRow[]>({ required: true })

// Which row is open for editing, at most one at a time — the mockup's own
// affordance (row collapsed, "Bearbeiten" opens it) has no room for two rows
// open together.
const editingIndex = ref<number | null>(null)

function addField(type: string) {
  fields.value = [...fields.value, { label: '', type, required: false, optionsText: '' }]
  editingIndex.value = fields.value.length - 1
}

function removeField(index: number) {
  fields.value = fields.value.filter((_, i) => i !== index)
  editingIndex.value = null
}

function typeLabel(type: string): string {
  return props.fieldTypes.find((t) => t.value === type)?.label ?? type
}
</script>

<template>
  <div>
    <div class="flex flex-col gap-1.5">
      <div v-for="(field, index) in fields" :key="index" class="group/qbrow flex items-start gap-2.5 rounded-lg border border-border bg-background p-2.5 hover:border-line-hover">
        <span class="mt-0.5 h-[22px] w-3.5 flex-none cursor-grab bg-icon-grip bg-center bg-no-repeat bg-[length:12px_18px] opacity-45 group-hover/qbrow:opacity-80"></span>

        <div v-if="editingIndex === index" class="flex-1">
          <div class="mb-6 grid grid-cols-2 gap-x-6 gap-y-4">
            <Field wide>
              <Label :for="`field-label-${index}`">{{ $t('formTemplates.labelField') }}</Label>
              <Input :id="`field-label-${index}`" v-model="field.label" type="text" maxlength="255" />
            </Field>

            <div v-if="field.type === 'select'" class="fld wide">
              <Label :for="`field-options-${index}`">{{ $t('formTemplates.optionsField') }}</Label>
              <Textarea :id="`field-options-${index}`" v-model="field.optionsText" rows="3" />
              <span class="text-[11.5px] leading-[1.45] text-faint">{{ $t('formTemplates.optionsHint') }}</span>
            </div>

            <Field>
              <div class="flex items-center gap-2.5">
                <Checkbox :id="`field-required-${index}`" v-model="field.required" />
                <Label variant="inline" :for="`field-required-${index}`">{{ $t('formTemplates.requiredField') }}</Label>
              </div>
            </Field>
          </div>

          <div class="mt-6 flex items-center gap-4">
            <Button variant="outline" type="button" @click="editingIndex = null">{{ $t('formTemplates.done') }}</Button>
            <Button variant="outline" tone="accent" type="button" @click="removeField(index)">{{ $t('common.remove') }}</Button>
          </div>
        </div>

        <div v-else class="flex-1">
          <div class="text-[13px] leading-[1.4] font-medium">{{ field.label || $t('formTemplates.untitled') }}</div>
          <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
            <span class="block rounded-[5px] bg-muted px-[7px] py-0.5 text-[10px] leading-[1.5] font-semibold tracking-[0.05em] uppercase text-muted-foreground">{{ typeLabel(field.type) }}</span>
            <span v-if="field.required" class="block rounded-[5px] bg-muted px-[7px] py-0.5 text-[10px] leading-[1.5] font-semibold tracking-[0.05em] uppercase text-muted-foreground">{{ $t('formTemplates.requiredChip') }}</span>
            <span v-if="field.type === 'select' && field.optionsText" class="text-[11.5px] leading-[1.5] text-faint">{{ field.optionsText.split('\n').filter(Boolean).join(', ') }}</span>
            <span v-if="field.type === 'name_list'" class="text-[11.5px] leading-[1.5] text-faint">{{ $t('formTemplates.nameListHint') }}</span>
          </div>
          <Button class="opacity-0 transition-opacity duration-[120ms] ease-out group-hover/qbrow:opacity-100" as="a" variant="outline" href="#" @click.prevent="editingIndex = index">{{ $t('formTemplates.edit') }}</Button>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <span class="mb-1.5 block">{{ $t('formTemplates.questionsLabel') }}</span>
      <div class="flex flex-wrap gap-1.5">
        <Button
          v-for="type in fieldTypes"
          :key="type.value"
          class="px-[11px] py-1.5 text-[11.5px] leading-[1.4]" as="a"
          variant="outline"
          href="#"
          @click.prevent="addField(type.value)"
        >
          {{ type.label }}
        </Button>
      </div>
    </div>
  </div>
</template>
