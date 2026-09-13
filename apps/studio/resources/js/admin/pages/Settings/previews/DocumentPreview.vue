<script setup lang="ts">
import { computed } from 'vue'
import PreviewFrame from './PreviewFrame.vue'

/**
 * The one sheet Rechtliches, Bankverbindung, Zahlungsdaten & USt and
 * Dokumente all land on — four panes whose fields were, until now, only
 * visible in a rendered PDF. Index.vue merges the live form on top of the
 * saved settings, so the pane being edited moves this in place.
 */
const props = defineProps<{
  doc: {
    displayName: string
    logoUrl: string | null
    fontPair: string
    accentColor: string | null
    legalName: string
    legalForm: string
    street: string
    postcode: string
    city: string
    representative: string
    taxNumber: string
    vatId: string
    registerNumber: string
    registerCourt: string
    bankAccountHolder: string
    bankIban: string
    bankBic: string
    vatMode: string
    vatRate: string
    quoteValidityDays: number
    paymentTermDays: number
    depositPercentage: string
    widerrufIncluded: boolean
    quoteLayout: string
  }
}>()

const previewUrl = computed(() =>
  `/einstellungen/dokumente/angebot-vorschau?layout=${encodeURIComponent(props.doc.quoteLayout ?? '')}`)
</script>

<template>
  <PreviewFrame :label="$t('settings.previewDocument')">
    <iframe
      :src="previewUrl"
      class="h-[520px] w-full rounded-md border-0 bg-background"
      sandbox=""
      title="Angebot-Vorschau"
    />
  </PreviewFrame>
</template>
