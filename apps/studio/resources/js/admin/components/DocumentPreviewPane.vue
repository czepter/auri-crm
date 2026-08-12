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
      body: JSON.stringify({ type: props.type, body }),
    })

    if (!response.ok) return

    // A slower earlier request must not overwrite a newer answer: the local
    // paint is allowed to lag because it announces itself as provisional, but
    // a server answer that is silently behind would be indistinguishable from
    // truth.
    if (id !== requestId) return

    html.value = await response.text()
    stale.value = false
  } catch {
    // Offline or the request died: the preview is genuinely unconfirmed, so
    // `stale` stays true rather than being reset to a freshness we cannot claim.
  }
}, 400)

watch(
  () => [props.doc, props.type],
  () => {
    stale.value = true
    html.value = fillSamples(serialize(props.doc, props.type === 'mail' ? 'mail' : 'print'), props.samples)
    refreshFromServer()
  },
  { deep: true, immediate: true },
)
</script>

<template>
  <section class="doc-pane">
    <div class="doc-panehead">
      <span class="kick">{{ $t('documentTemplates.previewLabel') }}</span>
      <span class="doc-live" :class="{ stale }">{{
        stale ? $t('documentTemplates.previewStale') : $t('documentTemplates.previewFresh')
      }}</span>
    </div>
    <!-- sandbox="" (no allow-* tokens at all): a template body is
         staff-authored HTML, not trusted script — an empty sandbox strips
         script execution, form submission and top-level navigation, so a
         <script> in a preview can never run with this session's origin. -->
    <iframe class="doc-previewframe" :srcdoc="html" sandbox=""></iframe>
  </section>
</template>
