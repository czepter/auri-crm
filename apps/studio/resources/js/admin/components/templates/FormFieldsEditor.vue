<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { Field } from '@/ui/field'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'

// Shared by Create and Edit, so the two forms cannot drift into building a
// form field two different ways. optionsText is a newline-separated string
// rather than the array FormField.options actually is — a textarea can only
// bind a string; "select" reads it as its choices and "file" as its allowed
// formats. The page transforms it back on submit, the same shape
// PackageItemsEditor's page transforms euros back to cents.
export type FormFieldRow = {
  id?: string
  label: string
  type: string
  required: boolean
  optionsText: string
  fileMaxSize?: number
  fileMultiple?: boolean
  helpText: string
  minutesPerGroup?: number
}

const { t } = useI18n()

const props = defineProps<{ fieldTypes: { value: string; label: string }[] }>()

const fields = defineModel<FormFieldRow[]>({ required: true })

// Which row is open for editing, at most one at a time — the mockup's own
// affordance (row collapsed, "Bearbeiten" opens it) has no room for two rows
// open together.
const editingIndex = ref<number | null>(null)

function addField(type: string) {
  // Der Index vor dem Schreiben: fields.value liest durch das Model, das die
  // neue Zeile erst nach dem Elternteil kennt — hinterher gelesen öffnete es
  // die vorige Zeile, bei der ersten gar keine.
  const added = fields.value.length

  fields.value = [...fields.value, { label: '', type, required: false, optionsText: '', fileMaxSize: 10, fileMultiple: false, helpText: '', minutesPerGroup: 2 }]
  editingIndex.value = added
}

function removeField(index: number) {
  fields.value = fields.value.filter((_, i) => i !== index)
  editingIndex.value = null
}

function typeLabel(type: string): string {
  return props.fieldTypes.find((t) => t.value === type)?.label ?? type
}

// Drei Typen teilen sich das Textfeld über der einen options-Spalte, und
// jeder nennt seine Zeilen anders — siehe FormFieldType::optionRules().
function optionsLabel(type: string): string {
  return t(type === 'file' ? 'formTemplates.formatsField' : type === 'group_photos' ? 'formTemplates.shotsField' : 'formTemplates.optionsField')
}

function optionsHint(type: string): string {
  return t(type === 'file' ? 'formTemplates.formatsHint' : type === 'group_photos' ? 'formTemplates.shotsHint' : 'formTemplates.optionsHint')
}
</script>

<template>
  <div>
    <div class="flex flex-col gap-1.5">
      <div v-for="(field, index) in fields" :key="index" class="group/qbrow flex items-start gap-2.5 rounded-xl border border-border bg-background p-2.5 hover:border-line-hover">
        <span class="mt-0.5 h-[22px] w-3.5 flex-none cursor-grab bg-icon-grip bg-center bg-no-repeat bg-[length:12px_18px] opacity-45 group-hover/qbrow:opacity-80"></span>

        <div v-if="editingIndex === index" class="flex-1">
          <div class="grid grid-cols-2 gap-x-6 gap-y-4">
            <Field wide>
              <Label :for="`field-label-${index}`">{{ $t('formTemplates.labelField') }}</Label>
              <Input :id="`field-label-${index}`" v-model="field.label" type="text" maxlength="255" />
            </Field>

            <div class="col-span-full flex min-w-0 flex-col gap-1.5">
              <Label :for="`field-help-${index}`">{{ $t('formTemplates.helpField') }}</Label>
              <Textarea :id="`field-help-${index}`" v-model="field.helpText" rows="2" maxlength="500" />
            </div>

            <div v-if="field.type === 'select' || field.type === 'file' || field.type === 'group_photos'" class="col-span-full flex min-w-0 flex-col gap-1.5">
              <Label :for="`field-options-${index}`">{{ optionsLabel(field.type) }}</Label>
              <Textarea :id="`field-options-${index}`" v-model="field.optionsText" rows="3" />
              <span class="text-small leading-[1.45] text-faint">{{ optionsHint(field.type) }}</span>
            </div>

            <Field v-if="field.type === 'group_photos'">
              <Label :for="`field-minutes-${index}`">{{ $t('formTemplates.minutesField') }}</Label>
              <Input :id="`field-minutes-${index}`" v-model.number="field.minutesPerGroup" type="number" min="1" max="15" />
            </Field>

            <template v-if="field.type === 'file'">
              <Field>
                <Label :for="`field-max-size-${index}`">{{ $t('formTemplates.maxSizeField') }}</Label>
                <Input :id="`field-max-size-${index}`" v-model.number="field.fileMaxSize" type="number" min="1" :max="25" />
              </Field>

              <Field>
                <div class="flex items-center gap-2.5">
                  <Checkbox :id="`field-multiple-${index}`" v-model="field.fileMultiple" />
                  <Label variant="inline" :for="`field-multiple-${index}`">{{ $t('formTemplates.multipleField') }}</Label>
                </div>
              </Field>
            </template>

            <Field>
              <div class="flex items-center gap-2.5">
                <Checkbox :id="`field-required-${index}`" v-model="field.required" />
                <Label variant="inline" :for="`field-required-${index}`">{{ $t('formTemplates.requiredField') }}</Label>
              </div>
            </Field>
          </div>

          <div class="mt-4 flex items-center gap-2.5">
            <Button variant="outline" type="button" @click="editingIndex = null">{{ $t('formTemplates.done') }}</Button>
            <Button variant="outline" tone="accent" type="button" @click="removeField(index)">{{ $t('common.remove') }}</Button>
          </div>
        </div>

        <template v-else>
          <div class="min-w-0 flex-1">
            <div class="text-small leading-[1.4] font-medium">{{ field.label || $t('formTemplates.untitled') }}</div>
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
              <span class="block rounded-[5px] bg-muted px-[7px] py-0.5 text-small leading-[1.5] font-semibold text-muted-foreground">{{ typeLabel(field.type) }}</span>
              <span v-if="field.required" class="block rounded-[5px] bg-muted px-[7px] py-0.5 text-small leading-[1.5] font-semibold text-muted-foreground">{{ $t('formTemplates.requiredChip') }}</span>
              <span v-if="(field.type === 'select' || field.type === 'file' || field.type === 'group_photos') && field.optionsText" class="text-small leading-[1.5] text-faint">{{ field.optionsText.split('\n').filter(Boolean).join(', ') }}</span>
              <span v-if="field.type === 'name_list'" class="text-small leading-[1.5] text-faint">{{ $t('formTemplates.nameListHint') }}</span>
            </div>
            <div v-if="field.helpText" class="mt-1 truncate text-small leading-[1.5] text-faint">{{ field.helpText }}</div>
          </div>
          <Button as="a" variant="outline" href="#" @click.prevent="editingIndex = index">{{ $t('formTemplates.edit') }}</Button>
        </template>
      </div>
    </div>

    <div class="mt-4">
      <span class="mb-1.5 block">{{ $t('formTemplates.questionsLabel') }}</span>
      <div class="flex flex-wrap gap-1.5">
        <Button
          v-for="type in fieldTypes"
          :key="type.value"
          class="px-[11px] py-1.5 text-small leading-[1.4]" as="a"
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
