<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import EditorJS from '@editorjs/editorjs'
import Header from '@editorjs/header'
import List from '@editorjs/list'
import Quote from '@editorjs/quote'
import Delimiter from '@editorjs/delimiter'
import Table from '@editorjs/table'
import { buildTools } from '@/admin/documents/blocks/tools'
import { createMergeFieldTool } from '@/admin/documents/blocks/mergeFieldTool'
import { CalloutTool, ColumnsTool, ImageTool, SpacerTool } from '@/admin/documents/blocks/customTools'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

// The Editor.js shell. Vanilla instance rather than a wrapper package: the
// library is framework-agnostic and its lifecycle is two calls, so a wrapper
// would be a dependency that only renames them.
//
// The palette comes from the server (DocumentBlockCatalog) rather than being
// listed here, so what the inserter offers and what save validation accepts
// are the same list.
const props = defineProps<{
  palette: PaletteEntry[]
  mergeFields: { path: string; sample: string }[]
}>()

const model = defineModel<EditorDocument>({ required: true })

const holder = ref<HTMLElement | null>(null)
let editor: EditorJS | null = null

// paragraph is Editor.js's built-in default and needs no entry. The four
// custom tools come from customTools.ts (Task 6b) — Editor.js ships nothing
// for a callout, a spacer, a two-column row or a URL-only image.
const registry = {
  header: { class: Header, config: { levels: [1, 2, 3], defaultLevel: 2 } },
  list: List,
  quote: Quote,
  delimiter: Delimiter,
  table: Table,
  callout: CalloutTool,
  spacer: SpacerTool,
  columns: ColumnsTool,
  image: ImageTool,
}

onMounted(() => {
  editor = new EditorJS({
    holder: holder.value!,
    data: model.value,
    minHeight: 240,
    placeholder: 'Text schreiben oder / für Bausteine',
    tools: {
      ...buildTools(props.palette, registry),
      mergeField: { class: createMergeFieldTool(props.mergeFields) },
    },
    // Editor.js fires onChange on every keystroke; save() is cheap (it walks
    // the block list) and the preview pane is what debounces the round trip.
    onChange: async () => {
      model.value = (await editor!.save()) as EditorDocument
    },
  })
})

onBeforeUnmount(() => {
  editor?.destroy()
  editor = null
})

// A parent that replaces the whole document (type switch on the create screen)
// has to push it into the instance — Editor.js does not watch its data option.
watch(
  () => props.palette,
  async () => {
    if (editor === null) return
    await editor.isReady
    await editor.render(model.value)
  },
)
</script>

<template>
  <div ref="holder" class="doc-canvas"></div>
</template>
