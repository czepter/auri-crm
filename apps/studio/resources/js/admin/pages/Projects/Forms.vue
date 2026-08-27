<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import FormFieldsEditor, { type FormFieldRow } from '@/admin/components/FormFieldsEditor.vue'
import Section from '@/ui/page/Section.vue'
import { dayAndMonth } from '@/admin/lib/shoots'
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

// Which questionnaire is open for editing, at most one at a time: the section
// reads as answers until someone works on its questions.
const editing = ref<string | null>(null)

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
          optionsText: (field.type === 'file' ? field.fileFormats ?? [] : field.options ?? []).join('\n'),
          fileMaxSize: field.fileMaxSize ?? 10,
          fileMultiple: field.fileMultiple,
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
        options: fieldOptions(field),
      })),
    }))
    .patch(`/frageboegen/${instanceId}`, {
      preserveScroll: true,
      onSuccess: () => (editing.value = null),
    })
}

// select stores its choices and file its allowed formats in the one options
// column — see FormFieldType::optionRules().
function fieldOptions(field: FormFieldRow) {
  const list = field.optionsText.split('\n').map((o) => o.trim()).filter(Boolean)

  if (field.type === 'file') {
    return { formats: list, maxSize: field.fileMaxSize ?? 10, multiple: field.fileMultiple ?? false }
  }

  return field.type === 'select' ? list : null
}

function uploadTo(fieldId: string, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  router.post(`/frageboegen-felder/${fieldId}/datei`, { file }, {
    preserveScroll: true,
    onFinish: () => (input.value = ''),
  })
}

function removeFile(fileId: string) {
  router.delete(`/fragebogen-dateien/${fileId}`, { preserveScroll: true })
}

function accept(field: App.Crm.Data.FormInstanceFieldData): string {
  return (field.fileFormats ?? []).map((f) => `.${f}`).join(',')
}

// E-03: one answer form per field, keyed by its id — same reason editForms
// keeps one per instance. FormInstanceField::answer() never overwrites, so
// re-submitting the same field just appends the next version.
const answerForms = Object.fromEntries(
  props.formInstances.flatMap((instance) =>
    instance.fields
      .filter((field) => field.type !== 'file')
      .map((field) => [field.id, useForm({ value: field.currentAnswer ?? '' })]),
  ),
)

function answer(fieldId: string) {
  answerForms[fieldId].patch(`/frageboegen-felder/${fieldId}/antwort`, { preserveScroll: true })
}

