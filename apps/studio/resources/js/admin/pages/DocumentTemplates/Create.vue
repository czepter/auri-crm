<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import DocumentTemplatePreview from '@/admin/components/DocumentTemplatePreview.vue'

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
          <label for="type">{{ $t('documentTemplates.type') }}</label>
          <select id="type" v-model="form.type" class="inp">
            <option v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <p v-if="form.errors.type" class="err">{{ form.errors.type }}</p>
        </div>
        <div class="fld grp">
          <label for="shoot_kind">{{ $t('documentTemplates.shootKind') }}</label>
          <select id="shoot_kind" v-model="form.shoot_kind" class="inp">
            <option value="">{{ $t('documentTemplates.shootKindAny') }}</option>
            <option v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
          </select>
          <p v-if="form.errors.shoot_kind" class="err">{{ form.errors.shoot_kind }}</p>
        </div>
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

        <DocumentTemplatePreview :type="form.type" :body="form.body" />

        <div class="formfoot">
          <button class="btn" type="submit" :disabled="form.processing">{{ $t('common.save') }}</button>
          <a href="/dokumentvorlagen" class="txtbtn">{{ $t('common.cancel') }}</a>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
