<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import SendByMailView from '@/admin/components/SendByMailView.vue'
import QuoteCard from '@/admin/components/quote/QuoteCard.vue'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument } from '@/admin/documents/blocks/types'
import { formatEuros } from '@/admin/lib/money'

const props = defineProps<{
  projectId: string
  installmentId: string
  fromList: boolean
  invoice: {
    number: string
    date: string | null
    amount: number
    statusLabel: string
  }
  recipients: { name: string; email: string }[]
  mailHtml: string
}>()

const message: EditorDocument = {
  blocks: [
    { type: 'paragraph', data: { text: `Hallo ${props.recipients.map((recipient) => recipient.name).join(' und ')},` } },
    { type: 'paragraph', data: { text: `anbei die Rechnung ${props.invoice.number}.` } },
  ],
}

const cardOpen = ref(true)

function cancel() {
  router.visit(props.fromList ? '/rechnungen' : `/auftraege/${props.projectId}`)
}

function send({ subject, message }: { subject: string; message: EditorDocument }) {
  router.post(`/raten/${props.installmentId}/rechnung/versenden`, { subject, message: toPlainText(message), liste: props.fromList })
}
</script>

<template>
  <SendByMailView
    :recipients="recipients"
    :subject="`${$t('payments.invoice')} ${invoice.number}`"
    :message="message"
    :mail-html="mailHtml"
    @cancel="cancel"
    @send="send"
  >
    <template #document>
      <QuoteCard
        v-model:open="cardOpen"
        :title="`${$t('payments.invoice')} ${invoice.number}`"
        :summary="formatEuros(invoice.amount)"
      >
        <dl class="m-0 grid grid-cols-[120px_1fr] gap-x-5 gap-y-2 text-[12.5px]">
          <dt class="text-ink-2">{{ $t('payments.invoiceDate') }}</dt>
          <dd class="m-0">{{ invoice.date }}</dd>
          <dt class="text-ink-2">{{ $t('payments.invoiceAmount') }}</dt>
          <dd class="m-0 tabular-nums">{{ formatEuros(invoice.amount) }}</dd>
          <dt class="text-ink-2">{{ $t('payments.status') }}</dt>
          <dd class="m-0">{{ invoice.statusLabel }}</dd>
        </dl>
      </QuoteCard>
    </template>
  </SendByMailView>
</template>
