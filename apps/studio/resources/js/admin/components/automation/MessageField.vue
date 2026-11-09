<script setup lang="ts">
// contenteditable statt Editor.js: dessen save() ist asynchron und ein neuer
// Beispielwert müsste den Editor neu aufbauen, der Cursor ginge verloren.
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { cn } from '@/lib/utils'
import { inputVariants } from '@/ui/input'
import { fieldCount, locate, needsSpace, offsetOf, parts, plain, read } from './messageText'

const props = defineProps<{
  fields: { path: string; label: string }[]
  samples: Record<string, string>
  placeholder?: string
  invalid?: boolean
  singleLine?: boolean
  id?: string
}>()
const model = defineModel<string>({ required: true })

const el = ref<HTMLElement | null>(null)
const labels = () => new Map(props.fields.map((f) => [f.path, f.label]))
const known = () => new Set(props.fields.map((f) => f.path))
let saved: Range | null = null

function chipText(path: string): string {
  const sample = props.samples[path]

  return sample ? `${labels().get(path) ?? path} · ${sample}` : (labels().get(path) ?? path)
}

function chip(path: string): HTMLElement {
  const node = document.createElement('span')
  node.dataset.mf = path
  node.contentEditable = 'false'
  node.className = 'mx-px inline-block rounded-[var(--r-sm)] bg-sunk px-1.5 align-baseline text-small font-medium whitespace-nowrap shadow-[inset_0_0_0_1px_var(--line)]'
  node.textContent = chipText(path)

  return node
}

// A caret at the end needs a second newline to show; the phantom one is dropped here.
const value = () => {
  const text = plain(read(el.value!)).replace(/\n$/, '')

  return props.singleLine ? text.replace(/\s*\n\s*/g, ' ') : text
}

function paint(text = model.value) {
  el.value!.replaceChildren(...parts(text, known()).map((part) => ('field' in part ? chip(part.field) : document.createTextNode(part.text))))
}

function select(r: Range) {
  getSelection()?.removeAllRanges()
  getSelection()?.addRange(r)
}

const TYPED = /\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}$/
let converting = false

// Swapping only the typed range keeps the browser's undo stack; a repaint would wipe it.
function chipTyped(text: string, at: number): boolean {
  const typed = text.slice(0, at).match(TYPED)
  if (!typed || !known().has(typed[1])) return false
  const from = locate(el.value!, at - typed[0].length)
  const to = locate(el.value!, at)
  const r = document.createRange()
  r.setStart(from.node as Node, from.offset)
  r.setEnd(to.node as Node, to.offset)
  select(r)
  converting = true
  const done = document.execCommand('insertHTML', false, chip(typed[1]).outerHTML)
  converting = false

  return done
}

// A typed or pasted `{{ path }}` has no chip yet: swap it in, else repaint, and put the caret back.
function changed(event?: Event) {
  if (converting) return
  const root = el.value!
  if (!root.textContent && !root.querySelector('[data-mf]')) root.replaceChildren()
  const text = value()
  model.value = text
  const input = event as InputEvent | undefined
  if (input?.isComposing || input?.inputType.startsWith('history')) return
  if (fieldCount(parts(text, known())) === root.querySelectorAll('[data-mf]').length) return

  const selection = getSelection()
  const at = selection?.rangeCount && root.contains(selection.anchorNode) ? offsetOf(root, selection.anchorNode!, selection.anchorOffset) : null
  if (at !== null && selection!.isCollapsed && chipTyped(text, at) && fieldCount(parts(value(), known())) === root.querySelectorAll('[data-mf]').length) {
    model.value = value()

    return
  }
  paint(text)
  model.value = value()
  if (at === null) return
  const spot = locate(root, at)
  const r = document.createRange()
  r.setStart(spot.node as Node, spot.offset)
  r.collapse(true)
  select(r)
}

function range(): Range {
  const selection = getSelection()
  const current = selection?.rangeCount && el.value!.contains(selection.anchorNode) ? selection.getRangeAt(0) : saved
  const r = current ?? document.createRange()
  if (!current) {
    r.selectNodeContents(el.value!)
    r.collapse(false)
  }

  // A click on a chip puts the selection inside it, where nothing can be inserted.
  const start = r.startContainer
  const host = (start instanceof Element ? start : start.parentElement)?.closest('[data-mf]')
  if (host) {
    r.setStartAfter(host)
    r.collapse(true)
  }

  return r
}