function answered(instance: App.Crm.Data.FormInstanceData): number {
  return instance.fields.filter((field) => field.answeredAt).length
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectShell :header="header" active="forms">
      <Section v-for="instance in formInstances" :key="instance.id" :title="instance.name">
        <template #actions>
          <span
            v-if="instance.fields.length"
            :class="[
              'inline-flex items-center rounded-xl px-[7px] py-0.5 text-[10.5px] leading-[1.5] font-semibold tracking-[0.02em]',
              answered(instance) === instance.fields.length ? 'bg-ok-bg text-ok' : 'bg-due-bg text-due',
            ]"
          >
            {{ answered(instance) === instance.fields.length
              ? $t('formInstances.complete')
              : $t('formInstances.progress', { answered: answered(instance), total: instance.fields.length }) }}
          </span>

          <span v-if="instance.sharedAt" class="text-[11.5px] leading-[1.45] text-ink-2">
            {{ $t('formInstances.sharedAt', { date: new Date(instance.sharedAt).toLocaleDateString('de-DE') }) }}
          </span>
          <span v-else-if="instance.sourceTemplateName" class="text-[11.5px] leading-[1.45] text-ink-2">
            {{ $t('formInstances.seededFrom', { name: instance.sourceTemplateName }) }}
          </span>

          <div v-if="header.canManageForms" class="ml-auto flex flex-wrap gap-1.5">
            <Button
              variant="outline"
              type="button"
              @click="editing = editing === instance.id ? null : instance.id"
            >
              {{ editing === instance.id ? $t('formTemplates.done') : $t('formTemplates.edit') }}
            </Button>
            <!-- P-03 (#222): sharing is what makes this fillable in the
                 portal. Un-sharing hides the screen and deletes nothing —
                 form_answers is append-only and stays that way. -->
            <Button variant="outline" type="button" @click="share(instance.id, !instance.sharedAt)">
              {{ instance.sharedAt ? $t('formInstances.unshare') : $t('formInstances.share') }}
            </Button>
          </div>
        </template>

        <template v-if="header.canManageForms && editing === instance.id">
          <div class="mb-2.5 flex min-w-0 flex-col gap-1.5">
            <Label :for="`form-name-${instance.id}`">{{ $t('formInstances.nameField') }}</Label>
            <Input :id="`form-name-${instance.id}`" v-model="editForms[instance.id].name" type="text" maxlength="255" />
          </div>

          <FormFieldsEditor v-model="editForms[instance.id].fields" :field-types="fieldTypes" />

          <div class="mt-4 flex items-center gap-4">
            <Button type="button" :disabled="editForms[instance.id].processing" @click="save(instance.id)">
              {{ $t('common.save') }}
            </Button>
          </div>

          <div v-for="field in instance.fields" :key="field.id" class="mt-4">
            <div class="text-[12.5px] leading-[1.35] text-ink-2">{{ field.label }}</div>

            <template v-if="field.type === 'file'">
              <div v-for="file in field.files" :key="file.id" class="mt-1.5 flex items-center gap-2.5">
                <span class="text-[13px] leading-[1.4]">{{ file.name }}</span>
                <Button variant="outline" tone="accent" type="button" @click="removeFile(file.id)">{{ $t('common.remove') }}</Button>
              </div>
              <input
                :id="`file-${field.id}`"
                class="mt-1.5 block text-[12.5px] leading-[1.45]"
                type="file"
                :accept="accept(field)"
                @change="uploadTo(field.id, $event)"
              />
            </template>

            <template v-else>
              <Textarea v-model="answerForms[field.id].value" rows="2" class="mt-1.5" />
              <div class="mt-1.5 flex items-center gap-4">
                <Button variant="outline" type="button" :disabled="answerForms[field.id].processing" @click="answer(field.id)">
                  {{ $t('formInstances.saveAnswer') }}
                </Button>
                <span v-if="field.answeredAt" class="text-[11.5px] leading-[1.45] text-faint">
                  {{ $t('formInstances.answeredAt', { date: new Date(field.answeredAt).toLocaleDateString('de-DE') }) }}
                </span>
              </div>
            </template>
          </div>
        </template>

        <template v-else>
          <div v-for="field in instance.fields" :key="field.id" class="py-[9px]">
            <div class="flex items-baseline gap-2.5">
              <span class="text-[12.5px] leading-[1.4] text-ink-2">{{ field.label }}</span>
              <span v-if="field.answeredAt" class="ml-auto text-[11.5px] leading-[1.45] text-ink-2">{{ dayAndMonth(field.answeredAt) }}</span>
              <span v-else class="ml-auto text-[11.5px] leading-[1.45] text-faint">{{ $t('formInstances.open') }}</span>
            </div>
            <div v-if="field.currentAnswer" class="mt-0.5 text-[13px] leading-[1.47] whitespace-pre-line">{{ field.currentAnswer }}</div>
            <div v-for="file in field.files" :key="file.id" class="mt-0.5 text-[13px] leading-[1.47]">{{ file.name }}</div>
          </div>
        </template>
      </Section>

      <Section v-if="header.canManageForms" :title="$t('formInstances.create')">
        <div class="flex flex-wrap gap-1.5">
          <Button
            v-for="template in formTemplates"
            :key="template.value"
            as="a"
            href="#"
            variant="outline"
            @click.prevent="createFrom(template.value)"
          >
            {{ template.label }}
          </Button>
          <Button as="a" href="#" variant="outline" tone="accent" @click.prevent="createFrom('')">
            {{ $t('formInstances.startBlank') }}
          </Button>
        </div>
      </Section>

      <p v-if="!formInstances.length && !header.canManageForms" class="text-[11.5px] leading-[1.45] text-faint">{{ $t('formInstances.empty') }}</p>
    </ProjectShell>
  </AdminLayout>
</template>
