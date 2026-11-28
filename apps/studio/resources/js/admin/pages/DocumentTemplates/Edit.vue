<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import DocumentEditor from '@/admin/components/templates/DocumentEditor.vue'
import DocumentPreviewPane from '@/admin/components/templates/DocumentPreviewPane.vue'
import { PreviewOverlay } from '@/ui/preview-overlay'
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

const samples = computed(() =>
  Object.fromEntries(props.mergeFields.map((field) => [field.path, field.sample])),
)

// A template written before the block editor has blocks = null; its body
// becomes one rawHtml block the author can keep or replace. The import is
// only persisted if they save.
const doc = ref<EditorDocument>((props.template.blocks as EditorDocument | null) ?? htmlToBlocks(props.template.body, samples.value))

const form = useForm({ name: props.template.name, subject: props.template.subject ?? '', body: props.template.body, blocks: doc.value })

function submit() {
  form.body = serialize(doc.value, props.template.type === 'mail' ? 'mail' : 'print')
  form.blocks = doc.value
  form.transform((data) => ({ ...data, subject: data.subject || null })).patch(`/dokumentvorlagen/${props.template.id}`)
}
</script>

<template>
  <AdminLayout active="vorlagen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead kicker-href="/vorlagen?typ=dokumente" :kicker="`${$t('documentTemplates.title')} · ${template.typeLabel}`" :title="template.name">
          <Button as="a" class="ml-auto" href="/vorlagen?typ=dokumente" variant="ghost">{{ $t('common.cancel') }}</Button>
          <Button variant="default" type="button" :disabled="form.processing" @click="submit">{{ $t('common.save') }}</Button>
        </PageHead>

        <div class="px-6 pt-7 pb-8">
          <p class="text-small leading-[1.45] text-faint">{{ $t('documentTemplates.version', { version: template.version }) }} · {{ $t('documentTemplates.editHint') }}</p>
          <p v-if="form.errors.blocks" class="text-small leading-[1.45] text-destructive">{{ form.errors.blocks }}</p>
          <p v-if="form.errors.body" class="text-small leading-[1.45] text-destructive">{{ form.errors.body }}</p>

          <div class="flex min-w-0 max-w-[420px] flex-col gap-1.5">
            <Label for="name">{{ $t('documentTemplates.name') }}</Label>
            <Input id="name" v-model="form.name" type="text" required />
            <p v-if="form.errors.name" class="text-small leading-[1.45] text-destructive">{{ form.errors.name }}</p>
          </div>

          <div v-if="template.type === 'mail'" class="mt-4 flex min-w-0 max-w-[420px] flex-col gap-1.5">
            <Label for="subject">{{ $t('sendMail.subject') }}</Label>
            <Input id="subject" v-model="form.subject" type="text" />
          </div>

          <div class="mt-6 grid min-h-0 grid-cols-[minmax(0,1fr)_7px_minmax(0,1fr)] gap-0 max-[1100px]:grid-cols-[minmax(0,1fr)]">
            <section class="flex min-w-0 flex-col">
              <div class="flex items-center gap-2.5 border-b border-border pb-2.5">
                <span class="mb-[5px] text-small leading-none font-semibold text-faint">{{ $t('documentTemplates.body') }}</span>
                <span class="ml-auto text-small leading-[1.45] text-faint">{{ $t('documentTemplates.bodyHint') }}</span>
              </div>
              <div class="mx-auto mt-6 w-full max-w-[520px] rounded-[2px] border border-border bg-white px-10 pb-10">
                <div class="-mx-10 border-dashed border-line-hover bg-sunk px-3 py-[9px] text-small leading-[1.4] font-medium text-faint mb-6 border-b">{{ $t('documentTemplates.letterhead') }}</div>
                <DocumentEditor v-model="doc" :palette="palette" :merge-fields="mergeFields" />
                <div class="-mx-10 border-dashed border-line-hover bg-sunk px-3 py-[9px] text-small leading-[1.4] font-medium text-faint -mb-10 mt-6 border-t">{{ $t('documentTemplates.footer') }}</div>
              </div>

              <!-- C-16 (#252): what this document type may not have stands dimmed
                   with its reason rather than missing. Editor.js owns its own plus
                   menu, so the list sits under the sheet instead of inside it —
                   replacing that inserter is a rewrite of the editor shell, not
                   this issue's subject. -->
              <div class="flex flex-wrap gap-x-3.5 gap-y-1.5 px-0.5 pt-2.5 text-small text-ink-2">
                <span
                  v-for="entry in palette.filter((block) => !block.inline)"
                  :key="entry.type"
                  :class="entry.allowed === false && 'text-mark'"
                >
                  {{ entry.label }}
                  <template v-if="entry.allowed === false"> · {{ $t('documentTemplates.blockOnly', { types: entry.only }) }}</template>
                </span>
              </div>
            </section>

            <div
              class="flex cursor-col-resize items-center justify-center before:block before:h-full before:w-px before:bg-border before:content-[''] max-[1100px]:hidden"
            />

            <PreviewOverlay :min="1101" :label="$t('common.preview')" wrapper-class="contents">
              <DocumentPreviewPane :type="template.type" :doc="doc" :samples="samples" />
            </PreviewOverlay>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
