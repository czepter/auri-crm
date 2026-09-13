<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import QuoteSendView from '@/admin/components/quote/QuoteSendView.vue'
import type { EditorDocument } from '@/admin/documents/blocks/types'

const props = defineProps<{
  projectId: string
  quote: App.Quotes.Data.QuoteData
  studioName: string
  recipientName: string
  recipientEmail: string
}>()

// The Editor.js field carries paragraph blocks; QuoteController::send()
// takes plain text (App.Quotes.Actions.SendQuote's mail, like every other
// mail body in the app, is plain lines rather than HTML) — so inline markup
// is stripped rather than serialized.
function toPlainText(doc: EditorDocument): string {
  return doc.blocks
    .map((block) => String(block.data.text ?? '').replace(/<[^>]+>/g, ''))
    .filter((line) => line !== '')
    .join('\n\n')
}

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
