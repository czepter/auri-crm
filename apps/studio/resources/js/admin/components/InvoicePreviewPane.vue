<script setup lang="ts">
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import PaperSheet from '@/admin/components/PaperSheet.vue'
import InvoiceSheet, { type InvoicePage } from '@/portal/components/InvoiceSheet.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { ChoiceGroup } from '@/ui/choice-group'

// #480: the three things a client gets from one invoice — the PDF, the portal
// page and the mail — each rendered by the server from the same draft.
const props = defineProps<{ payload: Record<string, unknown> }>()

type Mode = 'pdf' | 'portal' | 'mail'

const mode = ref<Mode>('pdf')
const fit = ref(true)
const stale = ref(true)
const html = ref('')
const page = ref<InvoicePage | null>(null)

// A later edit's response wins even if it lands first.
let requestId = 0

const refresh = useDebounceFn(async () => {
  const id = ++requestId
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

  try {
    const response = await fetch('/rechnungen/vorschau', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: mode.value === 'portal' ? 'application/json' : 'text/html' },
      body: JSON.stringify({ ...props.payload, format: mode.value }),
    })

    if (id !== requestId) return

    if (!response.ok) {
      stale.value = true
      return
    }

    if (mode.value === 'portal') {
      page.value = await response.json()
    } else {
      html.value = await response.text()
    }

    stale.value = false
  } catch {
    if (id === requestId) stale.value = true
  }
}, 400)

watch(
  () => [props.payload, mode.value],
  () => {
    stale.value = true
    refresh()
  },
  { deep: true, immediate: true },
)
</script>

<template>
  <section class="flex min-w-0 flex-col">
    <div class="flex items-center gap-2.5 border-b border-border pb-2.5">
      <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('quotes.preview') }}</span>

      <ChoiceGroup
        :model-value="mode"
        :options="[
          { value: 'pdf', label: $t('quotes.previewPdf') },
          { value: 'portal', label: $t('quotes.previewPortal') },
          { value: 'mail', label: $t('invoicesCreate.previewMail') },
        ]"
        variant="segmented"
        class="ml-2.5"
        @update:model-value="(value) => (mode = value as Mode)"
      />

      <div v-if="mode === 'pdf'" class="ml-auto flex items-center gap-1.5">
        <button
          v-for="option in [{ key: 'fit', on: true, label: 'quotes.zoomFit' }, { key: 'full', on: false, label: 'quotes.zoomFull' }]"
          :key="option.key"
          class="cursor-pointer rounded-lg border-0 px-2.5 py-[5px] text-[12px] leading-[1.3]"
          :class="fit === option.on ? 'bg-foreground text-background' : 'bg-sunk-2 text-foreground'"
          type="button"
          @click="fit = option.on"
        >{{ $t(option.label) }}</button>
      </div>
      <span class="text-[11px] leading-none" :class="[stale ? 'text-due' : 'text-faint', mode === 'pdf' ? 'ml-2.5' : 'ml-auto']">{{
        stale ? $t('quotes.previewStale') : $t('quotes.previewFresh')
      }}</span>
    </div>

    <PaperSheet v-if="mode === 'pdf'" :html="html" :fit="fit" />

    <div v-else-if="mode === 'portal'" class="mt-6 flex-1 overflow-auto rounded-md border border-border bg-sunk p-4">
      <div v-if="page" data-tokens data-theme="portal" class="rounded-[10px] bg-background text-[15px] leading-[1.6] text-foreground">
        <PortalWrap class="py-10">
          <InvoiceSheet :invoice="page" />
        </PortalWrap>
      </div>
    </div>

    <!-- sandbox="": the mail is rendered HTML, not trusted script. -->
    <iframe v-else :srcdoc="html" sandbox="" class="mt-6 min-h-[640px] flex-1 rounded-md border border-border bg-sunk" />
  </section>
</template>
