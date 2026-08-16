<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
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
</script>

<template>
  <PortalHeader :contact="contact" :show-switcher="showSwitcher" />

  <main class="pwrap wide">
    <h1>{{ $t('portal.paymentsTitle') }}</h1>

    <template v-if="plan">
      <section class="psec" style="margin-top: var(--s5)">
        <div class="psech">
          <h2>{{ project.name }}</h2>
          <span class="chip now">{{ $t('portal.paymentsOpen', { amount: formatEuros(plan.openTotal) }) }}</span>
        </div>

        <div class="sess">
          <div v-for="installment in plan.installments" :key="installment.id" class="srow">
            <div class="stop">
              <div class="sname">
                {{ installment.label }}
                <span>{{ longDate(installment.dueOn) }}</span>
              </div>
              <span class="chip" :class="installment.status === 'paid' ? 'now' : 'next'">
                {{ installment.status === 'paid' ? installment.statusLabel : formatEuros(installment.amount) }}
              </span>
            </div>
            <!-- M-06: only once the PDF exists. Between issue and render the
                 number is on the row above as the transfer reference, but
                 there is nothing to download yet. -->
            <a
              v-if="installment.hasInvoiceDocument"
              class="hint"
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
      <div class="split" style="margin-top: var(--s5)">
        <div v-if="bankDetails.iban" class="prail">
          <div class="card static">
            <div class="cardhead"><span class="kick">{{ $t('portal.transferTitle') }}</span></div>
            <dl class="legal" style="margin: var(--s3) 0 0">
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
              <div v-else-if="!referencedInstallments.length" class="hint">
                {{ $t('portal.transferReferencePending') }}
              </div>
            </dl>
          </div>
        </div>

        <div v-if="stripeEnabled" class="prail">
          <div class="card static">
            <div class="cardhead"><span class="kick">{{ $t('portal.payNowTitle') }}</span></div>

            <p v-if="paymentStatus === 'success'" class="hint">{{ $t('portal.payNowSuccess') }}</p>
            <p v-else-if="paymentStatus === 'cancelled'" class="hint">{{ $t('portal.payNowCancelled') }}</p>


            <div v-if="openInstallments.length" class="sess" style="margin-top: var(--s3)">
              <div v-for="installment in openInstallments" :key="installment.id" class="srow">
                <div class="stop">
                  <div class="sname">{{ installment.label }}<span>{{ formatEuros(installment.amount) }}</span></div>
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

    <p v-else class="hint">{{ $t('portal.paymentsNone') }}</p>
  </main>

  <PortalFooter />
</template>
