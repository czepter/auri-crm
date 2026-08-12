<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import DocumentTemplatePreview from '@/admin/components/DocumentTemplatePreview.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'

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
          <Label for="name">{{ $t('documentTemplates.name') }}</Label>
          <Input id="name" v-model="form.name" type="text" required />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>
        <div class="fld grp">
          <Label for="body">{{ $t('documentTemplates.body') }}</Label>
          <Textarea id="body" v-model="form.body" rows="16" required />
          <p class="hint">{{ $t('documentTemplates.bodyHint') }}</p>
          <p v-if="form.errors.body" class="err">{{ form.errors.body }}</p>
        </div>

        <DocumentTemplatePreview :type="template.type" :body="form.body" />

        <div class="formfoot">
          <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
          <Button as="a" href="/dokumentvorlagen" variant="ghost">{{ $t('common.cancel') }}</Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
