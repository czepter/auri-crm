<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import SendByMailView from '@/admin/components/SendByMailView.vue'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument } from '@/admin/documents/blocks/types'

const props = defineProps<{
  contactId: string
  studioName: string
  recipients: { name: string; email: string }[]
}>()

const message: EditorDocument = {
  blocks: [
    { type: 'paragraph', data: { text: `Hallo ${props.recipients[0]?.name ?? ''},` } },
    { type: 'paragraph', data: { text: 'hier euer Zugang zum Kundenbereich.' } },
  ],
}

function cancel() {
  router.visit(`/kontakte/${props.contactId}`)
}

function send({ subject, message }: { subject: string; message: EditorDocument }) {
  router.post(`/kontakte/${props.contactId}/portal-zugang/versenden`, { subject, message: toPlainText(message) })
}
</script>

<template>
  <SendByMailView
    :studio-name="studioName"
    :recipients="recipients"
    :subject="$t('contacts.portalAccess')"
    :message="message"
    :cta="$t('sendMail.portalCta')"
    @cancel="cancel"
    @send="send"
  />
</template>
