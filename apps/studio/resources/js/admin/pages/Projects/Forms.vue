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

    <span class="mb-4 text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('formInstances.title') }}</span>

    <div class="grid grid-cols-2 items-start gap-4">
      <div class="flex flex-col gap-6">
        <div v-for="instance in formInstances" :key="instance.id" class="relative block cursor-default rounded-lg border border-border p-2.5">
          <div class="mb-4 flex items-center justify-between">
            <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ instance.name }}</span>
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

          <p v-if="instance.sharedAt" class="text-[11.5px] leading-[1.45] text-faint">
            {{ $t('formInstances.sharedAt', { date: new Date(instance.sharedAt).toLocaleDateString('de-DE') }) }}
          </p>

          <template v-if="header.canManageForms">
            <div class="mb-2.5 flex min-w-0 flex-col gap-1.5">
              <Label :for="`form-name-${instance.id}`">{{ $t('formInstances.nameField') }}</Label>
              <Input :id="`form-name-${instance.id}`" v-model="editForms[instance.id].name" type="text" maxlength="255" />
            </div>

            <FormFieldsEditor v-model="editForms[instance.id].fields" :field-types="fieldTypes" />

            <div class="mt-6 flex items-center gap-4">
              <Button type="button" :disabled="editForms[instance.id].processing" @click="save(instance.id)">
                {{ $t('common.save') }}
              </Button>
            </div>

            <div v-if="instance.fields.length" class="mt-4">
              <div v-for="field in instance.fields" :key="field.id">
                <div class="text-[12.5px] leading-[1.35] text-muted-foreground">{{ field.label }}</div>
                <div class="flex min-w-0 flex-col gap-1.5">
                  <Textarea v-model="answerForms[field.id].value" rows="2" />
                </div>
                <div class="mt-1.5 flex items-center gap-4">
                  <Button variant="outline" type="button" :disabled="answerForms[field.id].processing" @click="answer(field.id)">
                    {{ $t('formInstances.saveAnswer') }}
                  </Button>
                  <span v-if="field.answeredAt" class="text-[11.5px] leading-[1.45] text-faint">
                    {{ $t('formInstances.answeredAt', { date: new Date(field.answeredAt).toLocaleDateString('de-DE') }) }}
                  </span>
                </div>
              </div>
            </div>
          </template>

          <span v-if="instance.sourceTemplateName" class="text-[11.5px] leading-[1.45] text-faint">
            {{ $t('formInstances.seededFrom', { name: instance.sourceTemplateName }) }}
          </span>
        </div>

        <div v-if="header.canManageForms" class="relative block cursor-default rounded-lg border border-border p-2.5">
          <div class="mb-4 flex items-center justify-between"><span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('formInstances.create') }}</span></div>
          <span class="mb-1.5 block">{{ $t('formInstances.fromTemplate') }}</span>
          <div class="flex flex-wrap gap-1.5">
            <Button as="a"
              class="px-[11px] py-1.5 text-[11.5px]" v-for="template in formTemplates"
              :key="template.value"
              href="#" variant="outline"
              @click.prevent="createFrom(template.value)"
            >
              {{ template.label }}
            </Button>
          </div>
          <span class="mt-4 mb-1.5 block">{{ $t('formInstances.orBlank') }}</span>
          <div class="flex flex-wrap gap-1.5">
            <Button as="a" class="px-[11px] py-1.5 text-[11.5px]" href="#" variant="outline" tone="accent" @click.prevent="createFrom('')">{{ $t('formInstances.startBlank') }}</Button>
          </div>
        </div>
      </div>
    </div>

    <p v-if="!formInstances.length && !header.canManageForms" class="text-[11.5px] leading-[1.45] text-faint">{{ $t('formInstances.empty') }}</p>
  </AdminLayout>
</template>
