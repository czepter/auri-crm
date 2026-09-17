<script setup lang="ts">
/*
 * #419. Die Rechnung als Seite, mit denselben ROW-Klassen wie Quotes/Show.vue:
 * was im PDF in der Summenkette steht, steht hier in derselben Reihenfolge.
 * #480: auch die Portal-Vorschau beim Anlegen zeigt genau dieses Blatt.
 */
export type InvoicePage = {
  metaRows: [string, string][]
  description: string
  lines: { title: string; quantity: string; unitPrice: string; discount: string; total: string; vatRate: string }[]
  deposits: { number: string; date: string; net: string; vat: string; gross: string }[]
  totalGross: string
  summaryRows: [string, string, boolean][]
  vatNote: string
  paymentLines: string[]
  intro: string
  outro: string
}

defineProps<{ invoice: InvoicePage }>()

const ROW = '[&>td]:py-2 [&>td]:align-top [&>td]:text-[14.5px] [&>td]:leading-[1.5] [&>td:last-child]:text-right [&>td:last-child]:tabular-nums [&>td:last-child]:whitespace-nowrap'
</script>

<template>
  <div>
    <!-- #480: bereinigtes Editor.js-HTML, Felder escaped (InvoiceDocument). -->
    <div v-if="invoice.intro" class="mt-8 max-w-[62ch] text-[15.5px] leading-[1.6] [&_p+*]:mt-3 [&_*+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5" v-html="invoice.intro" />

    <table class="mt-8 w-full max-w-[420px] border-collapse">
      <tr v-for="row in invoice.metaRows" :key="row[0]" :class="[ROW, '[&>td]:py-1! [&>td]:text-muted-foreground']">
        <td>{{ row[0] }}</td>
        <td>{{ row[1] }}</td>
      </tr>
    </table>

    <table class="mt-8 w-full border-collapse">
      <!-- #411: die ganze Leistung, wenn dies die Endrechnung ist, sonst
           die eine Zeile dieser Rate. -->
      <template v-if="invoice.lines.length">
        <tr v-for="(line, index) in invoice.lines" :key="`line-${index}`" :class="ROW">
          <td>
            {{ line.title }}
            <span v-if="line.unitPrice" class="block text-[13px] text-muted-foreground">
              {{ line.quantity }} × {{ line.unitPrice }}<template v-if="line.discount"> · {{ $t('quotes.discount') }} {{ line.discount }}</template>
            </span>
          </td>
          <td>{{ line.total }}</td>
        </tr>
        <tr :class="[ROW, '[&>td]:border-t [&>td]:border-border [&>td]:pt-3!']">
          <td>{{ $t('portal.invoiceServiceTotal') }}</td>
          <td>{{ invoice.totalGross }}</td>
        </tr>
        <template v-if="invoice.deposits.length">
          <tr :class="[ROW, '[&>td]:py-1! [&>td]:text-muted-foreground']">
            <td colspan="2">{{ $t('portal.invoiceDeposits') }}</td>
          </tr>
          <tr v-for="deposit in invoice.deposits" :key="deposit.number" :class="[ROW, '[&>td]:py-1! [&>td]:text-muted-foreground']">
            <td>{{ $t('portal.invoiceDeposit', { number: deposit.number, date: deposit.date }) }}</td>
            <td>−{{ deposit.gross }}</td>
          </tr>
        </template>
      </template>
      <tr v-else :class="ROW">
        <td>{{ invoice.description }}</td>
        <td>{{ invoice.summaryRows[invoice.summaryRows.length - 1]?.[1] }}</td>
      </tr>

      <tr
        v-for="(row, index) in invoice.summaryRows"
        :key="row[0]"
        :class="[
          ROW,
          index === 0 && '[&>td]:border-t [&>td]:border-border [&>td]:pt-3!',
          row[2]
            ? '[&>td]:pt-2! [&>td]:font-serif [&>td]:text-[22px]!'
            : '[&>td]:py-1! [&>td]:text-muted-foreground',
        ]"
      >
        <td>{{ row[0] }}</td>
        <td>{{ row[1] }}</td>
      </tr>

      <tr v-if="invoice.vatNote" :class="[ROW, '[&>td]:py-1! [&>td]:text-[13px]! [&>td]:text-muted-foreground']">
        <td colspan="2">{{ invoice.vatNote }}</td>
      </tr>
    </table>

    <table v-if="invoice.paymentLines.length" class="mt-8 w-full max-w-[420px] border-collapse">
      <tr :class="[ROW, '[&>td]:border-b-0']">
        <td colspan="2" class="text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">
          {{ $t('portal.transferTitle') }}
        </td>
      </tr>
      <tr v-for="line in invoice.paymentLines" :key="line" :class="[ROW, '[&>td]:py-1! [&>td]:text-muted-foreground']">
        <td colspan="2">{{ line }}</td>
      </tr>
    </table>

    <div v-if="invoice.outro" class="mt-8 max-w-[62ch] text-[15.5px] leading-[1.6] [&_p+*]:mt-3 [&_*+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5" v-html="invoice.outro" />
  </div>
</template>
