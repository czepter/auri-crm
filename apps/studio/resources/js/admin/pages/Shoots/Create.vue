<script setup lang="ts">
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import ConflictNote from '@/admin/components/ConflictNote.vue'
import ProjectTypeahead from '@/admin/components/ProjectTypeahead.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { TimePicker } from '@/ui/time-picker'

const props = defineProps<{
  project: App.Crm.Data.CaseSuggestionData | null
  kinds: string[]
  date: string | null
}>()

const project = ref(props.project)

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
  if (project.value) {
    form.transform((data) => ({ ...data, wizard: true })).post(`/auftraege/${project.value.id}/shootings`)
  }
}
</script>

<template>
  <AdminLayout active="uebersicht">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('create.kicker')" :title="$t('project.addShoot')" />

        <form class="flex max-w-[620px] flex-col gap-4 px-6 pt-7 pb-8" @submit.prevent="submit">
          <Field v-slot="{ id }" :label="$t('create.pick')">
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

          <Button class="mt-2 ml-0 self-start" type="submit" :disabled="!project || form.processing">
            {{ $t('project.addShoot') }}
          </Button>
        </form>
      </div>
    </Page>
  </AdminLayout>
</template>
