<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
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
  <AdminLayout active="einstellungen">
    <div style="max-width: 480px">
      <h1>{{ $t('formTemplates.createTitle') }}</h1>

      <form style="margin-top: var(--s5)" @submit.prevent="submit">
        <div class="fld grp">
          <Label for="name">{{ $t('formTemplates.nameField') }}</Label>
          <Input id="name" v-model="form.name" type="text" required maxlength="255" />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>
        <div class="fld grp">
          <Label for="project-type">{{ $t('formTemplates.projectTypeField') }}</Label>
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
        </div>

        <div class="formfoot">
          <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
          <Button as="a" href="/einstellungen/fragebogen-vorlagen" variant="ghost">{{ $t('common.cancel') }}</Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
