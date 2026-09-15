<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalRow from '@/portal/components/PortalRow.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { Button } from '@/ui/button'

type Installment = {
  id: string
  amount: number
  dueOn: string
  status: string
  statusLabel: string
  label: string
  invoiceNumber: string | null
  hasInvoiceDocument: boolean
  creditNotes: { id: string; number: string; amount: number; issuedOn: string; hasDocument: boolean }[]
}

type Plan = {
  id: string
  total: number
  openTotal: number
  reference: string | null
  installments: Installment[]
}

const props = defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  project: { id: string; name: string }
  plan: Plan | null
  stripeEnabled: boolean
  paymentStatus: 'success' | 'cancelled' | null
  bankDetails: { accountHolder: string | null; iban: string | null; bic: string | null }
}>()

// M-04: one button per still-open installment — "Jetzt zahlen" pays a
// specific installment, never the plan as a whole. The click itself never
// talks to Stripe; it posts to PaymentCheckoutController, which creates (or
// reuses) the Checkout Session and sends the browser there.
// #408: eine stornierte Rate ist weder offen noch bezahlt — sie bleibt
// lesbar und fällt aus jedem Betrag und jedem Knopf heraus.
const openInstallments = computed(
  () => props.plan?.installments.filter((installment) => !['paid', 'cancelled'].includes(installment.status)) ?? [],
)
const payingId = ref<string | null>(null)

// M-06: what a still-open installment should be transferred under. Only
// issued ones have a number to name, which is why an unissued plan says the
// invoice is coming rather than printing an empty reference line.
const referencedInstallments = computed(
  () => openInstallments.value.filter((installment) => installment.invoiceNumber !== null),
)

function payNow(installmentId: string) {
  payingId.value = installmentId
  router.post(`/projekte/${props.project.id}/zahlungen/${installmentId}/jetzt-zahlen`, {}, {
    onFinish: () => (payingId.value = null),
  })
}

// The one euro/cent boundary this page crosses — see resources/js/admin/lib/
// money.ts for the admin-side twin of this. Inlined rather than imported: no
// portal page has needed a shared money helper yet, and importing across the
// admin/portal bundle split would blur the surface-isolation the two
// entrypoints otherwise keep (see resources/js/portal/main.ts's own note).
function formatEuros(cents: number) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(cents / 100)
}

function longDate(date: string) {
  return new Date(date).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}


const BAND = 'max-w-[880px] border-t border-border pt-12'
</script>

<template>
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: showSwitcher }" />

    <PortalWrap class="pb-40">
      <section class="pt-[var(--band)]">
        <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.name }}</p>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.paymentsTitle') }}
        </h1>
        <p v-if="plan && plan.openTotal" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-4 text-[17px] tabular-nums text-action">
          {{ $t('portal.paymentsOpen', { amount: formatEuros(plan.openTotal) }) }}
        </p>
      </section>

      <template v-if="plan">
        <p v-if="paymentStatus" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-8 max-w-[880px] rounded-md bg-sunk p-6 text-[15px]">
          {{ paymentStatus === 'success' ? $t('portal.payNowSuccess') : $t('portal.payNowCancelled') }}
        </p>

        <!-- One list, not two. The card-payment block used to repeat every open
             installment a second time under its own heading. -->
        <section class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12">
          <div :class="BAND">
            <PortalLabel class="mb-6">{{ $t('portal.installmentsTitle') }}</PortalLabel>
            <PortalRow
              v-for="installment in plan.installments"
              :key="installment.id"
              :title="installment.label"
              :state="installment.status === 'paid' || installment.status === 'cancelled' ? 'done' : 'open'"
              :note="installment.status === 'paid' || installment.status === 'cancelled'
                ? installment.statusLabel
                : $t('portal.dueOn', { date: longDate(installment.dueOn) })"
              :aside="formatEuros(installment.amount)"
              :aside-open="!['paid', 'cancelled'].includes(installment.status)"
            >
              <template #action>
                <span class="flex flex-wrap items-center gap-4">
                  <!-- #419: auf die Seite, nicht aufs PDF. Erst ab der
                       Nummer — vorher gibt es keine Rechnung zu zeigen. -->
                  <Link
                    v-if="installment.invoiceNumber !== null"
                    class="text-[13px] whitespace-nowrap text-muted-foreground hover:text-foreground"
                    :href="`/projekte/${project.id}/zahlungen/${installment.id}`"
                  >{{ $t('portal.invoiceDownload', { number: installment.invoiceNumber }) }}</Link>
                  <Button
                    v-if="stripeEnabled && !['paid', 'cancelled'].includes(installment.status)"
                    variant="portal-chip"
                    type="button"
                    :disabled="payingId === installment.id"
                    @click="payNow(installment.id)"
                  >
                    {{ $t('portal.payNowButton') }}
                  </Button>
                </span>
              </template>
            </PortalRow>
          </div>
        </section>

        <!-- "Überweisen" renders whenever the studio filed an IBAN, Stripe on
             or off (T-07's own acceptance: a client can always see what to pay,
             to which account and under which reference). Bank details are
             optional in settings, and half a transfer block — labels with
             nothing behind them — is worse than none. -->
        <section v-if="bankDetails.iban" class="animate-rise [animation-delay:320ms] motion-reduce:animate-none mt-12">
          <div :class="BAND">
            <PortalLabel class="mb-6">{{ $t('portal.transferTitle') }}</PortalLabel>
            <dl class="m-0 grid max-w-[560px] grid-cols-[148px_1fr] gap-x-6 gap-y-3 max-[700px]:grid-cols-1 max-[700px]:gap-y-0">
              <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.transferAccountHolder') }}</dt>
              <dd class="m-0 text-[14.5px]">{{ bankDetails.accountHolder }}</dd>
              <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.transferIban') }}</dt>
              <dd class="m-0 text-[14.5px] tabular-nums">{{ bankDetails.iban }}</dd>
              <dt v-if="bankDetails.bic" class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.transferBic') }}</dt>
              <dd v-if="bankDetails.bic" class="m-0 text-[14.5px] tabular-nums">{{ bankDetails.bic }}</dd>

              <!-- M-06: each open installment transfers under its own invoice
                   number. `plan.reference` is the pre-M-06 fallback and is null
                   for every plan created since. -->
              <template v-for="installment in referencedInstallments" :key="installment.id">
                <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.transferReference') }}</dt>
                <dd class="m-0 text-[14.5px] tabular-nums">{{ installment.invoiceNumber }} ({{ installment.label }})</dd>
              </template>
              <template v-if="!referencedInstallments.length">
                <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.transferReference') }}</dt>
                <dd class="m-0 text-[14.5px] tabular-nums">
                  {{ plan.reference ?? $t('portal.transferReferencePending') }}
                </dd>
              </template>
            </dl>
          </div>
        </section>
      </template>

      <p v-else class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 text-[15px] text-muted-foreground">{{ $t('portal.paymentsNone') }}</p>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
