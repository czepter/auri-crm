<script setup lang="ts">
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import ProjectTypeahead from '@/admin/components/ProjectTypeahead.vue'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { DatePicker } from '@/ui/date-picker'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { TimePicker } from '@/ui/time-picker'

const props = defineProps<{
  project: App.Crm.Data.ProjectSuggestionData | null
  kinds: { value: string; label: string }[]
  conflicts: { label: string; href: string }[]
}>()

const project = ref(props.project)

const form = useForm({
  kind: 'wedding',
  label: '',
  date: '',
  call_time: '',
  end_time: '',
  venue_name: '',
  venue_city: '',
})

function submit() {
  if (project.value) {
    form.post(`/auftraege/${project.value.id}/shootings`)
  }
}
</script>

<template>
  <AdminLayout active="uebersicht">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('create.kicker')" :title="$t('project.addShoot')" />

        <form class="flex max-w-[620px] flex-col gap-4 px-8 pt-7 pb-8" @submit.prevent="submit">
          <Field v-slot="{ id }" :label="$t('create.pick')">
            <ProjectTypeahead :id="id" v-model="project" />
          </Field>

          <template v-if="project">
            <Field :label="$t('project.shootKind')">
              <ChoiceGroup v-model="form.kind" :options="kinds" />
            </Field>

            <Field v-slot="{ id }" :label="$t('project.shootLabel')">
              <Input :id="id" v-model="form.label" type="text" />
            </Field>

            <Field v-slot="{ id }" :label="$t('project.shootDate')">
              <DatePicker :id="id" v-model="form.date" />
              <Link
                v-for="conflict in conflicts"
                :key="conflict.href"
                class="flex items-center self-start rounded-[7px] bg-late-bg bg-icon-alert bg-[length:13px_13px] bg-[position:9px_center] bg-no-repeat py-[7px] pr-[9px] pl-7 text-[11.5px] leading-[1.3] font-medium text-action no-underline hover:underline"
                :href="conflict.href"
              >
                {{ conflict.label }}
              </Link>
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
