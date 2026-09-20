<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useElementSize } from '@vueuse/core'
import PreviewFrame from './PreviewFrame.vue'

/**
 * The one sheet Zahlung and Belege land on: fields that were only visible
 * in a rendered PDF. Index.vue merges the live form on top of the saved
 * settings, so the pane being edited moves this in place.
 *
 * Sheet metrics and the scale-to-fit transform are QuotePreviewPane's own
 * pattern (SHEET_WIDTH/SHEET_HEIGHT, useElementSize, transform: scale) — an
 * A4 sheet rendered at its real 794px width and shrunk to the 352px rail
 * rather than reflowed, so what staff sees here is what Gotenberg prints.
 */
const props = defineProps<{
  doc: {
    documentLayout: string
  }
}>()

const SHEET_WIDTH = 794
const SHEET_HEIGHT = 1123

const html = ref('')
// PreviewFrame's own wrapper already pads (rounded-lg bg-muted p-4) — this
// div sits directly inside it, so its measured width is already the content
// width to fit the sheet into, no second padding subtracted here.
const frame = ref<HTMLElement | null>(null)
const { width: available } = useElementSize(frame)

const scale = computed(() => (available.value > 0 ? Math.min(1, available.value / SHEET_WIDTH) : 1))

async function load() {
  const response = await fetch(
    `/einstellungen/belege/angebot-vorschau?layout=${encodeURIComponent(props.doc.documentLayout ?? '')}`,
    { headers: { Accept: 'text/html' } },
  )

  if (response.ok) html.value = await response.text()
}

watch(() => props.doc.documentLayout, load, { immediate: true })
</script>

<template>
  <PreviewFrame :label="$t('settings.previewDocument')">
    <div ref="frame" class="overflow-hidden rounded-md" :style="{ height: `${SHEET_HEIGHT * scale}px` }">
      <!-- sandbox="" (no allow-* tokens): the sheet is a rendered document,
           not trusted script — an empty sandbox keeps a <script> in it from
           ever running with this session's origin. -->
      <iframe
        class="block border-0 bg-white"
        :srcdoc="html"
        sandbox=""
        title="Angebot-Vorschau"
        :style="{ width: `${SHEET_WIDTH}px`, height: `${SHEET_HEIGHT}px`, transform: `scale(${scale})`, transformOrigin: 'top left' }"
      />
    </div>
  </PreviewFrame>
</template>
