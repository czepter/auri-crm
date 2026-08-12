<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import DocumentEditor from '@/admin/components/DocumentEditor.vue'
import DocumentPreviewPane from '@/admin/components/DocumentPreviewPane.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { serialize } from '@/admin/documents/blocks/serialize'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

const props = defineProps<{
  types: { value: string; label: string }[]
  shootKinds: { value: string; label: string }[]
  palettes: Record<string, PaletteEntry[]>
  mergeFields: { path: string; sample: string }[]
}>()

const doc = ref<EditorDocument>({ blocks: [] })
const form = useForm({ name: '', body: '', blocks: doc.value, type: 'contract', shoot_kind: '' })

const palette = computed(() => props.palettes[form.type] ?? [])
const samples = computed(() =>
  Object.fromEntries(props.mergeFields.map((field) => [field.path, field.sample])),
)

function submit() {
  form.body = serialize(doc.value, form.type === 'mail' ? 'mail' : 'print')
  form.blocks = doc.value
  form.transform((data) => ({ ...data, shoot_kind: data.shoot_kind || null })).post('/dokumentvorlagen')
}
</script>

<template>
  <AdminLayout active="dokumentvorlagen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('documentTemplates.title') }}</div>
        <h1>{{ $t('documentTemplates.createTitle') }}</h1>
      </div>
      <Button as="a" href="/dokumentvorlagen" variant="ghost">{{ $t('common.cancel') }}</Button>
      <Button type="button" :disabled="form.processing" @click="submit">{{ $t('common.save') }}</Button>
    </div>
    <p v-if="form.errors.blocks" class="err">{{ form.errors.blocks }}</p>
    <p v-if="form.errors.body" class="err">{{ form.errors.body }}</p>

    <div class="doc-meta">
      <div class="fld">
        <Label for="type">{{ $t('documentTemplates.type') }}</Label>
        <Select :model-value="form.type" @update:model-value="(value) => (form.type = String(value))">
          <SelectTrigger id="type"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="form.errors.type" class="err">{{ form.errors.type }}</p>
      </div>
      <div class="fld">
        <Label for="shoot_kind">{{ $t('documentTemplates.shootKind') }}</Label>
        <Select :model-value="form.shoot_kind" @update:model-value="(value) => (form.shoot_kind = String(value))">
          <SelectTrigger id="shoot_kind"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="">{{ $t('documentTemplates.shootKindAny') }}</SelectItem>
            <SelectItem v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="fld">
        <Label for="name">{{ $t('documentTemplates.name') }}</Label>
        <Input id="name" v-model="form.name" type="text" required />
        <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
      </div>
    </div>

    <div class="doc-split">
      <section class="doc-pane">
        <div class="doc-panehead">
          <span class="kick">{{ $t('documentTemplates.body') }}</span>
          <span class="hint">{{ $t('documentTemplates.bodyHint') }}</span>
        </div>
        <div class="doc-sheet">
          <div class="doc-chrome">{{ $t('documentTemplates.letterhead') }}</div>
          <DocumentEditor v-model="doc" :palette="palette" :merge-fields="mergeFields" />
          <div class="doc-chrome foot">{{ $t('documentTemplates.footer') }}</div>
        </div>
      </section>

      <div class="doc-splitbar"></div>

      <DocumentPreviewPane :type="form.type" :doc="doc" :samples="samples" />
    </div>
  </AdminLayout>
</template>
