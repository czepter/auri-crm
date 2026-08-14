<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import DocumentEditor from '@/admin/components/DocumentEditor.vue'
import DocumentPreviewPane from '@/admin/components/DocumentPreviewPane.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { htmlToBlocks } from '@/admin/documents/blocks/importHtml'
import { serialize } from '@/admin/documents/blocks/serialize'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

const props = defineProps<{
  template: App.Documents.Data.DocumentTemplateData
  palette: PaletteEntry[]
  mergeFields: { path: string; sample: string }[]
}>()

// A template written before the block editor has blocks = null; its body
// becomes one rawHtml block the author can keep or replace. The import is
// only persisted if they save.
const doc = ref<EditorDocument>((props.template.blocks as EditorDocument | null) ?? htmlToBlocks(props.template.body))

const samples = computed(() =>
  Object.fromEntries(props.mergeFields.map((field) => [field.path, field.sample])),
)

const form = useForm({ name: props.template.name, body: props.template.body, blocks: doc.value })

function submit() {
  form.body = serialize(doc.value, props.template.type === 'mail' ? 'mail' : 'print')
  form.blocks = doc.value
  form.patch(`/dokumentvorlagen/${props.template.id}`)
}
</script>

<template>
  <AdminLayout active="dokumentvorlagen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('documentTemplates.title') }} · {{ template.typeLabel }}</div>
        <h1>{{ template.name }}</h1>
      </div>
      <Button as="a" href="/dokumentvorlagen" variant="ghost">{{ $t('common.cancel') }}</Button>
      <Button type="button" :disabled="form.processing" @click="submit">{{ $t('common.save') }}</Button>
    </div>
    <p class="hint">{{ $t('documentTemplates.version', { version: template.version }) }} · {{ $t('documentTemplates.editHint') }}</p>
    <p v-if="form.errors.blocks" class="err">{{ form.errors.blocks }}</p>
    <p v-if="form.errors.body" class="err">{{ form.errors.body }}</p>

    <div class="fld" style="max-width: 420px">
      <Label for="name">{{ $t('documentTemplates.name') }}</Label>
      <Input id="name" v-model="form.name" type="text" required />
      <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
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

      <DocumentPreviewPane :type="template.type" :doc="doc" :samples="samples" />
    </div>
  </AdminLayout>
</template>
