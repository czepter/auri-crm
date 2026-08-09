<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import DocumentTemplatePreview from '@/admin/components/DocumentTemplatePreview.vue'

const props = defineProps<{ template: App.Documents.Data.DocumentTemplateData }>()

const form = useForm({ name: props.template.name, body: props.template.body })

function submit() {
  form.patch(`/dokumentvorlagen/${props.template.id}`)
}
</script>

<template>
  <AdminLayout active="dokumentvorlagen">
    <div style="max-width: 720px">
      <h1>{{ $t('documentTemplates.editTitle') }}</h1>
      <p class="hint">
        {{ template.typeLabel }}<template v-if="template.shootKindLabel"> · {{ template.shootKindLabel }}</template>
      </p>
      <p class="hint">{{ $t('documentTemplates.editHint') }}</p>

      <form style="margin-top: var(--s5)" @submit.prevent="submit">
        <div class="fld grp">
          <label for="name">{{ $t('documentTemplates.name') }}</label>
          <input id="name" v-model="form.name" class="inp" type="text" required />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>
        <div class="fld grp">
          <label for="body">{{ $t('documentTemplates.body') }}</label>
          <textarea id="body" v-model="form.body" class="inp" rows="16" required />
          <p class="hint">{{ $t('documentTemplates.bodyHint') }}</p>
          <p v-if="form.errors.body" class="err">{{ form.errors.body }}</p>
        </div>

        <DocumentTemplatePreview :type="template.type" :body="form.body" />

        <div class="formfoot">
          <button class="btn" type="submit" :disabled="form.processing">{{ $t('common.save') }}</button>
          <a href="/dokumentvorlagen" class="txtbtn">{{ $t('common.cancel') }}</a>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
