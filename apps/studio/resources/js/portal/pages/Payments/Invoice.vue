<script setup lang="ts">
/*
 * #419. Die Rechnung als Seite, gebaut wie Quotes/Show.vue und mit denselben
 * ROW-Klassen: was im PDF in der Summenkette steht, steht hier in derselben
 * Reihenfolge. Die Zahlen kommen fertig formatiert vom Server — aus
 * demselben InvoiceDocument, aus dem die fünf Drucklayouts lesen.
 */
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import InvoiceSheet, { type InvoicePage } from '@/portal/components/InvoiceSheet.vue'
import PortalDot from '@/portal/components/PortalDot.vue'
import PortalBack from '@/portal/components/PortalBack.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { Button } from '@/ui/button'

const props = defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  project: { id: string; name: string }
  invoice: InvoicePage & {
    id: string
    number: string | null
    status: string
    statusLabel: string
    hasDocument: boolean
    creditNotes: { id: string; number: string }[]
  }
  payInstallmentId: string | null
}>()

const paying = ref(false)

function payNow() {
  paying.value = true
  router.post(`/projekte/${props.project.id}/zahlungen/${props.payInstallmentId}/jetzt-zahlen`, {}, {
    onFinish: () => (paying.value = false),
  })
}
</script>

<template>
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: showSwitcher }" />

    <PortalWrap class="pb-40">
      <section class="pt-[var(--band)]">
        <PortalBack :href="`/projekte/${project.id}/zahlungen`" />
        <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.name }}</p>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.invoiceTitle', { number: invoice.number }) }}
        </h1>
      </section>

      <div class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 max-w-[680px] border-t border-border pt-8">
        <div class="flex flex-wrap items-baseline justify-between gap-4">
          <span class="inline-flex items-center gap-2 text-[13px] text-muted-foreground">
            <PortalDot :state="invoice.status === 'paid' ? 'done' : 'open'" />{{ invoice.statusLabel }}
          </span>
        </div>

        <InvoiceSheet :invoice="invoice" />

        <div class="mt-8 flex flex-wrap items-center gap-4">
          <a
            v-if="invoice.hasDocument"
            class="text-[13px] whitespace-nowrap text-muted-foreground hover:text-foreground"
            :href="`/projekte/${project.id}/zahlungen/${invoice.id}/rechnung`"
            target="_blank"
          >{{ $t('portal.invoiceDownload', { number: invoice.number }) }}</a>

          <a
            v-for="note in invoice.creditNotes"
            :key="note.id"
            class="text-[13px] whitespace-nowrap text-muted-foreground hover:text-foreground"
            :href="`/projekte/${project.id}/zahlungen/${invoice.id}/gutschrift/${note.id}`"
            target="_blank"
          >{{ $t('portal.creditNoteDownload', { number: note.number }) }}</a>

          <Button v-if="payInstallmentId" variant="portal" type="button" :disabled="paying" @click="payNow">
            {{ $t('portal.payNowButton') }}
          </Button>
        </div>
      </div>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
