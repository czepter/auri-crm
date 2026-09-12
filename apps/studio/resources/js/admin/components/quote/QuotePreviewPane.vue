<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useDebounceFn, useElementSize } from '@vueuse/core'
import { buildQuotePayload, type QuoteDraft } from './quotePayload'

// Server-rendered only — unlike DocumentPreviewPane there is no local instant
// paint here, that half belongs to Editor.js and has no counterpart on a
// quote draft.
const props = defineProps<{
  quoteId: string
  draft: QuoteDraft
  canManageCatalog: boolean
}>()

// A4 at 96 dpi, matching DocumentPreviewPane's own sheet metrics — the same
// paper RenderQuotePdf sends to Gotenberg.
const SHEET_WIDTH = 794
const SHEET_HEIGHT = 1123
const SHEET_MARGIN = 38
const PAD = 16

const html = ref('')
const stale = ref(true)
const fit = ref(true)

const frame = ref<HTMLElement | null>(null)
const { width: available } = useElementSize(frame)

const scale = computed(() =>
  fit.value && available.value > 0 ? Math.min(1, (available.value - PAD * 2) / SHEET_WIDTH) : 1,
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

  return html.value.includes('</head>') ? html.value.replace('</head>', `${style}</head>`) : style + html.value
})

// useDebounceFn only cancels a pending, not-yet-started call — a monotonic
// token is how a later edit's response wins even if it lands first.
let requestId = 0

const refreshFromServer = useDebounceFn(async () => {
  const id = ++requestId
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
  const body = buildQuotePayload(props.draft, { canManageCatalog: props.canManageCatalog })

  try {
    const response = await fetch(`/angebote/${props.quoteId}/vorschau`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'text/html' },
      body: JSON.stringify(body),
    })

    if (!response.ok) {
      if (id === requestId) stale.value = true
      return
    }

    const responseHtml = await response.text()

    if (id !== requestId) return

    html.value = responseHtml
    stale.value = false
  } catch {
    // Offline or the request died: the last good sheet stays on screen, only
    // marked stale rather than blanked.
    if (id === requestId) stale.value = true
  }
}, 400)

watch(
  () => [props.draft, props.canManageCatalog],
  () => {
    stale.value = true
    refreshFromServer()
  },
  { deep: true, immediate: true },
)
</script>

<template>
  <section class="flex min-w-0 flex-col">
    <div class="flex items-center gap-2.5 border-b border-border pb-2.5">
      <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('quotes.preview') }}</span>
      <div class="ml-auto flex items-center gap-1.5">
        <button
          v-for="option in [{ key: 'fit', on: true, label: 'quotes.zoomFit' }, { key: 'full', on: false, label: 'quotes.zoomFull' }]"
          :key="option.key"
          class="cursor-pointer rounded-lg border-0 px-2.5 py-[5px] text-[12px] leading-[1.3]"
          :class="fit === option.on ? 'bg-foreground text-background' : 'bg-sunk-2 text-foreground'"
          type="button"
          @click="fit = option.on"
        >{{ $t(option.label) }}</button>
      </div>
      <span class="ml-2.5 text-[11px] leading-none" :class="stale ? 'text-due' : 'text-faint'">{{
        stale ? $t('quotes.previewStale') : $t('quotes.previewFresh')
      }}</span>
    </div>

    <!-- sandbox="" (no allow-* tokens): the sheet is staff-authored HTML, not
         trusted script — an empty sandbox keeps a <script> in it from ever
         running with this session's origin. -->
    <div
      ref="frame"
      class="mt-6 flex-1 overflow-auto bg-sunk"
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
  </section>
</template>
