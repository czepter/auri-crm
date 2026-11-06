<script setup lang="ts">
import { reactive } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/crm/ProjectShell.vue'
import FormFieldsEditor from '@/admin/components/templates/FormFieldsEditor.vue'
import { toPayload, toRow } from '@/admin/lib/formFields'
import { useLiveProps } from '@/admin/lib/liveReload'
import GroupPhotoField from '@/portal/components/GroupPhotoField.vue'
import Card from '@/ui/page/Card.vue'
import { Button } from '@/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Switch } from '@/ui/switch'
import { Textarea } from '@/ui/textarea'
import Chip from '@/ui/page/Chip.vue'

const props = defineProps<{
  header: App.Crm.Screens.CaseHeaderData
  formInstances: App.Crm.Data.FormInstanceData[]
  formTemplates: { value: string; label: string }[]
  fieldTypes: { value: string; label: string }[]
}>()
useLiveProps(['header', 'formInstances', 'formTemplates'])

const { t } = useI18n()

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

const editing = reactive<Record<string, boolean>>({})

// One edit form per instance, keyed by its id — the same reason Quotes.vue
// keeps several drafts independent rather than sharing one form's state.
const editForms = Object.fromEntries(
  props.formInstances.map((instance) => [
    instance.id,
    useForm({
      name: instance.name,
      fields: instance.fields.map(toRow),
    }),
  ]),
)

function save(instanceId: string) {
  editForms[instanceId]
    .transform((data) => ({
      name: data.name,
      fields: data.fields.map(toPayload),
    }))
    .patch(`/frageboegen/${instanceId}`, {
      preserveScroll: true,
      onSuccess: () => (editing[instanceId] = false),
    })
}

