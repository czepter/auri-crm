<script setup lang="ts">
import { ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import CreateSummary from '@/admin/components/CreateSummary.vue'
import Card from '@/ui/page/Card.vue'
import ProjectTypeahead from '@/admin/components/crm/ProjectTypeahead.vue'
import FormFieldsEditor from '@/admin/components/templates/FormFieldsEditor.vue'
import { toPayload, toRow } from '@/admin/lib/formFields'
import { useLiveProps } from '@/admin/lib/liveReload'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Switch } from '@/ui/switch'
import Chip from '@/ui/page/Chip.vue'

const props = defineProps<{
  project: App.Crm.Data.CaseSuggestionData | null
  formTemplates: { value: string; label: string }[]
  fieldTypes: { value: string; label: string }[]
  instance: App.Crm.Data.FormInstanceData | null
}>()
useLiveProps([])

const project = ref(props.project)

watch(project, (chosen) => {
  if (chosen && !props.formTemplates.length) {
    return start('')
  }

  router.get('/frageboegen/neu', chosen ? { projekt: chosen.id } : {}, { preserveState: true, replace: true })
})

// Eine Vorlage wählen legt den Fragebogen an und kopiert ihre Fragen; was
// danach geändert wird, ändert nur diese Kopie.
function start(template: string) {
  if (project.value) {
    router.post(`/auftraege/${project.value.id}/frageboegen`, { template: template || null, wizard: true })
  }
}

const form = useForm({
  name: props.instance?.name ?? '',
  fields: (props.instance?.fields ?? []).map(toRow),
})

function save() {
  if (props.instance) {
    form
      .transform((data) => ({ name: data.name, fields: data.fields.map(toPayload) }))
      .patch(`/frageboegen/${props.instance.id}`)
  }
}

function share(shared: boolean) {
  if (props.instance) {
    router.post(`/frageboegen/${props.instance.id}/freigeben`, { shared })
  }
}

const LONG = ['text', 'name_list', 'group_photos']
</script>

<template>
  <AdminLayout active="projekte">
    <FormShell
      :kicker="$t('create.kickerForms')" :title="$t('formInstances.create')" wide
    >
      <template #head>
        <Chip v-if="instance" :tone="instance.sharedAt ? 'success' : 'neutral'">
          {{ instance.sharedAt ? $t('formInstances.shared') : $t('formInstances.notShared') }}
        </Chip>
      </template>

      <div>
        <Field v-slot="{ id }" class="max-w-[720px]" :label="$t('create.pick')">
          <ProjectTypeahead :id="id" v-model="project" />
        </Field>

        <Card v-if="project && !instance" class="mt-5 max-w-[720px]" :title="$t('formTemplates.starterTitle')">
          <div class="flex flex-col gap-4">
            <div>
              <span class="mb-1.5 block text-small">{{ $t('formInstances.fromTemplate') }}</span>
              <div class="flex flex-wrap gap-1.5">
                <Button v-for="template in formTemplates" :key="template.value" variant="outline" type="button" @click="start(template.value)">
                  {{ template.label }}
                </Button>
              </div>
            </div>
            <div>
              <span class="mb-1.5 block text-small">{{ $t('formInstances.orBlank') }}</span>
              <Button variant="outline" tone="accent" type="button" @click="start('')">{{ $t('formInstances.startBlank') }}</Button>
            </div>
          </div>
        </Card>

        <div v-if="project && instance" class="mt-5 flex gap-6">
          <form class="flex w-[720px] max-w-full flex-none flex-col gap-3" @submit.prevent="save">
            <Card :title="$t('formInstances.nameField')">
              <Input v-model="form.name" type="text" maxlength="255" />
              <p v-if="instance.sourceTemplateName" class="mt-1.5 text-small text-ink-2">
                {{ $t('formInstances.seededFrom', { name: instance.sourceTemplateName }) }}
              </p>
            </Card>

            <Card :title="$t('formTemplates.fieldsLabel')">
              <FormFieldsEditor v-model="form.fields" :field-types="fieldTypes" />

              <div class="mt-4 flex items-center gap-2.5 pt-3.5 border-t border-rule">
                <Switch id="form-share" :model-value="Boolean(instance.sharedAt)" @update:model-value="share" />
                <Label variant="inline" for="form-share">{{ $t('formInstances.share') }}</Label>
              </div>
            </Card>

            <Button class="ml-auto" type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
          </form>

          <!-- Was der Kunde im Portal sieht, aus dem Formular statt vom Server:
               jede Änderung an einer Frage steht sofort hier. -->
          <section class="min-w-0 max-w-[420px] flex-1">
            <div class="flex items-center gap-2.5 border-b border-border pb-2.5">
              <span class="text-small leading-none font-semibold text-faint">{{ $t('quotes.preview') }}</span>
            </div>

            <div class="mt-6 bg-sunk p-4">
              <div class="border border-rule bg-white px-[26px] py-7">
                <p class="text-[9px] leading-none font-semibold text-ink-2">{{ project.name }}</p>
                <h2 class="mt-2.5 mb-6 text-[28px] leading-[0.98] font-medium tracking-[-0.03em]">{{ form.name }}</h2>

                <template v-for="(field, index) in form.fields" :key="index">
                  <div v-if="field.type === 'section'" class="mb-4 border-b border-border pb-1.5">
                    <div class="text-small font-semibold">{{ field.label }}</div>
                    <div v-if="field.helpText" class="mt-0.5 text-small text-ink-2">{{ field.helpText }}</div>
                  </div>
                  <label v-else-if="field.type === 'boolean'" class="mb-4 flex items-start gap-2.5 text-small">
                    <Checkbox disabled />
                    <span>{{ field.label }}</span>
                  </label>
                  <div v-else class="mb-4">
                    <div class="mb-1.5 flex gap-1.5 text-small font-medium">
                      {{ field.label }}
                      <em v-if="!field.required" class="font-normal text-ink-2 not-italic">{{ $t('settings.optional') }}</em>
                    </div>
                    <div class="rounded-[9px] border border-line-hover" :class="LONG.includes(field.type) ? 'h-[54px]' : 'h-8'" />
                  </div>
                </template>

                <span class="inline-block rounded-[var(--r-pill)] bg-foreground px-5 py-2.5 text-small font-semibold text-background">
                  {{ $t('common.save') }}
                </span>
              </div>
            </div>
          </section>
        </div>
      </div>

      <template v-if="!instance" #aside>
        <CreateSummary
          :title="$t('common.summary')"
          :rows="[
            { label: $t('create.pick'), value: project?.name },
            { label: $t('leads.contact'), value: project?.contactName },
          ]"
        />
      </template>
    </FormShell>
  </AdminLayout>
</template>
