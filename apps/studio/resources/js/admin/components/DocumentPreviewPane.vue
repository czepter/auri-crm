<script setup lang="ts">
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { serialize } from '@/admin/documents/blocks/serialize'
import { fillSamples } from '@/admin/documents/blocks/importHtml'
import type { EditorDocument } from '@/admin/documents/blocks/types'

// Two-speed preview. The local paint keeps the sheet alive while typing; the
// server answer is the one that counts, because it runs the real
// MergeFieldResolver and DocumentRenderer — the same pair RenderContractJob
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

const html = ref('')
const stale = ref(false)

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
      <span class="ml-auto text-[11px] leading-none font-normal" :class="stale ? 'text-due' : 'text-faint'">{{
        stale ? $t('documentTemplates.previewStale') : $t('documentTemplates.previewFresh')
      }}</span>
    </div>
    <!-- sandbox="" (no allow-* tokens at all): a template body is
         staff-authored HTML, not trusted script — an empty sandbox strips
         script execution, form submission and top-level navigation, so a
         <script> in a preview can never run with this session's origin. -->
    <iframe
      class="mt-6 min-h-[520px] w-full flex-1 rounded-[2px] border border-border bg-white max-[1100px]:min-h-[360px]" :srcdoc="html" sandbox=""></iframe>
  </section>
</template>
