<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormFieldsEditor, { type FormFieldRow } from '@/admin/components/FormFieldsEditor.vue'

const { t } = useI18n()

const props = defineProps<{
  template: App.Crm.Data.FormTemplateData
  projectTypes: { value: string; label: string }[]
  fieldTypes: { value: string; label: string }[]
}>()

const form = useForm({
  name: props.template.name,
  project_type: props.template.projectType ?? '',
  is_active: props.template.isActive,
  fields: props.template.fields.map(
    (field): FormFieldRow => ({
      label: field.label,
      type: field.type,
      required: field.required,
      optionsText: (field.options ?? []).join('\n'),
    }),
  ),
})

const projectTypeLabel = computed(
  () => props.projectTypes.find((option) => option.value === form.project_type)?.label ?? t('formTemplates.projectTypeAll'),
)

const requiredCount = computed(() => form.fields.filter((f) => f.required).length)

function submit() {
  form
    .transform((data) => ({
      ...data,
      project_type: data.project_type || null,
      fields: data.fields.map((field) => ({
        label: field.label,
        type: field.type,
        required: field.required,
        options: field.type === 'select' ? field.optionsText.split('\n').map((o) => o.trim()).filter(Boolean) : null,
      })),
    }))
    .patch(`/einstellungen/fragebogen-vorlagen/${props.template.id}`)
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('formTemplates.kicker') }}</div>
        <h1>{{ form.name || $t('formTemplates.untitled') }}</h1>
      </div>
      <button class="btn" type="submit" form="form-template-form" :disabled="form.processing">
        {{ $t('formTemplates.save') }}
      </button>
    </div>

    <div class="meta row">
      <span class="q">{{ projectTypeLabel }}</span>
      <span class="d">{{ $t('formTemplates.updatedAt', { date: new Date(template.updatedAt).toLocaleDateString('de-DE') }) }}</span>
    </div>

    <form id="form-template-form" style="margin-top: var(--s5)" @submit.prevent="submit">
      <div class="split">
        <div class="stackcol">
          <FormFieldsEditor v-model="form.fields" :field-types="fieldTypes" />
        </div>

        <div class="stackcol">
          <div class="card static">
            <div class="cardhead">
              <span class="kick">{{ $t('formTemplates.fieldsKick') }}</span>
              <span class="chip" :class="form.is_active ? 'done' : 'off'">
                {{ form.is_active ? $t('formTemplates.active') : $t('formTemplates.inactive') }}
              </span>
            </div>
            <div class="kv">
              <div class="kvrow">
                <span>{{ $t('formTemplates.nameField') }}</span>
                <input v-model="form.name" class="inp" type="text" maxlength="255" style="text-align: right" />
              </div>
              <div class="kvrow">
                <span>{{ $t('formTemplates.projectTypeField') }}</span>
                <select v-model="form.project_type" class="inp">
                  <option value="">{{ $t('formTemplates.projectTypeAll') }}</option>
                  <option v-for="type in projectTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
              </div>
              <div class="kvrow">
                <span>{{ $t('formTemplates.activeField') }}</span>
                <input v-model="form.is_active" type="checkbox" />
              </div>
              <div class="kvrow">
                <span>{{ $t('formTemplates.fieldsLabel') }}</span>
                <span><b>{{ $t('formTemplates.fieldSummary', { count: form.fields.length, required: requiredCount }) }}</b></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </form>
  </AdminLayout>
</template>
