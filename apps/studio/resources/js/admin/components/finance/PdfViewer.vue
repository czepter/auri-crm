<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ZoomIn, ZoomOut } from 'lucide-vue-next'
import { getDocument, GlobalWorkerOptions, type PDFDocumentProxy } from 'pdfjs-dist'
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
import { Button } from '@/ui/button'

GlobalWorkerOptions.workerSrc = workerUrl

const props = defineProps<{ url: string; title: string }>()

const pages = ref<HTMLElement | null>(null)
const total = ref(1)
const failed = ref(false)
const zoom = ref(1)
const current = ref(1)
let doc: PDFDocumentProxy | null = null
let width = 0
let run = 0
let observer: ResizeObserver | null = null

async function render() {
  const container = pages.value
  const pdf = doc
  if (!container || !pdf || !width) return

  const token = ++run
  const ratio = window.devicePixelRatio || 1
  const canvases: HTMLCanvasElement[] = []

  for (let number = 1; number <= pdf.numPages; number++) {
    const page = await pdf.getPage(number)
    if (token !== run) return

    const base = page.getViewport({ scale: 1 })
    const scale = (width / base.width) * zoom.value
    const viewport = page.getViewport({ scale })
    const canvas = document.createElement('canvas')
    canvas.width = Math.floor(viewport.width * ratio)
    canvas.height = Math.floor(viewport.height * ratio)
    canvas.style.width = `${Math.floor(viewport.width)}px`
    canvas.style.height = `${Math.floor(viewport.height)}px`
    canvas.dataset.page = String(number)
    canvas.className = 'mx-auto mb-3 block bg-white shadow-sm'

    const context = canvas.getContext('2d')
    if (!context) return
    await page.render({ canvas, canvasContext: context, viewport, transform: ratio === 1 ? undefined : [ratio, 0, 0, ratio, 0, 0] }).promise
    if (token !== run) return
    canvases.push(canvas)
  }

  container.replaceChildren(...canvases)
}

async function load() {
  failed.value = false
  doc = null
  run++

  try {
    doc = await getDocument({ url: props.url, withCredentials: true }).promise
    total.value = doc.numPages
    await render()
  } catch {
    failed.value = true
  }
}

function track() {
  const container = pages.value
  if (!container) return

  const middle = container.scrollTop + container.clientHeight / 2
  const canvases = Array.from(container.querySelectorAll<HTMLCanvasElement>('canvas'))
  const hit = canvases.find((canvas) => canvas.offsetTop + canvas.offsetHeight > middle)
  current.value = Number(hit?.dataset.page ?? canvases.length ?? 1)
}

onMounted(() => {
  observer = new ResizeObserver(([entry]) => {
    const next = Math.floor(entry.contentRect.width)
    if (next === width) return
    width = next
    void render()
  })
  if (pages.value) observer.observe(pages.value)
  void load()
})

onBeforeUnmount(() => {
  observer?.disconnect()
  run++
  void doc?.destroy()
})

watch(() => props.url, load)
watch(zoom, render)
</script>

<template>
  <div class="flex w-full min-h-0 flex-col bg-sunk" role="document" :aria-label="title">
    <div class="flex shrink-0 items-center justify-between gap-2 border-b border-border bg-background px-2 py-1">
      <span class="px-1 text-small tabular-nums text-ink-2">{{ current }} / {{ total }}</span>
      <div class="flex items-center gap-1">
        <Button variant="ghost" size="icon" type="button" class="size-7" :aria-label="$t('financeExpense.zoomOut')" :title="$t('financeExpense.zoomOut')" :disabled="zoom <= 0.5" @click="zoom = Math.max(0.5, zoom - 0.25)"><ZoomOut /></Button>
        <Button variant="ghost" size="icon" type="button" class="size-7" :aria-label="$t('financeExpense.zoomIn')" :title="$t('financeExpense.zoomIn')" :disabled="zoom >= 3" @click="zoom = Math.min(3, zoom + 0.25)"><ZoomIn /></Button>
      </div>
    </div>
    <div ref="pages" class="min-h-0 flex-1 overflow-auto p-3" tabindex="0" @scroll.passive="track" />
    <p v-if="failed" class="p-3 text-small text-destructive">{{ $t('financeExpense.previewFailed') }}</p>
  </div>
</template>
