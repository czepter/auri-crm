<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import FormFieldsEditor, { type FormFieldRow } from '@/admin/components/FormFieldsEditor.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'

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

function share(id: string, shared: boolean) {
  router.post(`/frageboegen/${id}/freigeben`, { shared }, { preserveScroll: true })
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
            <!-- P-03 (#222): sharing is what makes this fillable in the
                 portal. Un-sharing hides the screen and deletes nothing —
                 form_answers is append-only and stays that way. -->
            <Button
              v-if="header.canManageForms"
              variant="ghost"
              type="button"
              @click="share(instance.id, !instance.sharedAt)"
            >
              {{ instance.sharedAt ? $t('formInstances.unshare') : $t('formInstances.share') }}
            </Button>
          </div>

          <p v-if="instance.sharedAt" class="hint">
            {{ $t('formInstances.sharedAt', { date: new Date(instance.sharedAt).toLocaleDateString('de-DE') }) }}
          </p>

          <template v-if="header.canManageForms">
            <div class="fld wide" style="margin-bottom: var(--s3)">
              <Label :for="`form-name-${instance.id}`">{{ $t('formInstances.nameField') }}</Label>
              <Input :id="`form-name-${instance.id}`" v-model="editForms[instance.id].name" type="text" maxlength="255" />
            </div>

            <FormFieldsEditor v-model="editForms[instance.id].fields" :field-types="fieldTypes" />

            <div class="formfoot">
              <Button type="button" :disabled="editForms[instance.id].processing" @click="save(instance.id)">
                {{ $t('common.save') }}
              </Button>
            </div>

            <div v-if="instance.fields.length" class="qa" style="margin-top: var(--s4)">
              <div v-for="field in instance.fields" :key="field.id">
                <div class="q">{{ field.label }}</div>
                <div class="fld wide">
                  <Textarea v-model="answerForms[field.id].value" rows="2" />
                </div>
                <div class="formfoot" style="margin-top: var(--s2)">
                  <Button variant="outline" type="button" :disabled="answerForms[field.id].processing" @click="answer(field.id)">
                    {{ $t('formInstances.saveAnswer') }}
                  </Button>
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
            <Button as="a"
              v-for="template in formTemplates"
              :key="template.value"
              href="#" variant="outline"
              @click.prevent="createFrom(template.value)"
            >
              {{ template.label }}
            </Button>
          </div>
          <span class="lab" style="display: block; margin: var(--s4) 0 var(--s2)">{{ $t('formInstances.orBlank') }}</span>
          <div class="qpal">
            <Button as="a" href="#" variant="outline" tone="accent" @click.prevent="createFrom('')">{{ $t('formInstances.startBlank') }}</Button>
          </div>
        </div>
      </div>
    </div>

    <p v-if="!formInstances.length && !header.canManageForms" class="hint">{{ $t('formInstances.empty') }}</p>
  </AdminLayout>
</template>
