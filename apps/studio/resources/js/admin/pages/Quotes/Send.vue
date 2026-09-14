<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import SendByMailView from '@/admin/components/SendByMailView.vue'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument } from '@/admin/documents/blocks/types'

const props = defineProps<{
  projectId: string
  quote: App.Quotes.Data.QuoteData
  recipientName: string
  recipientEmail: string
  mailHtml: string
}>()

const message: EditorDocument = {
  blocks: [
    { type: 'paragraph', data: { text: `Hallo ${props.recipientName},` } },
    { type: 'paragraph', data: { text: 'vielen Dank für euer Vertrauen — hier euer Angebot.' } },
  ],
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
  <SendByMailView
    :recipients="[{ name: recipientName, email: recipientEmail }]"
    :subject="$t('quotes.title')"
    :message="message"
    :mail-html="mailHtml"
    @cancel="cancel"
    @send="send"
  />
</template>
