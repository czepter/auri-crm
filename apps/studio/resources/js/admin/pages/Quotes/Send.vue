<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import SendByMailView from '@/admin/components/SendByMailView.vue'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument } from '@/admin/documents/blocks/types'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  projectId: string
  quote: App.Quotes.Data.QuoteData
  recipientName: string
  recipientEmail: string
  address: 'sie' | 'du' | 'ihr'
  mailHtml: string
}>()
useLiveProps([])

const message: EditorDocument = {
  blocks: [
    { type: 'paragraph', data: { text: `Hallo ${props.recipientName},` } },
    {
      type: 'paragraph',
      data: {
        text: {
          sie: 'vielen Dank für Ihr Vertrauen — hier Ihr Angebot.',
          du: 'vielen Dank für dein Vertrauen — hier dein Angebot.',
          ihr: 'vielen Dank für euer Vertrauen — hier euer Angebot.',
        }[props.address],
      },
    },
  ],
}

function cancel() {
  router.visit(`/auftraege/${props.projectId}`)
}

function send({ subject, message }: { subject: string; message: EditorDocument }) {
  router.post(
    `/angebote/${props.quote.id}/versenden`,
    { subject, message: toPlainText(message) },
    {
      onSuccess: () => router.visit(`/auftraege/${props.projectId}`),
      onError: (errors) => toast.error(errors.quote),
    },
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
