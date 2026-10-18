<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Field } from '@/ui/field'
import { NONE, fromOption, toOption } from '@/lib/select'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'

defineProps<{ projectTypes: { value: string; label: string }[] }>()

const form = useForm({
  name: '',
  project_type: '',
})

function submit() {
  form.transform((data) => ({ ...data, project_type: data.project_type || null })).post('/einstellungen/fragebogen-vorlagen')
}
</script>

<template>
  <AdminLayout active="vorlagen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('formTemplates.createTitle')" />

        <form class="max-w-[480px] px-6 pt-7 pb-8" @submit.prevent="submit">
          <Field id="name" class="mb-6" :label="$t('formTemplates.nameField')" :error="form.errors.name">
            <Input id="name" v-model="form.name" type="text" required maxlength="255" />
          </Field>
          <Field id="project-type" class="mb-6" :label="$t('formTemplates.projectTypeField')">
            <Select
              :model-value="toOption(form.project_type)"
              @update:model-value="(value) => (form.project_type = fromOption(value))"
            >
              <SelectTrigger id="project-type">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem :value="NONE">{{ $t('formTemplates.projectTypeAll') }}</SelectItem>
                <SelectItem v-for="type in projectTypes" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
              </SelectContent>
            </Select>
          </Field>

          <div class="mt-6 flex items-center gap-4">
            <Button type="submit" class="ml-0" :disabled="form.processing">{{ $t('common.save') }}</Button>
            <Button as="a" href="/vorlagen?typ=fragebogen" variant="ghost">{{ $t('common.cancel') }}</Button>
          </div>
        </form>
      </div>
    </Page>
  </AdminLayout>
</template>
