<script setup lang="ts">
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import DocumentEditor from '@/admin/components/DocumentEditor.vue'
import { serialize } from '@/admin/documents/blocks/serialize'
import type { EditorBlock, EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

// #480: Einleitung und Schluss von Angebot und Rechnung. Gespeichert wird das
// HTML, das serialize() daraus macht; die Blöcke entstehen beim Öffnen wieder
// aus diesem HTML, damit kein zweites Feld neben dem Text mitgeführt wird.
const model = defineModel<string>({ required: true })

type LetterEditor = { palette: PaletteEntry[]; mergeFields: { path: string; sample: string }[] }

// Einmal gelesen: die geteilte Prop kommt mit jeder Antwort neu, und eine neue
// Palette baut den Editor neu — beim Autosave verlöre das den Cursor.
const { palette, mergeFields } = usePage().props.letterEditor as LetterEditor
const samples = Object.fromEntries(mergeFields.map((field) => [field.path, field.sample]))

function chips(html: string): string {
  return html.replace(/\{\{\s*([\w.]+)\s*\}\}/g, (_match, path: string) =>
    `<span class="doc-mf" data-mf="${path}">${samples[path] ?? path}</span>`)
}

function toBlocks(html: string): EditorDocument {
  const body = new DOMParser().parseFromString(html, 'text/html').body
  const blocks: EditorBlock[] = []

  for (const node of Array.from(body.childNodes)) {
    if (node.nodeType === Node.TEXT_NODE) {
      if (node.textContent?.trim()) blocks.push({ type: 'paragraph', data: { text: chips(node.textContent) } })
      continue
    }

    const element = node as HTMLElement
    const tag = element.tagName.toLowerCase()

    if (/^h[1-3]$/.test(tag)) {
      blocks.push({ type: 'header', data: { text: chips(element.innerHTML), level: Number(tag[1]) } })
    } else if (tag === 'ul' || tag === 'ol') {
      blocks.push({
        type: 'list',
        data: {
          style: tag === 'ol' ? 'ordered' : 'unordered',
          items: Array.from(element.children).map((item) => ({ content: chips(item.innerHTML), meta: {}, items: [] })),
        },
      })
    } else if (tag === 'blockquote') {
      blocks.push({
        type: 'quote',
        data: { text: chips(element.querySelector('p')?.innerHTML ?? element.innerHTML), caption: chips(element.querySelector('cite')?.innerHTML ?? '') },
      })
    } else if (tag === 'hr') {
      blocks.push({ type: 'delimiter', data: {} })
    } else {
      blocks.push({ type: 'paragraph', data: { text: chips(element.innerHTML) } })
    }
  }

  return { blocks }
}

const doc = ref<EditorDocument>(toBlocks(model.value))

watch(doc, (value) => {
  model.value = serialize(value, 'print')
})
</script>

<template>
  <div class="rounded-md border border-border px-3 py-2.5">
    <DocumentEditor v-model="doc" :palette="palette" :merge-fields="mergeFields" :min-height="60" />
  </div>
</template>