// Eine Gruppenfotos-Antwort ist JSON. Das Studio liest sie hier als Liste;
// gebaut wird sie im Portal.
function shotPlan(field: App.Crm.Data.FormInstanceFieldData): { name: string; members: string[] }[] {
  if (field.type !== 'group_photos' || !field.currentAnswer) return []

  try {
    const groups = JSON.parse(field.currentAnswer).groups
    return Array.isArray(groups) ? groups : []
  } catch {
    return []
  }
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

function summary(instance: App.Crm.Data.FormInstanceData): string {
  const answered = instance.fields.filter((field) => field.answeredAt).length

  return answered === instance.fields.length && answered > 0
    ? t('formInstances.complete')
    : t('formInstances.progress', { answered, total: instance.fields.length })
}
</script>

<template>
  <AdminLayout :active="header.isBooked ? 'projekte' : 'anfragen'">
    <ProjectShell :header="header" active="documents" child="forms">
      <template v-if="header.canManageForms" #actions>
        <Button v-if="formTemplates.length === 1" variant="outline" type="button" @click="createFrom(formTemplates[0].value)">
          {{ $t('formInstances.send') }}
        </Button>
        <DropdownMenu v-else-if="formTemplates.length">
          <DropdownMenuTrigger as-child>
            <Button variant="outline" type="button">{{ $t('formInstances.send') }}</Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem v-for="template in formTemplates" :key="template.value" @select="createFrom(template.value)">
              {{ template.label }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <Button variant="outline" type="button" @click="createFrom('')">
          {{ $t('formInstances.startBlank') }}
        </Button>
      </template>

      <div class="flex flex-col gap-3">
        <Card
          v-for="instance in formInstances"
          :key="instance.id"
          v-model:editing="editing[instance.id]"
          :title="instance.name"
          :summary="summary(instance)"
          :editable="header.canManageForms"
          @cancel="editForms[instance.id].reset()"
          @save="save(instance.id)"
        >
          <template #action>
            <Chip :tone="instance.sharedAt ? 'success' : 'neutral'">
              {{ instance.sharedAt ? $t('formInstances.shared') : $t('formInstances.notShared') }}
            </Chip>
          </template>

          <!-- E-03 gilt auch am Telefon: ein unbeantwortetes Feld steht hier
               als sein Eingabefeld, damit das Studio nachträgt, was im
               Gespräch kam. -->
          <div class="grid grid-cols-2 gap-x-[18px] gap-y-4 max-[640px]:grid-cols-1">
            <Field
              v-for="field in instance.fields"
              :key="field.id"
              v-slot="{ id }"
              :label="field.label"
              :wide="field.type === 'group_photos'"
            >
              <template v-if="field.type === 'file'">
                <div v-for="file in field.files" :key="file.id" class="flex items-center gap-2.5 text-ui leading-[1.35]">
                  <span>{{ file.name }}</span>
                  <Button v-if="header.canManageForms" variant="ghost" class="text-small" type="button" @click="removeFile(file.id)">
                    {{ $t('common.remove') }}
                  </Button>
                </div>
                <input
                  v-if="header.canManageForms"
                  :id="id"
                  class="block text-small leading-[1.45]"
                  type="file"
                  :accept="accept(field)"
                  @change="uploadTo(field.id, $event)"
                />
              </template>

              <ol v-else-if="field.type === 'group_photos' && field.answeredAt" class="flex flex-col gap-0.5">
                <li v-for="(shot, index) in shotPlan(field)" :key="index" class="flex items-baseline gap-2.5 text-ui leading-[1.47]">
                  <span class="w-4 flex-none text-faint">{{ index + 1 }}</span>
                  <span class="grow">{{ shot.name }}</span>
                  <span class="flex-none text-small text-ink-2">{{ shot.members.join(', ') }}</span>
                </li>
              </ol>

              <div
                v-else-if="field.answeredAt && !header.canManageForms"
                class="text-ui leading-[1.47] whitespace-pre-line"
              >
                {{ field.currentAnswer }}
              </div>

              <template v-else-if="header.canManageForms">
                <GroupPhotoField
                  v-if="field.type === 'group_photos'"
                  v-model="answerForms[field.id].value"
                  :suggestions="field.groupPhotos?.suggestions ?? []"
                  :minutes-per-group="field.groupPhotos?.minutesPerGroup ?? 2"
                />
                <Button
                  v-if="field.type === 'group_photos'"
                  class="self-start"
                  variant="outline"
                  type="button"
                  :disabled="answerForms[field.id].processing"
                  @click="answer(field.id)"
                >
                  {{ $t('formInstances.saveAnswer') }}
                </Button>
                <Textarea v-else :id="id" v-model="answerForms[field.id].value" rows="2" @change="answer(field.id)" />
              </template>

              <span v-else class="text-ui leading-[1.47]">—</span>
            </Field>
          </div>

          <template #edit>
            <Field v-slot="{ id }" class="max-w-[280px]" :label="$t('formInstances.nameField')">
              <Input :id="id" v-model="editForms[instance.id].name" type="text" maxlength="255" />
            </Field>

            <div class="mt-4">
              <FormFieldsEditor v-model="editForms[instance.id].fields" :field-types="fieldTypes" />
            </div>

            <!-- P-03 (#222): sharing is what makes this fillable in the
                 portal. Un-sharing hides the screen and deletes nothing —
                 form_answers is append-only and stays that way. -->
            <div class="mt-4 flex items-center gap-2.5 pt-3.5 shadow-[inset_0_1px_0_var(--rule)]">
              <Switch
                :id="`share-${instance.id}`"
                :model-value="Boolean(instance.sharedAt)"
                @update:model-value="share(instance.id, $event)"
              />
              <Label variant="inline" :for="`share-${instance.id}`">{{ $t('formInstances.share') }}</Label>
            </div>

            <!-- Eine Frage ohne Titel weist der Server ab. Ohne diese Zeile
                 sah das Speichern aus, als täte es nichts. -->
            <p
              v-if="Object.keys(editForms[instance.id].errors).length"
              class="mt-2 text-small leading-[1.45] text-destructive"
            >{{ Object.values(editForms[instance.id].errors)[0] }}</p>
          </template>
        </Card>
      </div>

      <p v-if="!formInstances.length && !header.canManageForms" class="text-small leading-[1.45] text-faint">{{ $t('formInstances.empty') }}</p>
    </ProjectShell>
  </AdminLayout>
</template>
