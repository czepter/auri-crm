<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
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
    <div class="qb">
      <div v-for="(field, index) in fields" :key="index" class="qbrow" :class="{ editing: editingIndex === index }">
        <span class="grip"></span>

        <div v-if="editingIndex === index" class="qbody">
          <div class="fgrid grp">
            <div class="fld wide">
              <Label :for="`field-label-${index}`">{{ $t('formTemplates.labelField') }}</Label>
              <Input :id="`field-label-${index}`" v-model="field.label" type="text" maxlength="255" />
            </div>

            <div v-if="field.type === 'select'" class="fld wide">
              <Label :for="`field-options-${index}`">{{ $t('formTemplates.optionsField') }}</Label>
              <Textarea :id="`field-options-${index}`" v-model="field.optionsText" rows="3" />
              <span class="hint">{{ $t('formTemplates.optionsHint') }}</span>
            </div>

            <div class="fld">
              <div class="cbrow">
                <Checkbox :id="`field-required-${index}`" v-model="field.required" />
                <Label variant="inline" :for="`field-required-${index}`">{{ $t('formTemplates.requiredField') }}</Label>
              </div>
            </div>
          </div>

          <div class="formfoot">
            <Button variant="outline" type="button" @click="editingIndex = null">{{ $t('formTemplates.done') }}</Button>
            <Button variant="outline" tone="accent" type="button" @click="removeField(index)">{{ $t('common.remove') }}</Button>
          </div>
        </div>

        <div v-else class="qbody">
          <div class="qlabel">{{ field.label || $t('formTemplates.untitled') }}</div>
          <div class="qmeta">
            <span class="qtype">{{ typeLabel(field.type) }}</span>
            <span v-if="field.required" class="qreq">{{ $t('formTemplates.requiredChip') }}</span>
            <span v-if="field.type === 'select' && field.optionsText" class="qopts">{{ field.optionsText.split('\n').filter(Boolean).join(', ') }}</span>
            <span v-if="field.type === 'name_list'" class="qopts">{{ $t('formTemplates.nameListHint') }}</span>
          </div>
          <Button as="a" variant="outline" href="#" @click.prevent="editingIndex = index">{{ $t('formTemplates.edit') }}</Button>
        </div>
      </div>
    </div>

    <div style="margin-top: var(--s4)">
      <span class="lab" style="display: block; margin-bottom: var(--s2)">{{ $t('formTemplates.questionsLabel') }}</span>
      <div class="qpal">
        <Button
          v-for="type in fieldTypes"
          :key="type.value"
          as="a"
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
