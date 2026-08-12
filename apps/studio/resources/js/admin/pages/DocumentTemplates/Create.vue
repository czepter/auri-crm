<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import DocumentTemplatePreview from '@/admin/components/DocumentTemplatePreview.vue'
import { NONE, fromOption, toOption } from '@/lib/select'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'

defineProps<{
  types: { value: string; label: string }[]
  shootKinds: { value: string; label: string }[]
}>()

const form = useForm({ name: '', body: '', type: 'contract', shoot_kind: '' })

function submit() {
  form.transform((data) => ({ ...data, shoot_kind: data.shoot_kind || null })).post('/dokumentvorlagen')
}
</script>

<template>
  <AdminLayout active="dokumentvorlagen">
    <div style="max-width: 720px">
      <h1>{{ $t('documentTemplates.createTitle') }}</h1>

      <form style="margin-top: var(--s5)" @submit.prevent="submit">
        <div class="fld grp">
          <Label for="type">{{ $t('documentTemplates.type') }}</Label>
          <Select :model-value="form.type" @update:model-value="(value) => (form.type = String(value))">
            <SelectTrigger id="type">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="form.errors.type" class="err">{{ form.errors.type }}</p>
        </div>
        <div class="fld grp">
          <Label for="shoot_kind">{{ $t('documentTemplates.shootKind') }}</Label>
          <Select
            :model-value="toOption(form.shoot_kind)"
            @update:model-value="(value) => (form.shoot_kind = fromOption(value))"
          >
            <SelectTrigger id="shoot_kind">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="NONE">{{ $t('documentTemplates.shootKindAny') }}</SelectItem>
              <SelectItem v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="form.errors.shoot_kind" class="err">{{ form.errors.shoot_kind }}</p>
        </div>
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

        <DocumentTemplatePreview :type="form.type" :body="form.body" />

        <div class="formfoot">
          <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
          <Button as="a" href="/dokumentvorlagen" variant="ghost">{{ $t('common.cancel') }}</Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
