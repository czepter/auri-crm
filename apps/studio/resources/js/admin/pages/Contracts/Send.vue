<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import SendByMailView from '@/admin/components/SendByMailView.vue'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument } from '@/admin/documents/blocks/types'

const props = defineProps<{
  projectId: string
  contract: { id: string; templateName: string }
  studioName: string
  recipients: { name: string; email: string }[]
}>()

const message: EditorDocument = {
  blocks: [
    { type: 'paragraph', data: { text: `Hallo ${props.recipients.map((recipient) => recipient.name).join(' und ')},` } },
    { type: 'paragraph', data: { text: 'hier euer Vertrag zur Unterschrift.' } },
  ],
}

function cancel() {
  router.visit(`/auftraege/${props.projectId}/vertrag`)
}

function send({ subject, message }: { subject: string; message: EditorDocument }) {
  router.post(`/vertraege/${props.contract.id}/versenden`, { subject, message: toPlainText(message) })
}
</script>

<template>
  <SendByMailView
    :studio-name="studioName"
    :recipients="recipients"
    :subject="$t('project.contract')"
    :message="message"
    :cta="$t('sendMail.contractCta')"
    @cancel="cancel"
    @send="send"
  >
    <template #card>
      <div class="flex items-baseline justify-between px-[18px] py-3.5">
        <span class="text-[13px] font-semibold">{{ $t('project.contract') }}</span>
        <span class="text-[11.5px] text-faint">{{ contract.templateName }}</span>
      </div>
    </template>
  </SendByMailView>
</template>
