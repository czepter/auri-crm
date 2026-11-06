<script setup lang="ts">
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import PaperSheet from '@/admin/components/quote/PaperSheet.vue'
import { ChoiceGroup } from '@/ui/choice-group'
import { buildQuotePayload, type QuoteDraft, type VatSettings } from './quotePayload'
import QuotePortalPreview from './QuotePortalPreview.vue'

// Server-rendered only — unlike DocumentPreviewPane there is no local instant
// paint here, that half belongs to Editor.js and has no counterpart on a
// quote draft.
//
// `draft` is only what a draft edits — a sent/accepted/superseded quote has
// none, and its sheet never changes underneath it (Quote::booted() sees to
// that), so it fetches the stored quote once over GET instead of re-posting
// a draft on every keystroke.
const props = defineProps<{
  quoteId: string
  draft?: QuoteDraft
  canManageCatalog?: boolean
  // Nur für den Portal-Modus: die Anrede-Zeile, die der Kunde über dem
  // Angebot sieht (portal/pages/Quotes/Show.vue's project.name), und was
  // Quote::computeVat() aus den aktuellen Studio-Einstellungen macht.
  recipientName?: string
  vatSettings?: VatSettings
}>()

const mode = ref<'pdf' | 'portal'>('portal')

const html = ref('')
const stale = ref(true)
const fit = ref(true)

// useDebounceFn only cancels a pending, not-yet-started call — a monotonic
// token is how a later edit's response wins even if it lands first.
let requestId = 0

const refreshFromServer = useDebounceFn(async () => {
  const id = ++requestId
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
  const body = buildQuotePayload(props.draft as QuoteDraft, { canManageCatalog: props.canManageCatalog ?? false })

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

// The stored quote's own sheet, fetched exactly once — nothing to debounce
// or re-post, since there is no draft here to change it.
async function loadStoredSheet() {
  const response = await fetch(`/angebote/${props.quoteId}/vorschau`, { headers: { Accept: 'text/html' } })

  if (response.ok) html.value = await response.text()
}

if (props.draft) {
  watch(
    () => [props.draft, props.canManageCatalog],
    () => {
      stale.value = true
      refreshFromServer()
    },
    { deep: true, immediate: true },
  )
} else {
  stale.value = false
  loadStoredSheet()
}
</script>

<template>
  <section class="flex min-w-0 flex-col">
    <div class="flex items-center gap-2.5 border-b border-border pb-2.5">
      <span class="mb-[5px] text-small leading-none font-semibold text-faint">{{ $t('quotes.preview') }}</span>

      <ChoiceGroup
        :model-value="mode"
        :options="[{ value: 'pdf', label: $t('quotes.previewPdf') }, { value: 'portal', label: $t('quotes.previewPortal') }]"
        variant="segmented"
        class="ml-2.5"
        @update:model-value="(value) => (mode = value as 'pdf' | 'portal')"
      />

      <div v-if="mode === 'pdf'" class="ml-auto flex items-center gap-1.5">
        <button
          v-for="option in [{ key: 'fit', on: true, label: 'quotes.zoomFit' }, { key: 'full', on: false, label: 'quotes.zoomFull' }]"
          :key="option.key"
          class="cursor-pointer rounded-lg border-0 px-2.5 py-[5px] text-small leading-[1.3]"
          :class="fit === option.on ? 'bg-foreground text-background' : 'bg-sunk-2 text-foreground'"
          type="button"
          @click="fit = option.on"
        >{{ $t(option.label) }}</button>
      </div>
      <span v-if="mode === 'pdf' && draft" class="ml-2.5 text-small leading-none" :class="stale ? 'text-due' : 'text-faint'">{{
        stale ? $t('quotes.previewStale') : $t('quotes.previewFresh')
      }}</span>
    </div>

    <PaperSheet v-if="mode === 'pdf'" :html="html" :fit="fit" />

    <div v-else class="mt-6 flex-1 overflow-auto rounded-md border border-border bg-sunk p-4">
      <QuotePortalPreview
        v-if="draft && vatSettings"
        :draft="draft"
        :project-name="recipientName ?? ''"
        :vat-settings="vatSettings"
        class="rounded-[10px]"
      />
    </div>
  </section>
</template>
