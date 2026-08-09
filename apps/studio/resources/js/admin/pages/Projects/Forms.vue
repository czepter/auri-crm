<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import FormFieldsEditor, { type FormFieldRow } from '@/admin/components/FormFieldsEditor.vue'

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  formInstances: App.Crm.Data.FormInstanceData[]
  formTemplates: { value: string; label: string }[]
  fieldTypes: { value: string; label: string }[]
}>()

// One "seed from" action per template button, plus the blank one — E-02's
// store() only ever needs the template's public_id (or none at all), so
// there is nothing else to collect before creating.
const createForm = useForm({ template: '' })

function createFrom(template: string) {
  createForm
    .transform(() => ({ template: template || null }))
    .post(`/auftraege/${props.header.id}/frageboegen`, { preserveScroll: true })
}

// One edit form per instance, keyed by its id — the same reason Quotes.vue
// keeps several drafts independent rather than sharing one form's state.
const editForms = Object.fromEntries(
  props.formInstances.map((instance) => [
    instance.id,
    useForm({
      name: instance.name,
      fields: instance.fields.map(
        (field): FormFieldRow => ({
          label: field.label,
          type: field.type,
          required: field.required,
          optionsText: (field.options ?? []).join('\n'),
        }),
      ),
    }),
  ]),
)

function save(instanceId: string) {
  editForms[instanceId]
    .transform((data) => ({
      name: data.name,
      fields: data.fields.map((field: FormFieldRow) => ({
        label: field.label,
        type: field.type,
        required: field.required,
        options: field.type === 'select' ? field.optionsText.split('\n').map((o) => o.trim()).filter(Boolean) : null,
      })),
    }))
    .patch(`/frageboegen/${instanceId}`, { preserveScroll: true })
}

// E-03: one answer form per field, keyed by its id — same reason editForms
// keeps one per instance. FormInstanceField::answer() never overwrites, so
// re-submitting the same field just appends the next version.
const answerForms = Object.fromEntries(
  props.formInstances.flatMap((instance) =>
    instance.fields.map((field) => [field.id, useForm({ value: field.currentAnswer ?? '' })]),
  ),
)

function answer(fieldId: string) {
  answerForms[fieldId].patch(`/frageboegen-felder/${fieldId}/antwort`, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectHeader :header="header" active="forms" />

    <span class="kick" style="margin-bottom: var(--s4)">{{ $t('formInstances.title') }}</span>

    <div class="cols2">
      <div class="stackcol">
        <div v-for="instance in formInstances" :key="instance.id" class="card static">
          <div class="cardhead">
            <span class="kick">{{ instance.name }}</span>
          </div>

          <template v-if="header.canManageForms">
            <div class="fld wide" style="margin-bottom: var(--s3)">
              <label :for="`form-name-${instance.id}`">{{ $t('formInstances.nameField') }}</label>
              <input :id="`form-name-${instance.id}`" v-model="editForms[instance.id].name" class="inp" type="text" maxlength="255" />
            </div>

            <FormFieldsEditor v-model="editForms[instance.id].fields" :field-types="fieldTypes" />

            <div class="formfoot">
              <button class="btn" type="button" :disabled="editForms[instance.id].processing" @click="save(instance.id)">
                {{ $t('common.save') }}
              </button>
            </div>

            <div v-if="instance.fields.length" class="qa" style="margin-top: var(--s4)">
              <div v-for="field in instance.fields" :key="field.id">
                <div class="q">{{ field.label }}</div>
                <div class="fld wide">
                  <textarea v-model="answerForms[field.id].value" class="inp" rows="2"></textarea>
                </div>
                <div class="formfoot" style="margin-top: var(--s2)">
                  <button class="abtn" type="button" :disabled="answerForms[field.id].processing" @click="answer(field.id)">
                    {{ $t('formInstances.saveAnswer') }}
                  </button>
                  <span v-if="field.answeredAt" class="hint">
                    {{ $t('formInstances.answeredAt', { date: new Date(field.answeredAt).toLocaleDateString('de-DE') }) }}
                  </span>
                </div>
              </div>
            </div>
          </template>

          <span v-if="instance.sourceTemplateName" class="hint">
            {{ $t('formInstances.seededFrom', { name: instance.sourceTemplateName }) }}
          </span>
        </div>

        <div v-if="header.canManageForms" class="card static">
          <div class="cardhead"><span class="kick">{{ $t('formInstances.create') }}</span></div>
          <span class="lab" style="display: block; margin-bottom: var(--s2)">{{ $t('formInstances.fromTemplate') }}</span>
          <div class="qpal">
            <a
              v-for="template in formTemplates"
              :key="template.value"
              href="#"
              class="abtn"
              @click.prevent="createFrom(template.value)"
            >
              {{ template.label }}
            </a>
          </div>
          <span class="lab" style="display: block; margin: var(--s4) 0 var(--s2)">{{ $t('formInstances.orBlank') }}</span>
          <div class="qpal">
            <a href="#" class="abtn hot" @click.prevent="createFrom('')">{{ $t('formInstances.startBlank') }}</a>
          </div>
        </div>
      </div>
    </div>

    <p v-if="!formInstances.length && !header.canManageForms" class="hint">{{ $t('formInstances.empty') }}</p>
  </AdminLayout>
</template>
