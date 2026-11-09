<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import SendByMailView from '@/admin/components/templates/SendByMailView.vue'
import { toast } from 'vue-sonner'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument } from '@/admin/documents/blocks/types'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  projectId: string
  quote: App.Quotes.Data.QuoteData
  recipientName: string
  recipientEmail: string
  address: 'sie' | 'du' | 'ihr'
  subject: string | null
  message: string | null
  mailHtml: string
  followUp?: boolean
  mailTemplates?: App.Documents.Data.MailTemplateData[]
  mergeFields?: { path: string; sample: string }[]
  shootKinds?: string[]
  occasion?: string
}>()
useLiveProps([])

const fallback: EditorDocument = {
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

const message: EditorDocument = props.message === null
  ? fallback
  : { blocks: props.message.split('\n\n').map((text) => ({ type: 'paragraph', data: { text } })) }

const back = () => (props.followUp ? `/anfragen/${props.projectId}` : `/auftraege/${props.projectId}`)

function cancel() {
  router.visit(back())
}

const processing = ref(false)

function send({ subject, message }: { subject: string; message: EditorDocument }) {
  router.post(
    `/angebote/${props.quote.id}/${props.followUp ? 'nachfassen' : 'versenden'}`,
    { subject, message: toPlainText(message) },
    {
      onStart: () => (processing.value = true),
      onFinish: () => (processing.value = false),
      onSuccess: () => router.visit(back()),
      onError: (errors) => toast.error(errors.quote),
    },
  )
}
</script>

<template>
  <SendByMailView
    :recipients="[{ name: recipientName, email: recipientEmail }]"
    :subject="subject ?? $t('quotes.title')"
    :message="message"
    :mail-html="mailHtml"
    :templates="mailTemplates"
    :merge-fields="mergeFields"
    :shoot-kinds="shootKinds"
    :save-as="occasion ? { occasion, quote: quote.id } : undefined"
    :processing="processing"
    @cancel="cancel"
    @send="send"
  />
</template>
