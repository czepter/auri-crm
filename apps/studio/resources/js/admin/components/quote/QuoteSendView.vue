<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import SendByMailView from '@/admin/components/SendByMailView.vue'
import { basisPointsToPercent, formatEuros } from '@/admin/lib/money'
import { monthDate } from '@/admin/lib/dates'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

const props = withDefaults(
  defineProps<{
    quote: App.Quotes.Data.QuoteData
    studioName: string
    recipientName: string
    recipientEmail: string
    subject?: string
    message?: EditorDocument
    palette?: PaletteEntry[]
    mergeFields?: { path: string; sample: string }[]
  }>(),
  { palette: () => [], mergeFields: () => [] },
)

const emit = defineEmits<{ cancel: []; send: [{ subject: string; message: EditorDocument }] }>()

const { t } = useI18n()

const message: EditorDocument = props.message ?? {
  blocks: [
    { type: 'paragraph', data: { text: `Hallo ${props.recipientName},` } },
    { type: 'paragraph', data: { text: 'vielen Dank für euer Vertrauen — hier euer Angebot.' } },
  ],
}

// QuoteWorkspace.vue's own summaryRows: a draft ends at Gesamt or Netto
// (vatMode is only set once a quote is sent), a sent one adds discount and
// USt rows exactly as its capsule does — this card is that same figure, not
// a second one that can drift from it.
const summaryRows = computed(() => {
  const rows = props.quote.discountAmount
    ? [
        { label: t('quotes.total'), value: formatEuros(props.quote.total) },
        { label: t('quotes.discount'), value: `−${formatEuros(props.quote.discountAmount)}` },
        { label: t('quotes.net'), value: formatEuros(props.quote.net) },
      ]
    : [{ label: t('quotes.total'), value: formatEuros(props.quote.total) }]

  if (props.quote.vatMode === 'standard') {
    rows.push(
      { label: t('quotes.vat', { rate: basisPointsToPercent(props.quote.vatRate) }), value: formatEuros(props.quote.vatAmount ?? 0) },
      { label: t('quotes.total'), value: formatEuros(props.quote.net + (props.quote.vatAmount ?? 0)) },
    )
  }

  return rows.map((row, index) => ({ ...row, last: index === rows.length - 1 }))
})
</script>

<template>
  <SendByMailView
    :studio-name="studioName"
    :recipient-name="recipientName"
    :recipient-email="recipientEmail"
    :subject="subject ?? $t('quotes.title')"
    :message="message"
    :cta="$t('quotes.sendCta')"
    :palette="palette"
    :merge-fields="mergeFields"
    @cancel="emit('cancel')"
    @send="emit('send', $event)"
  >
    <template #note>{{ $t('sendMail.pdfNote') }}</template>

    <template #card>
      <div class="flex items-baseline justify-between border-b border-border px-[18px] py-3.5">
        <span class="text-[13px] font-semibold">{{ $t('quotes.title') }}</span>
        <span v-if="quote.validUntil" class="text-[11.5px] text-faint">{{ $t('quotes.validUntilInline', { date: monthDate(quote.validUntil) }) }}</span>
      </div>
      <div class="px-[18px]">
        <div
          v-for="item in quote.items"
          :key="item.id"
          class="flex gap-2.5 border-b border-border py-[11px] text-[12.5px] last:border-b-0"
        >
          <span class="flex-1">{{ item.title }}</span>
          <span class="font-semibold tabular-nums">{{ item.isIncluded ? $t('quotes.includedPrice') : formatEuros(item.netTotal) }}</span>
        </div>
        <div
          v-for="(row, index) in summaryRows"
          :key="`${row.label}-${index}`"
          class="flex gap-2.5 py-2"
          :class="row.last ? 'text-[14px] font-semibold' : 'text-[12.5px]'"
        >
          <span class="flex-1">{{ row.label }}</span>
          <span class="tabular-nums">{{ row.value }}</span>
        </div>
      </div>
    </template>
  </SendByMailView>
</template>
