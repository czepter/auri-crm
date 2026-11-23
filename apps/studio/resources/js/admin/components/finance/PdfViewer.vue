<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { getDocument, GlobalWorkerOptions, type PDFDocumentLoadingTask, type PDFDocumentProxy } from 'pdfjs-dist'
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
import { ChoiceGroup } from '@/ui/choice-group'

GlobalWorkerOptions.workerSrc = workerUrl

const FULL_SCALE = 96 / 72

const props = defineProps<{ url: string; title: string }>()

const pages = ref<HTMLElement | null>(null)
const failed = ref(false)
const fit = ref(true)
let task: PDFDocumentLoadingTask | null = null
let doc: PDFDocumentProxy | null = null
let width = 0
let run = 0
let observer: ResizeObserver | null = null

async function render() {
  try {
    await draw()
  } catch {
    failed.value = true
  }
}

async function draw() {
  const container = pages.value
  const pdf = doc
  if (!container || !pdf || !width) return

  const token = ++run
  const ratio = window.devicePixelRatio || 1

  for (let number = 1; number <= pdf.numPages; number++) {
    const page = await pdf.getPage(number)
    if (token !== run) return

    const base = page.getViewport({ scale: 1 })
    const scale = fit.value ? width / base.width : FULL_SCALE
    const viewport = page.getViewport({ scale })
    const canvas = document.createElement('canvas')
    canvas.width = Math.floor(viewport.width * ratio)
    canvas.height = Math.floor(viewport.height * ratio)
    canvas.style.width = `${Math.round(viewport.width)}px`
    canvas.style.height = `${Math.round(viewport.height)}px`
    canvas.className = 'mx-auto mb-4 block last:mb-0 border border-border bg-white shadow-[0_18px_40px_-24px_rgba(20,16,14,.28)]'

    const context = canvas.getContext('2d')
    if (!context) return
    await page.render({ canvas, canvasContext: context, viewport, transform: ratio === 1 ? undefined : [ratio, 0, 0, ratio, 0, 0] }).promise
    if (token !== run) return
    if (number === 1) container.replaceChildren(canvas)
    else container.append(canvas)
  }
}

async function load() {
  failed.value = false
  doc = null
  run++
  void task?.destroy()

  try {
    task = getDocument({ url: props.url, withCredentials: true })
    doc = await task.promise
    await render()
  } catch {
    failed.value = true
  }
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
  void task?.destroy()
})

watch(() => props.url, load)
watch(fit, render)
</script>

<template>
  <div class="flex min-w-0 flex-col">
    <div class="flex items-center border-b border-border pb-2.5">
      <span class="mb-[5px] text-small leading-none font-semibold text-faint">{{ $t('financeExpense.receipt') }}</span>
      <ChoiceGroup
        v-if="!failed"
        :model-value="fit ? 'fit' : 'full'"
        :options="[{ value: 'fit', label: $t('quotes.zoomFit') }, { value: 'full', label: $t('quotes.zoomFull') }]"
        class="ml-auto"
        @update:model-value="(value) => (fit = value === 'fit')"
      />
    </div>
    <p v-if="failed" class="mt-6 flex h-[640px] items-center justify-center bg-sunk text-small text-destructive max-[900px]:h-[320px]">{{ $t('financeExpense.previewFailed') }}</p>
    <div v-show="!failed" ref="pages" role="document" :aria-label="title" class="mt-6 h-[640px] overflow-auto bg-sunk p-4 max-[900px]:h-[320px]" tabindex="0" />
  </div>
</template>
