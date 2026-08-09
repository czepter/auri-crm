<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

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
          <label for="name">{{ $t('formTemplates.nameField') }}</label>
          <input id="name" v-model="form.name" class="inp" type="text" required maxlength="255" />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>
        <div class="fld grp">
          <label for="project-type">{{ $t('formTemplates.projectTypeField') }}</label>
          <select id="project-type" v-model="form.project_type" class="inp">
            <option value="">{{ $t('formTemplates.projectTypeAll') }}</option>
            <option v-for="type in projectTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
          </select>
        </div>

        <div class="formfoot">
          <button class="btn" type="submit" :disabled="form.processing">{{ $t('common.save') }}</button>
          <a href="/einstellungen/fragebogen-vorlagen" class="txtbtn">{{ $t('common.cancel') }}</a>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
