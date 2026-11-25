<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import CreateSummary from '@/admin/components/CreateSummary.vue'
import ConflictNote from '@/admin/components/crm/ConflictNote.vue'
import ProjectTypeahead from '@/admin/components/crm/ProjectTypeahead.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { TimePicker } from '@/ui/time-picker'
import { focusFirstError } from '@/admin/lib/focusInvalid'
import { shortDate } from '@/admin/lib/dates'

const props = defineProps<{
  project: App.Crm.Data.CaseSuggestionData | null
  kinds: string[]
  date: string | null
}>()

const { t } = useI18n()
const project = ref(props.project)
const unpicked = ref(false)

const form = useForm({
  kind: '',
  label: '',
  date: props.date ?? '',
  call_time: '',
  end_time: '',
  venue_name: '',
  venue_city: '',
})

// Wie bei der Erfassung einer Anfrage: der Tag wird geprüft, sobald er steht.
const conflicts = ref<App.Crm.Data.DateConflictData[]>([])

watch(
  () => form.date,
  async (date) => {
    if (!date) {
      conflicts.value = []
      return
    }

    const response = await fetch(`/verfuegbarkeit?datum=${date}`, { headers: { Accept: 'application/json' } })
    conflicts.value = (await response.json()).conflicts
  },
  { immediate: true },
)

function submit() {
  if (!project.value) {
    unpicked.value = true
    nextTick(focusFirstError)
    return
  }

  form.transform((data) => ({ ...data, wizard: true })).post(`/auftraege/${project.value.id}/shootings`)
}
</script>

<template>
  <AdminLayout active="kalender">
    <FormShell
      :kicker="$t('create.kickerShoots')" :title="$t('project.addShoot')" wide
    >
      <form class="flex flex-col gap-4" @submit.prevent="submit">
        <Field v-slot="{ id }" :label="$t('create.pick')" :error="unpicked && !project ? t('common.invalid.valueMissing') : undefined">
          <ProjectTypeahead :id="id" v-model="project" />
        </Field>

        <template v-if="project">
          <Field v-slot="{ id }" :label="$t('project.shootKind')">
            <Input :id="id" v-model="form.kind" type="text" maxlength="255" list="shoot-kinds" />
            <datalist id="shoot-kinds">
              <option v-for="kind in kinds" :key="kind" :value="kind" />
            </datalist>
          </Field>

          <Field v-slot="{ id }" :label="$t('project.shootLabel')">
            <Input :id="id" v-model="form.label" type="text" />
          </Field>

          <Field v-slot="{ id }" :label="$t('project.shootDate')">
            <DatePicker :id="id" v-model="form.date" />
            <ConflictNote :conflicts="conflicts" />
          </Field>

          <div class="grid grid-cols-2 gap-4">
            <Field v-slot="{ id }" :label="$t('project.callTime')">
              <TimePicker :id="id" v-model="form.call_time" />
            </Field>
            <Field v-slot="{ id }" :label="$t('project.endTime')" :error="form.errors.end_time">
              <TimePicker :id="id" v-model="form.end_time" />
            </Field>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <Field v-slot="{ id }" :label="$t('project.venueName')">
              <Input :id="id" v-model="form.venue_name" type="text" />
            </Field>
            <Field v-slot="{ id }" :label="$t('project.venueCity')">
              <Input :id="id" v-model="form.venue_city" type="text" />
            </Field>
          </div>
        </template>

        <Button class="mt-2 ml-0 self-start" type="submit" :disabled="form.processing">
          {{ $t('project.addShoot') }}
        </Button>
      </form>

      <template #aside>
        <CreateSummary
          :title="$t('common.summary')"
          :rows="[
            { label: $t('create.pick'), value: project?.name },
            { label: $t('project.shootKind'), value: form.kind },
            { label: $t('project.shootLabel'), value: form.label },
            { label: $t('project.shootDate'), value: form.date ? shortDate(form.date) : null },
            { label: $t('project.callTime'), value: form.call_time },
            { label: $t('project.endTime'), value: form.end_time },
            { label: $t('project.venueName'), value: [form.venue_name, form.venue_city].filter(Boolean).join(', ') },
          ]"
        />
      </template>
    </FormShell>
  </AdminLayout>
</template>
