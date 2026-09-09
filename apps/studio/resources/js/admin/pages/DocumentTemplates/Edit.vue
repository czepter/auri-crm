<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
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
  <AdminLayout active="vorlagen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="`${$t('documentTemplates.title')} · ${template.typeLabel}`" :title="template.name">
          <Button as="a" class="ml-auto" href="/vorlagen?typ=dokumente" variant="ghost">{{ $t('common.cancel') }}</Button>
          <Button variant="pill" type="button" :disabled="form.processing" @click="submit">{{ $t('common.save') }}</Button>
        </PageHead>

        <div class="px-8 pt-7 pb-8">
          <p class="text-[11.5px] leading-[1.45] text-faint">{{ $t('documentTemplates.version', { version: template.version }) }} · {{ $t('documentTemplates.editHint') }}</p>
          <p v-if="form.errors.blocks" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.blocks }}</p>
          <p v-if="form.errors.body" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.body }}</p>

          <div class="flex min-w-0 max-w-[420px] flex-col gap-1.5">
            <Label for="name">{{ $t('documentTemplates.name') }}</Label>
            <Input id="name" v-model="form.name" type="text" required />
            <p v-if="form.errors.name" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.name }}</p>
          </div>

          <div class="mt-6 grid min-h-0 grid-cols-[minmax(0,1fr)_7px_minmax(0,1fr)] gap-0 max-[1100px]:grid-cols-[minmax(0,1fr)]">
            <section class="flex min-w-0 flex-col">
              <div class="flex items-center gap-2.5 border-b border-border pb-2.5">
                <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('documentTemplates.body') }}</span>
                <span class="ml-auto text-[11.5px] leading-[1.45] text-faint">{{ $t('documentTemplates.bodyHint') }}</span>
              </div>
              <div class="mx-auto mt-6 w-full max-w-[520px] rounded-[2px] border border-border bg-white px-10 pb-10">
                <div class="-mx-10 border-dashed border-line-hover bg-[#FCFBFA] px-3 py-[9px] text-[10px] leading-[1.4] font-medium tracking-[0.06em] uppercase text-faint mb-6 border-b">{{ $t('documentTemplates.letterhead') }}</div>
                <DocumentEditor v-model="doc" :palette="palette" :merge-fields="mergeFields" />
                <div class="-mx-10 border-dashed border-line-hover bg-[#FCFBFA] px-3 py-[9px] text-[10px] leading-[1.4] font-medium tracking-[0.06em] uppercase text-faint -mb-10 mt-6 border-t">{{ $t('documentTemplates.footer') }}</div>
              </div>

              <!-- C-16 (#252): what this document type may not have stands dimmed
                   with its reason rather than missing. Editor.js owns its own plus
                   menu, so the list sits under the sheet instead of inside it —
                   replacing that inserter is a rewrite of the editor shell, not
                   this issue's subject. -->
              <div class="flex flex-wrap gap-x-3.5 gap-y-1.5 px-0.5 pt-2.5 text-[11.5px] text-[#6E6E73]">
                <span
                  v-for="entry in palette.filter((block) => !block.inline)"
                  :key="entry.type"
                  :class="entry.allowed === false && 'text-[#DEDEE1]'"
                >
                  {{ entry.label }}
                  <template v-if="entry.allowed === false"> · {{ $t('documentTemplates.blockOnly', { types: entry.only }) }}</template>
                </span>
              </div>
            </section>

            <div
              class="flex cursor-col-resize items-center justify-center before:block before:h-full before:w-px before:bg-border before:content-[''] max-[1100px]:hidden"
            />

            <DocumentPreviewPane :type="template.type" :doc="doc" :samples="samples" />
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
