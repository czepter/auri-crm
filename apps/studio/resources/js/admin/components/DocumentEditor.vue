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
import { CalloutTool, ColumnsTool, ImageTool, RawHtmlTool, SpacerTool } from '@/admin/documents/blocks/customTools'
import {
  ChecklistTool,
  ClauseTool,
  FinePrintTool,
  PageBreakTool,
  SignatureTool,
  rowsTool,
} from '@/admin/documents/blocks/contractTools'
import { ROW_SPECS } from '@/admin/documents/blocks/rowSpecs'
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
  pagebreak: PageBreakTool,
  clause: ClauseTool,
  signature: SignatureTool,
  checklist: ChecklistTool,
  finePrint: FinePrintTool,
  // Positionen, Zahlungsplan, Nutzungsrechte, Ablauf, Lieferumfang,
  // Ausfallstaffel: ein Werkzeug, sechs Spaltensätze.
  ...Object.fromEntries(ROW_SPECS.map((spec) => [spec.type, rowsTool(spec)])),
}

// Extracted so onMounted and the palette watcher build an instance exactly
// one way — the tool set is only ever set at construction (see the watcher
// below for why).
function create(data: EditorDocument): EditorJS {
  return new EditorJS({
    holder: holder.value!,
    data,
    minHeight: 240,
    placeholder: 'Text schreiben oder / für Bausteine',
    tools: {
      ...buildTools(props.palette, registry),
      // Never in the palette, always registered: this is the block every
      // pre-editor template imports into, and without a tool Editor.js drops it.
      rawHtml: RawHtmlTool,
      mergeField: { class: createMergeFieldTool(props.mergeFields) },
    },
    // Editor.js fires onChange on every keystroke; save() is cheap (it walks
    // the block list) and the preview pane is what debounces the round trip.
    onChange: async () => {
      model.value = (await editor!.save()) as EditorDocument
    },
  })
}

onMounted(() => {
  editor = create(model.value)
})

onBeforeUnmount(() => {
  editor?.destroy()
  editor = null
})

// Editor.js takes its `tools` map once, at construction, and offers no way to
// change it on a live instance — so a palette change (the type select on the
// create screen) means a new instance, not a re-render. The current document
// is carried across explicitly; destroy() alone would drop it.
watch(
  () => props.palette,
  async () => {
    if (editor === null) return

    // Editor.js attaches save()/destroy() to the instance only after its
    // internal isReady promise resolves — before that they are undefined, and
    // a palette change arriving in that window would throw and leave the tool
    // set stale, which is the bug this watcher exists to prevent.
    await editor.isReady

    const current = await editor.save()

    editor.destroy()
    editor = create(current as EditorDocument)
  },
)
</script>

<template>
  <div ref="holder"></div>
</template>
