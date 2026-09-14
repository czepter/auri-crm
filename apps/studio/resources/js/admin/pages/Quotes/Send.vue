<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import QuoteSendView from '@/admin/components/quote/QuoteSendView.vue'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument } from '@/admin/documents/blocks/types'

const props = defineProps<{
  projectId: string
  quote: App.Quotes.Data.QuoteData
  studioName: string
  recipientName: string
  recipientEmail: string
}>()

function cancel() {
  router.visit(`/auftraege/${props.projectId}`)
}

function send({ subject, message }: { subject: string; message: EditorDocument }) {
  router.post(
    `/angebote/${props.quote.id}/versenden`,
    { subject, message: toPlainText(message) },
    { onSuccess: () => router.visit(`/auftraege/${props.projectId}`) },
  )
}
</script>

<template>
  <QuoteSendView
    :quote="quote"
    :studio-name="studioName"
    :recipient-name="recipientName"
    :recipient-email="recipientEmail"
    @cancel="cancel"
    @send="send"
  />
</template>
