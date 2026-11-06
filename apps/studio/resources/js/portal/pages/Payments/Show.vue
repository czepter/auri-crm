<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import PortalBack from '@/portal/components/PortalBack.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalDot from '@/portal/components/PortalDot.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { dayDate, daysUntil } from '@/portal/lib/dates'
import { formatEuros } from '@/lib/money'
import { Button } from '@/ui/button'

type Installment = {
  id: string
  amount: number
  remaining: number
  dueOn: string
  status: string
  isLate: boolean
  statusLabel: string
  label: string
  invoiceId: string | null
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
  plan: Plan
  quote: { number: string | null } | null
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

// Before the invoice exists the transfer runs under quote number and rate.
function referenceFor(installment: Installment): string {
  return installment.invoiceNumber ?? [props.quote?.number, installment.label].filter(Boolean).join(' ')
}

function payNow(installmentId: string) {
  payingId.value = installmentId
  router.post(`/projekte/${props.project.id}/zahlungen/${installmentId}/jetzt-zahlen`, {}, {
    onFinish: () => (payingId.value = null),
  })
}

// DIN 5008: groups of four.
const iban = computed(() => (props.bankDetails.iban ?? '').replace(/\s+/g, '').replace(/(.{4})(?=.)/g, '$1 '))

const hasPayment = computed(() => props.stripeEnabled || !!props.bankDetails.iban)

// Seven months before the due date "Bezahlen" is a question, not a task.
const soon = (installment: Installment) => installment.isLate || daysUntil(installment.dueOn) <= 14
const payable = (installment: Installment) => hasPayment.value && (soon(installment) || (!props.stripeEnabled && !!props.bankDetails.iban))
const copied = ref<string | null>(null)

async function copy(key: string, text: string) {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = key
    setTimeout(() => (copied.value = null), 1800)
  } catch {
    copied.value = null
  }
}

const BAND = 'max-w-[880px] border-t border-border pt-12'
</script>

