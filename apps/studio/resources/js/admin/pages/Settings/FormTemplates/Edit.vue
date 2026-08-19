<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormFieldsEditor, { type FormFieldRow } from '@/admin/components/FormFieldsEditor.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Field } from '@/ui/field'
import { NONE, fromOption, toOption } from '@/lib/select'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'

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
    <div class="flex items-center gap-4">
      <div>
        <div class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('formTemplates.kicker') }}</div>
        <h1>{{ form.name || $t('formTemplates.untitled') }}</h1>
      </div>
      <Button type="submit" form="form-template-form" :disabled="form.processing">
        {{ $t('formTemplates.save') }}
      </Button>
    </div>

    <div class="mt-2.5 flex flex-row gap-6">
      <span class="inline-flex items-center bg-left bg-no-repeat bg-[length:13px_13px] pl-[19px] text-[12.5px] leading-[1.35] font-normal text-muted-foreground bg-icon-kind">{{ projectTypeLabel }}</span>
      <span class="inline-flex items-center bg-left bg-no-repeat bg-[length:13px_13px] pl-[19px] text-[12.5px] leading-[1.35] font-normal text-muted-foreground bg-icon-date">{{ $t('formTemplates.updatedAt', { date: new Date(template.updatedAt).toLocaleDateString('de-DE') }) }}</span>
    </div>

    <form id="form-template-form" style="margin-top: var(--s5)" @submit.prevent="submit">
      <div class="grid grid-cols-[minmax(0,1fr)_296px] items-start gap-8">
        <div class="flex flex-col gap-6">
          <FormFieldsEditor v-model="form.fields" :field-types="fieldTypes" />
        </div>

        <div class="flex flex-col gap-6">
          <div class="relative block cursor-default rounded-lg border border-border p-2.5">
            <div class="mb-4 flex items-center justify-between">
              <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('formTemplates.fieldsKick') }}</span>
              <span class="flex-none rounded-[5px] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase" :class="form.is_active ? 'bg-ok-bg text-ok' : 'bg-muted text-faint'">
                {{ form.is_active ? $t('formTemplates.active') : $t('formTemplates.inactive') }}
              </span>
            </div>
            <div class="grid grid-cols-2 gap-x-6 gap-y-4">
              <Field wide>
                <Label for="ft-name">{{ $t('formTemplates.nameField') }}</Label>
                <Input id="ft-name" v-model="form.name" type="text" maxlength="255" />
              </Field>
              <Field wide>
                <Label for="ft-project-type">{{ $t('formTemplates.projectTypeField') }}</Label>
                <Select
                  :model-value="toOption(form.project_type)"
                  @update:model-value="(value) => (form.project_type = fromOption(value))"
                >
                  <SelectTrigger id="ft-project-type">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="NONE">{{ $t('formTemplates.projectTypeAll') }}</SelectItem>
                    <SelectItem v-for="type in projectTypes" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
              <div class="cbrow" style="grid-column: 1 / -1">
                <Checkbox id="ft-active" v-model="form.is_active" />
                <Label variant="inline" for="ft-active">{{ $t('formTemplates.activeField') }}</Label>
              </div>
              <Field wide>
                <Label>{{ $t('formTemplates.fieldsLabel') }}</Label>
                <span><b>{{ $t('formTemplates.fieldSummary', { count: form.fields.length, required: requiredCount }) }}</b></span>
              </Field>
            </div>
          </div>
        </div>
      </div>
    </form>
  </AdminLayout>
</template>
