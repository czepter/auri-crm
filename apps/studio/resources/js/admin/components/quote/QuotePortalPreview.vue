<script setup lang="ts">
import { computed } from 'vue'
import { formatEuros, formatPercent } from '@/lib/money'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import QuoteSlider from '@/portal/components/QuoteSlider.vue'
import { shortDate } from '@/portal/lib/dates'
import { splitStages } from '@/portal/lib/paymentStages'
import type { QuoteDraft, VatSettings } from './quotePayload'
import { buildPreviewQuote } from './quotePayload'

// Was ein Kunde unter seinem Teilen-Link sieht — siehe
// portal/pages/Quotes/Show.vue, dessen Kopf, Fuß und Statuszeile hier fehlen:
// die Vorschau steckt schon im Editor, sie hat weder eigene Navigation noch
// einen Entwurfsstatus, den der Kunde nie sieht.
const props = defineProps<{ draft: QuoteDraft; projectName: string; vatSettings: VatSettings }>()

const quote = computed(() => buildPreviewQuote(props.draft, props.vatSettings))

// Choice-Modus zeigt seine eigene Aufstellung schon in QuoteSlider — das
// hier ist nur die klassische Tabelle gegen denselben Bruttobetrag.
const stages = computed(() => {
  const gross = quote.value.net + (quote.value.vatMode === 'standard' ? quote.value.vatAmount : 0)

  return splitStages(gross, quote.value.paymentStages)
})

const ROW = '[&>td]:py-2 [&>td]:align-top [&>td]:text-ui [&>td]:leading-[1.5] [&>td:last-child]:text-right [&>td:last-child]:tabular-nums [&>td:last-child]:whitespace-nowrap'
</script>

<template>
  <div data-tokens data-theme="portal" class="bg-background text-body leading-[1.6] text-foreground">
    <PortalWrap class="py-10">
      <section>
        <p class="mb-4 text-small leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ projectName }}</p>
        <h1 class="font-serif text-[clamp(32px,20px+1.6vw,44px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.quoteTitle') }}
        </h1>
      </section>

      <div v-if="quote.intro" class="mt-8 max-w-[62ch] text-body leading-[1.6] [&_p+*]:mt-3 [&_*+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5" v-html="quote.intro" />

      <QuoteSlider
        v-if="quote.hourlyRate !== null"
        class="mt-10"
        :project="{ id: 'preview', name: projectName }"
        :quote="quote"
        :can-accept="false"
        :expired="false"
        :accepted-at="null"
        :showcase-count="0"
      />

      <div v-else class="mt-10 max-w-[680px] border-t border-border pt-8">
        <table class="w-full border-collapse">
          <tr v-for="item in quote.items" :key="item.id" :class="[ROW, (item.isIncluded || (item.selectable && !item.selected)) && '[&>td]:text-muted-foreground']">
            <td>
              <template v-if="item.selectable && !item.selected">{{ $t('quotes.optional') }}: </template>{{ item.title }}
              <div v-if="item.description" class="text-small leading-[1.45] text-muted-foreground">
                {{ item.description }}
              </div>
            </td>
            <td>{{ item.isIncluded ? $t('quotes.includedPrice') : formatEuros(item.netTotal) }}</td>
          </tr>

          <tr :class="[ROW, '[&>td]:border-t [&>td]:border-border [&>td]:pt-3! [&>td]:font-semibold']">
            <td>{{ $t(quote.discountAmount ? 'quotes.subtotal' : quote.vatMode === 'standard' ? 'quotes.net' : 'quotes.total') }}</td>
            <td>{{ formatEuros(quote.total) }}</td>
          </tr>
          <template v-if="quote.discountAmount">
            <tr :class="[ROW, '[&>td]:py-1! [&>td]:text-muted-foreground']">
              <td>{{ $t('quotes.discount') }}</td>
              <td>−{{ formatEuros(quote.discountAmount) }}</td>
            </tr>
            <tr :class="[ROW, '[&>td]:pt-2! [&>td]:font-serif [&>td]:text-heading!']">
              <td>{{ $t('quotes.net') }}</td>
              <td>{{ formatEuros(quote.net) }}</td>
            </tr>
          </template>

          <tr v-if="quote.vatMode === 'small_business'" :class="[ROW, '[&>td]:py-1! [&>td]:text-small! [&>td]:text-muted-foreground']">
            <td colspan="2">{{ quote.vatNote }}</td>
          </tr>
          <template v-else-if="quote.vatMode === 'standard'">
            <tr :class="[ROW, '[&>td]:py-1! [&>td]:text-muted-foreground']">
              <td>{{ $t('quotes.vat', { rate: formatPercent(quote.vatRate) }) }}</td>
              <td>{{ formatEuros(quote.vatAmount) }}</td>
            </tr>
            <tr :class="[ROW, '[&>td]:pt-2! [&>td]:font-serif [&>td]:text-heading!']">
              <td>{{ $t('quotes.total') }}</td>
              <td>{{ formatEuros(quote.net + quote.vatAmount) }}</td>
            </tr>
          </template>
        </table>

        <table v-if="stages.length" class="mt-8 w-full max-w-[420px] border-collapse">
          <caption class="pb-2 text-left text-small leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">
            {{ $t('quotes.payment') }}
          </caption>
          <tr v-for="stage in stages" :key="stage.label" :class="[ROW, '[&>td]:text-muted-foreground']">
            <td>{{ stage.label }}</td>
            <td>{{ formatEuros(stage.amount) }}</td>
          </tr>
        </table>

        <div v-if="quote.outro" class="mt-8 max-w-[62ch] text-body leading-[1.6] [&_p+*]:mt-3 [&_*+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5" v-html="quote.outro" />

        <p v-if="quote.validUntil" class="mt-8 text-small text-muted-foreground">
          {{ $t('quotes.validUntil') }} {{ shortDate(quote.validUntil) }}
        </p>
      </div>
    </PortalWrap>
  </div>
</template>