<template>
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: showSwitcher }" />

    <PortalWrap class="pb-40">
      <section class="pt-[var(--band)]">
        <PortalBack :href="`/projekte/${project.id}`" />
        <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.name }}</p>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.paymentsTitle') }}
        </h1>
        <dl class="animate-rise [animation-delay:230ms] motion-reduce:animate-none m-0 mt-6 grid max-w-[480px] grid-cols-3 gap-x-6 tabular-nums">
          <div>
            <dt class="text-[13px] text-muted-foreground">{{ $t('portal.paymentsTotal') }}</dt>
            <dd class="m-0 text-[17px]">{{ formatEuros(plan.total) }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted-foreground">{{ $t('portal.paymentsPaid') }}</dt>
            <dd class="m-0 text-[17px]">{{ formatEuros(plan.total - plan.openTotal) }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted-foreground">{{ $t('portal.paymentsOpenLabel') }}</dt>
            <dd class="m-0 text-[17px]" :class="plan.openTotal > 0 && 'text-action'">{{ formatEuros(plan.openTotal) }}</dd>
          </div>
        </dl>
      </section>

        <p v-if="paymentStatus" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-8 max-w-[880px] rounded-md bg-sunk p-6 text-[15px]">
          {{ paymentStatus === 'success' ? $t('portal.payNowSuccess') : $t('portal.payNowCancelled') }}
        </p>

        <!-- One list, not two. The card-payment block used to repeat every open
             installment a second time under its own heading. -->
        <section class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12">
          <div :class="BAND">
            <PortalLabel class="mb-6">{{ $t('portal.installmentsTitle') }}</PortalLabel>
            <div
              v-for="installment in plan.installments"
              :key="installment.id"
              class="border-b border-border py-5"
            >
              <div class="flex items-baseline justify-between gap-4">
                <span class="font-serif text-[20px] leading-[1.25] tracking-[-0.01em]">{{ installment.label }}</span>
                <span
                  class="text-[15px] leading-none font-semibold tabular-nums whitespace-nowrap"
                  :class="['paid', 'cancelled'].includes(installment.status) ? 'text-muted-foreground' : 'text-action'"
                >{{ formatEuros(installment.amount) }}</span>
              </div>
              <p class="mt-1.5 flex items-center gap-2 text-[13.5px] text-muted-foreground">
                <PortalDot :state="['paid', 'cancelled'].includes(installment.status) ? 'done' : installment.isLate ? 'late' : 'due'" />
                {{ ['paid', 'cancelled'].includes(installment.status)
                  ? installment.statusLabel
                  : $t(installment.isLate ? 'portal.overdueSince' : 'portal.dueOn', { date: dayDate(installment.dueOn) }) }}
              </p>

              <div v-if="!['paid', 'cancelled'].includes(installment.status) && payable(installment)" class="mt-4">
                <!-- #414: der Knopf nennt, was er abbucht — nach einer
                     Teilzahlung ist das der Rest und nicht die Rate. -->
                <Button
                  v-if="stripeEnabled"
                  class="w-full justify-center min-[700px]:w-auto"
                  variant="portal"
                  type="button"
                  :disabled="payingId === installment.id"
                  @click="payNow(installment.id)"
                >
                  {{ installment.remaining === installment.amount
                    ? $t('portal.payButton')
                    : `${$t('portal.payButton')} · ${formatEuros(installment.remaining)}` }}
                </Button>
                <component :is="soon(installment) ? 'details' : 'div'" v-else-if="bankDetails.iban" class="group" :open="soon(installment) && installment.id === openInstallments[0]?.id">
                  <Button v-if="soon(installment)" as="summary" variant="portal" class="w-full cursor-pointer justify-center list-none min-[700px]:w-auto [&::-webkit-details-marker]:hidden">
                    {{ $t('portal.payButton') }}
                  </Button>
                  <p v-if="!soon(installment)" class="mb-2 text-[13px] text-muted-foreground first-letter:uppercase">{{ $t('portal.dueOn', { date: dayDate(installment.dueOn) }) }}</p>
                  <dl class="m-0 grid gap-y-4 rounded-md bg-sunk p-4" :class="soon(installment) && 'mt-4'">
                    <div v-if="bankDetails.accountHolder">
                      <dt class="text-[13px] text-muted-foreground">{{ $t('portal.transferAccountHolder') }}</dt>
                      <dd class="m-0 text-[15px]">{{ bankDetails.accountHolder }}</dd>
                    </div>
                    <div>
                      <dt class="text-[13px] text-muted-foreground">{{ $t('portal.transferIban') }}</dt>
                      <dd class="m-0 flex items-center justify-between gap-3 text-[15px] tabular-nums">
                        {{ iban }}
                        <Button type="button" variant="portal-chip" @click="copy(`iban-${installment.id}`, iban)">
                          {{ copied === `iban-${installment.id}` ? $t('gallery.copied') : $t('gallery.copy') }}
                        </Button>
                      </dd>
                    </div>
                    <div v-if="bankDetails.bic">
                      <dt class="text-[13px] text-muted-foreground">{{ $t('portal.transferBic') }}</dt>
                      <dd class="m-0 flex items-center justify-between gap-3 text-[15px] tabular-nums">
                        {{ bankDetails.bic }}
                        <Button type="button" variant="portal-chip" @click="copy('bic', bankDetails.bic)">
                          {{ copied === 'bic' ? $t('gallery.copied') : $t('gallery.copy') }}
                        </Button>
                      </dd>
                    </div>
                    <div>
                      <dt class="text-[13px] text-muted-foreground">{{ $t('portal.transferReference') }}</dt>
                      <dd class="m-0 flex items-center justify-between gap-3 text-[15px] tabular-nums">
                        {{ referenceFor(installment) }}
                        <Button
                          type="button"
                          variant="portal-chip"
                          @click="copy(installment.id, referenceFor(installment))"
                        >
                          {{ copied === installment.id ? $t('gallery.copied') : $t('gallery.copy') }}
                        </Button>
                      </dd>
                    </div>
                  </dl>
                </component>
              </div>

              <!-- #419: auf die Seite, nicht aufs PDF. Erst ab der
                   Nummer — vorher gibt es keine Rechnung zu zeigen. -->
              <Link
                v-if="installment.invoiceNumber !== null"
                class="mt-2 inline-flex min-h-11 items-center text-[13.5px] whitespace-nowrap text-foreground underline underline-offset-4 decoration-border hover:decoration-foreground"
                :href="`/projekte/${project.id}/zahlungen/${installment.invoiceId}`"
              >{{ $t('portal.invoiceDownload', { number: installment.invoiceNumber }) }}</Link>
            </div>
          </div>
        </section>

        <!-- "Überweisen" renders whenever the studio filed an IBAN, Stripe on
             or off (T-07's own acceptance: a client can always see what to pay,
             to which account and under which reference). Bank details are
             optional in settings, and half a transfer block — labels with
             nothing behind them — is worse than none. -->
        <section v-if="bankDetails.iban && stripeEnabled" id="ueberweisen" class="scroll-mt-24 animate-rise [animation-delay:320ms] motion-reduce:animate-none mt-12">
          <div :class="BAND">
            <PortalLabel class="mb-6">{{ $t('portal.transferTitle') }}</PortalLabel>
            <dl class="m-0 grid max-w-[560px] gap-y-5">
              <div v-if="bankDetails.accountHolder">
                <dt class="text-[13px] text-muted-foreground">{{ $t('portal.transferAccountHolder') }}</dt>
                <dd class="m-0 text-[15px]">{{ bankDetails.accountHolder }}</dd>
              </div>
              <div>
                <dt class="text-[13px] text-muted-foreground">{{ $t('portal.transferIban') }}</dt>
                <dd class="m-0 flex items-center justify-between gap-4 text-[15px] tabular-nums">
                  {{ iban }}
                  <Button type="button" variant="portal-chip" @click="copy('iban', iban)">
                    {{ copied === 'iban' ? $t('gallery.copied') : $t('gallery.copy') }}
                  </Button>
                </dd>
              </div>
              <div v-if="bankDetails.bic">
                <dt class="text-[13px] text-muted-foreground">{{ $t('portal.transferBic') }}</dt>
                <dd class="m-0 flex items-center justify-between gap-4 text-[15px] tabular-nums">
                  {{ bankDetails.bic }}
                  <Button type="button" variant="portal-chip" @click="copy('bic', bankDetails.bic)">
                    {{ copied === 'bic' ? $t('gallery.copied') : $t('gallery.copy') }}
                  </Button>
                </dd>
              </div>

              <div v-for="installment in openInstallments" :key="installment.id">
                <dt class="text-[13px] text-muted-foreground">{{ $t('portal.transferReference') }}</dt>
                <dd class="m-0 flex items-center justify-between gap-4 text-[15px] tabular-nums">
                  {{ referenceFor(installment) }}
                  <Button type="button" variant="portal-chip" @click="copy(installment.id, referenceFor(installment))">
                    {{ copied === installment.id ? $t('gallery.copied') : $t('gallery.copy') }}
                  </Button>
                </dd>
              </div>
            </dl>
          </div>
        </section>

    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