function put(nodes: Node[], caretAfter = nodes.length - 1, r = range()) {
  r.deleteContents()
  for (const node of [...nodes].reverse()) r.insertNode(node)
  r.setStartAfter(nodes[caretAfter])
  r.collapse(true)
  select(r)
  changed()
}

function newline() {
  if (document.execCommand('insertLineBreak')) return
  const tail = document.createRange()
  tail.selectNodeContents(el.value!)
  tail.setStart(range().endContainer, range().endOffset)
  const atEnd = tail.toString() === '' && !tail.cloneContents().querySelector('[data-mf]')
  put(atEnd ? [document.createTextNode('\n'), document.createTextNode('\n')] : [document.createTextNode('\n')], 0)
}

function remember() {
  const selection = getSelection()
  if (selection?.rangeCount && el.value!.contains(selection.anchorNode)) saved = selection.getRangeAt(0).cloneRange()
}

// execCommand keeps the browser's undo stack; the Range path is the fallback.
function type(raw: string) {
  const text = props.singleLine ? raw.replace(/\s*[\r\n]+\s*/g, ' ') : raw.replace(/\r\n?/g, '\n')
  const r = range()
  el.value!.focus()
  select(r)
  if (!text || document.execCommand('insertText', false, text)) return
  put([document.createTextNode(text)], 0, r)
}

function paste(event: ClipboardEvent) {
  event.preventDefault()
  type(event.clipboardData?.getData('text/plain') ?? '')
}

function drop(event: DragEvent) {
  event.preventDefault()
  const text = event.dataTransfer?.getData('text/plain') ?? ''
  const spot = document.caretPositionFromPoint?.(event.clientX, event.clientY)
  const at = spot ? { node: spot.offsetNode, offset: spot.offset } : null
  if (at && el.value!.contains(at.node)) {
    const r = document.createRange()
    r.setStart(at.node, at.offset)
    r.collapse(true)
    saved = r
    select(r)
  }
  type(text)
}

function enter(event: KeyboardEvent) {
  if (event.isComposing || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) return
  event.preventDefault()
  if (!props.singleLine) newline()
}

function insert(path: string) {
  const r = range().cloneRange()
  const lead = needsSpace(value(), offsetOf(el.value!, r.startContainer, r.startOffset)) ? ' ' : ''
  el.value!.focus()
  select(r)
  const node = chip(path)
  if (document.execCommand('insertHTML', false, `${lead}${node.outerHTML}`)) return
  const nodes = [...(lead ? [document.createTextNode(lead)] : []), node]
  put(nodes, nodes.length - 1, r)
}

let label: HTMLElement | null = null
const focus = () => el.value?.focus()

onMounted(() => {
  paint()
  label = props.id ? document.querySelector<HTMLElement>(`label[for="${props.id}"]`) : null
  if (!label) return
  label.id ||= `${props.id}-label`
  el.value!.setAttribute('aria-labelledby', label.id)
  label.addEventListener('click', focus)
})
onBeforeUnmount(() => label?.removeEventListener('click', focus))
watch(model, (next) => {
  if (next !== value()) paint()
})
watch(() => [props.samples, props.fields], () => {
  for (const node of el.value!.querySelectorAll<HTMLElement>('[data-mf]')) node.textContent = chipText(node.dataset.mf!)
})

defineExpose({ insert, focus })
</script>

<template>
  <div
    :id="id"
    ref="el"
    :class="cn(inputVariants(), singleLine ? 'overflow-hidden whitespace-nowrap leading-[1.35]' : 'min-h-[104px] whitespace-pre-wrap leading-[1.7]', 'empty:before:text-faint empty:before:content-[attr(data-placeholder)]')"
    :data-placeholder="placeholder"
    :aria-invalid="invalid"
    :aria-describedby="invalid && id ? `${id}-error` : undefined"
    contenteditable="true"
    role="textbox"
    :aria-multiline="!singleLine"
    @input="changed"
    @compositionend="changed"
    @paste="paste"
    @drop="drop"
    @blur="remember"
    @keydown.enter="enter"
  />
</template>
