<script setup lang="ts">
import { computed, ref } from 'vue'
import { useElementSize } from '@vueuse/core'

// A4 at 96 dpi, matching DocumentPreviewPane's own sheet metrics — the same
// paper RenderQuotePdf and RenderInvoicePdf send to Gotenberg.
const SHEET_WIDTH = 794
const SHEET_HEIGHT = 1123
const SHEET_MARGIN = 38
const PAD = 16

const props = defineProps<{ html: string; fit: boolean }>()

const frame = ref<HTMLElement | null>(null)
const { width: available } = useElementSize(frame)

const scale = computed(() =>
  props.fit && available.value > 0 ? Math.min(1, (available.value - PAD * 2) / SHEET_WIDTH) : 1,
)

const sheet = computed(() => {
  const style = `<style>
    @page { size: A4; margin: ${SHEET_MARGIN}px; }
    html { background: #fff; }
    body {
      width: ${SHEET_WIDTH}px; min-height: ${SHEET_HEIGHT}px; margin: 0; padding: ${SHEET_MARGIN}px;
      background-image: repeating-linear-gradient(to bottom, transparent 0 ${SHEET_HEIGHT - 1}px, #E9E9E7 ${SHEET_HEIGHT - 1}px ${SHEET_HEIGHT}px);
    }
  </style>`

  return props.html.includes('</head>') ? props.html.replace('</head>', `${style}</head>`) : style + props.html
})
</script>

<template>
  <!-- sandbox="" (no allow-* tokens): the sheet is staff-authored HTML, not
       trusted script — an empty sandbox keeps a <script> in it from ever
       running with this session's origin. -->
  <div
    ref="frame"
    class="mt-6 max-h-[calc(100dvh-200px)] flex-1 overflow-auto bg-sunk"
    :style="{ height: `${SHEET_HEIGHT * scale + PAD * 2}px`, padding: `${PAD}px` }"
  >
    <iframe
      class="block border border-border bg-white shadow-[0_18px_40px_-24px_rgba(20,16,14,.28)]"
      :srcdoc="sheet"
      sandbox=""
      :style="{
        width: `${SHEET_WIDTH}px`,
        height: `${SHEET_HEIGHT}px`,
        transform: `scale(${scale})`,
        transformOrigin: 'top left',
      }"
    ></iframe>
  </div>
</template>
