<script setup lang="ts">
import { ref } from 'vue'

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
              <label :for="`field-label-${index}`">{{ $t('formTemplates.labelField') }}</label>
              <input :id="`field-label-${index}`" v-model="field.label" class="inp" type="text" maxlength="255" />
            </div>

            <div v-if="field.type === 'select'" class="fld wide">
              <label :for="`field-options-${index}`">{{ $t('formTemplates.optionsField') }}</label>
              <textarea :id="`field-options-${index}`" v-model="field.optionsText" class="inp" rows="3"></textarea>
              <span class="hint">{{ $t('formTemplates.optionsHint') }}</span>
            </div>

            <div class="fld">
              <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
                <input v-model="field.required" type="checkbox" />
                {{ $t('formTemplates.requiredField') }}
              </label>
            </div>
          </div>

          <div class="formfoot">
            <button type="button" class="abtn" @click="editingIndex = null">{{ $t('formTemplates.done') }}</button>
            <button type="button" class="abtn hot" @click="removeField(index)">{{ $t('common.remove') }}</button>
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
          <a href="#" class="abtn" @click.prevent="editingIndex = index">{{ $t('formTemplates.edit') }}</a>
        </div>
      </div>
    </div>

    <div style="margin-top: var(--s4)">
      <span class="lab" style="display: block; margin-bottom: var(--s2)">{{ $t('formTemplates.questionsLabel') }}</span>
      <div class="qpal">
        <a v-for="type in fieldTypes" :key="type.value" href="#" class="abtn" @click.prevent="addField(type.value)">
          {{ type.label }}
        </a>
      </div>
    </div>
  </div>
</template>
