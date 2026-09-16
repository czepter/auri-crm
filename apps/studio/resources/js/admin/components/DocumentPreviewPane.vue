<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useDebounceFn, useElementSize } from '@vueuse/core'
import { serialize } from '@/admin/documents/blocks/serialize'
import { fillSamples } from '@/admin/documents/blocks/importHtml'
import type { EditorDocument } from '@/admin/documents/blocks/types'

// Two-speed preview. The local paint keeps the sheet alive while typing; the
// server answer is the one that counts, because it runs the real
// MergeFieldResolver and DocumentRenderer — the same pair every PDF render
// uses. The local pass never decides anything, it only fills the 400ms.
const props = defineProps<{
  type: string
  doc: EditorDocument
  samples: Record<string, string>
  // C-16 (#252): the project the preview reckons with. Null falls back to the
  // sample values — a template written before there is any project to try it
  // against still has to render.
  project?: string | null
}>()

// A4 at 96 dpi, and Chromium's own default print margin (1 cm) — Gotenberg
// sends no margin of its own (GotenbergClient), so this is the paper the PDF
// actually comes out on rather than a nicer-looking guess.
const SHEET_WIDTH = 794
const SHEET_HEIGHT = 1123
const SHEET_MARGIN = 38

const html = ref('')
const stale = ref(false)
const fit = ref(true)

const frame = ref<HTMLElement | null>(null)
const { width: available } = useElementSize(frame)

// Das Blatt liegt auf einer Fläche, sonst steht Weiß auf Weiß und die Kante
// des Papiers ist nicht zu sehen. PAD ist der Rand dieser Fläche.
const PAD = 16

const scale = computed(() =>
  fit.value && available.value > 0 ? Math.min(1, (available.value - PAD * 2) / SHEET_WIDTH) : 1,
)

// Mail is not paper: QuoteSent builds an email, so its preview keeps the
// fluid width it had rather than pretending to be a page.
const paper = computed(() => props.type !== 'mail')

const sheet = computed(() => {
  if (!paper.value) return html.value

  // Nur das Papier, nicht der Satz: Schrift, Größen und Abstände kommen aus
  // DocumentRenderer, damit auf dem Schirm steht, was aus dem Drucker kommt.
  const style = `<style>
    @page { size: A4; margin: ${SHEET_MARGIN}px; }
    html { background: #fff; }
    body {
      width: ${SHEET_WIDTH}px; min-height: ${SHEET_HEIGHT}px; margin: 0; padding: ${SHEET_MARGIN}px;
      background-image: repeating-linear-gradient(to bottom, transparent 0 ${SHEET_HEIGHT - 1}px, #E9E9E7 ${SHEET_HEIGHT - 1}px ${SHEET_HEIGHT}px);
    }
  </style>`

  if (html.value.includes('</head>')) return html.value.replace('</head>', `${style}</head>`)

  // Der Sofort-Paint ist ein Fragment ohne den Satz aus DocumentRenderer —
  // er füllt die 400ms bis zur Serverantwort und bekommt darum nur den
  // Schriftgrad, damit die Zeilen nicht auf halber Höhe springen, sobald
  // die eigentliche Fassung eintrifft.
  const provisional = `<style>
    body { font: 10.5pt/1.55 'Hanken Grotesk', 'Helvetica Neue', Arial, sans-serif; color: #191919; }
  </style>`

  return style + provisional + html.value
})

// useDebounceFn only cancels a pending, not-yet-started invocation — it does
// not abort one already in flight. Two edits 400ms apart can put two fetches
// in the air, and a monotonic token is how the later one wins even if its
// response lands first.
let requestId = 0

const refreshFromServer = useDebounceFn(async () => {
  const id = ++requestId
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
  const body = serialize(props.doc, props.type === 'mail' ? 'mail' : 'print')

  // An empty document has nothing to preview and the endpoint requires a body.
  if (body === '') {
    if (id !== requestId) return

    html.value = ''
    stale.value = false

    return
  }

  try {
    const response = await fetch('/dokumentvorlagen/vorschau', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'text/html' },
      body: JSON.stringify({ type: props.type, body, project: props.project ?? null }),
    })

    if (!response.ok) return

    const responseHtml = await response.text()

    // Re-checked after the body read, not before it: a slow body can outlast
    // a newer request's entire round trip, and a guard with an await after it
    // is not a guard. The local paint is allowed to lag because it announces
    // itself as provisional, but a server answer that is silently behind
    // would be indistinguishable from truth.
    if (id !== requestId) return

    html.value = responseHtml
    stale.value = false
  } catch {
    // Offline or the request died: the preview is genuinely unconfirmed, so
    // `stale` stays true rather than being reset to a freshness we cannot claim.
  }
}, 400)

watch(
  () => [props.doc, props.type, props.project],
  () => {
    stale.value = true
    html.value = fillSamples(serialize(props.doc, props.type === 'mail' ? 'mail' : 'print'), props.samples)
    refreshFromServer()
  },
  { deep: true, immediate: true },
)
</script>

<template>
  <section class="flex min-w-0 flex-col">
    <div class="flex items-center gap-2.5 border-b border-border pb-2.5">
      <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('documentTemplates.previewLabel') }}</span>
      <slot />
      <div v-if="paper" class="ml-auto flex items-center gap-1.5">
        <button
          v-for="option in [{ key: 'fit', on: true }, { key: 'full', on: false }]"
          :key="option.key"
          class="cursor-pointer rounded-lg border-0 px-2.5 py-[5px] text-[12px] leading-[1.3]"
          :class="fit === option.on ? 'bg-foreground text-background' : 'bg-sunk-2 text-foreground'"
          type="button"
          @click="fit = option.on"
        >{{ $t(`documentTemplates.zoom_${option.key}`) }}</button>
      </div>
      <span class="text-[11px] leading-none" :class="[stale ? 'text-due' : 'text-faint', paper ? 'ml-2.5' : 'ml-auto']">{{
        stale ? $t('documentTemplates.previewStale') : $t('documentTemplates.previewFresh')
      }}</span>
    </div>

    <!-- sandbox="" (no allow-* tokens at all): a template body is
         staff-authored HTML, not trusted script — an empty sandbox strips
         script execution, form submission and top-level navigation, so a
         <script> in a preview can never run with this session's origin. -->
    <div
      v-if="paper"
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

    <iframe
      v-else
      class="mt-6 min-h-[520px] w-full flex-1 rounded-[2px] border border-border bg-white max-[1100px]:min-h-[360px]"
      :srcdoc="sheet"
      sandbox=""
    ></iframe>
  </section>
</template>
