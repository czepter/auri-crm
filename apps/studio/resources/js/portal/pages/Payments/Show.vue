<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
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
const openInstallments = computed(() => props.plan?.installments.filter((installment) => installment.status !== 'paid') ?? [])
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

/* tokens.css `.chip` with portal.css's size step, and the installment row's
   own two lines. Inline rather than a component: this is the second portal
   screen with a chip on it, and the plan extracts at the third. */
</script>

<template>
  <PortalHeader :contact="contact" :show-switcher="showSwitcher" />

  <PortalWrap wide>
    <h1>{{ $t('portal.paymentsTitle') }}</h1>

    <template v-if="plan">
      <section class="mt-8">
        <div>
          <h2>{{ project.name }}</h2>
          <span class="flex-none rounded-[var(--r-sm)] px-[9px] py-1 text-[10.5px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('portal.paymentsOpen', { amount: formatEuros(plan.openTotal) }) }}</span>
        </div>

        <div class="flex flex-col gap-1.5">
          <div v-for="installment in plan.installments" :key="installment.id" class="rounded-md border border-border p-3.5">
            <div class="flex items-center gap-3.5">
              <div class="min-w-0 flex-1 text-[13px] leading-[1.3] font-semibold tracking-[-0.01em]">
                {{ installment.label }}
                <span class="mt-0.5 block text-[11.5px] leading-[1.35] font-normal tracking-normal text-muted-foreground">{{ longDate(installment.dueOn) }}</span>
              </div>
              <span
                class="flex-none rounded-[var(--r-sm)] px-[9px] py-1 text-[10.5px] leading-[1.5] font-semibold tracking-[0.04em] uppercase"
                :class="
                  installment.status === 'paid' ? 'bg-due-bg text-due' : 'bg-muted text-muted-foreground'
                "
              >
                {{ installment.status === 'paid' ? installment.statusLabel : formatEuros(installment.amount) }}
              </span>
            </div>
            <!-- M-06: only once the PDF exists. Between issue and render the
                 number is on the row above as the transfer reference, but
                 there is nothing to download yet. -->
            <a
              v-if="installment.hasInvoiceDocument"
              class="text-[11.5px] leading-[1.45] font-normal text-faint"
              :href="`/projekte/${project.id}/zahlungen/${installment.id}/rechnung`"
              target="_blank"
            >{{ $t('portal.invoiceDownload', { number: installment.invoiceNumber }) }}</a>
          </div>
        </div>
      </section>

      <!-- "Überweisen" renders whenever the studio filed an IBAN, Stripe on
           or off (T-07's own acceptance: a client can always see what to pay,
           to which account and under which reference). Bank details are
           optional in settings, and half a transfer block — labels with
           nothing behind them — is worse than none. No mockup was drawn for
           this screen — structure and tokens follow Contracts/Show.vue's own
           split layout. -->
      <div class="mt-8 grid grid-cols-[minmax(0,1fr)_352px] items-stretch gap-11">
        <div v-if="bankDetails.iban" class="sticky top-5 flex max-h-[calc(100vh-20px-44px)] flex-col gap-5 overflow-y-auto">
          <div class="relative block cursor-default rounded-md border border-border p-3.5 text-inherit no-underline">
            <div class="mb-5 flex items-center justify-between">
              <span class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.transferTitle') }}</span>
            </div>
            <dl class="mt-3.5 text-[12.5px] leading-[1.6] font-normal text-muted-foreground">
              <div><strong>{{ $t('portal.transferAccountHolder') }}:</strong> {{ bankDetails.accountHolder }}</div>
              <div><strong>{{ $t('portal.transferIban') }}:</strong> {{ bankDetails.iban }}</div>
              <div><strong>{{ $t('portal.transferBic') }}:</strong> {{ bankDetails.bic }}</div>
              <!-- M-06: each open installment transfers under its own
                   invoice number. `plan.reference` is the pre-M-06 fallback
                   and is null for every plan created since. -->
              <div v-for="installment in referencedInstallments" :key="installment.id">
                <strong>{{ $t('portal.transferReference') }}:</strong>
                {{ installment.invoiceNumber }} ({{ installment.label }})
              </div>
              <div v-if="!referencedInstallments.length && plan.reference">
                <strong>{{ $t('portal.transferReference') }}:</strong> {{ plan.reference }}
              </div>
              <div v-else-if="!referencedInstallments.length" class="text-[11.5px] leading-[1.45] font-normal text-faint">
                {{ $t('portal.transferReferencePending') }}
              </div>
            </dl>
          </div>
        </div>

        <div v-if="stripeEnabled" class="sticky top-5 flex max-h-[calc(100vh-20px-44px)] flex-col gap-5 overflow-y-auto">
          <div class="relative block cursor-default rounded-md border border-border p-3.5 text-inherit no-underline">
            <div class="mb-5 flex items-center justify-between">
              <span class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.payNowTitle') }}</span>
            </div>

            <p v-if="paymentStatus === 'success'" class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('portal.payNowSuccess') }}</p>
            <p v-else-if="paymentStatus === 'cancelled'" class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('portal.payNowCancelled') }}</p>


            <div v-if="openInstallments.length" class="mt-3.5 flex flex-col gap-1.5">
              <div v-for="installment in openInstallments" :key="installment.id" class="rounded-md border border-border p-3.5">
                <div class="flex items-center gap-3.5">
                  <div class="min-w-0 flex-1 text-[13px] leading-[1.3] font-semibold tracking-[-0.01em]">
                    {{ installment.label }}<span class="mt-0.5 block text-[11.5px] leading-[1.35] font-normal tracking-normal text-muted-foreground">{{ formatEuros(installment.amount) }}</span>
                  </div>
                  <Button
                    type="button"
                    :disabled="payingId === installment.id"
                    @click="payNow(installment.id)"
                  >
                    {{ $t('portal.payNowButton') }}
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <p v-else class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('portal.paymentsNone') }}</p>
  </PortalWrap>

  <PortalFooter />
</template>
