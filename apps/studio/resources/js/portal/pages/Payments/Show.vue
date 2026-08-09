<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'

type Installment = {
  id: string
  amount: number
  dueOn: string
  status: string
  statusLabel: string
  label: string
}

type Plan = {
  id: string
  total: number
  openTotal: number
  reference: string | null
  installments: Installment[]
}

defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  project: { id: string; name: string }
  plan: Plan | null
  stripeEnabled: boolean
  bankDetails: { accountHolder: string | null; iban: string | null; bic: string | null }
}>()

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
  <header class="phead">
    <div class="pbar">
      <span class="pmark"><i />Atelier Nordlicht <em>Fotografie · Hamburg</em></span>
      <div class="pme">
        <Link v-if="showSwitcher" href="/">{{ $t('portal.allProjects') }}</Link>
        <span>{{ $t('portal.signedInAs') }} <b>{{ contact.name }}</b></span>
        <button type="button" @click="router.post('/abmelden')">
          <svg viewBox="0 0 24 24">
            <path d="M15 17l5-5-5-5" />
            <path d="M20 12H9" />
            <path d="M12 20H6a2 2 0 01-2-2V6a2 2 0 012-2h6" />
          </svg>
          {{ $t('portal.signOut') }}
        </button>
      </div>
    </div>
  </header>

  <main class="pwrap wide">
    <div class="kick">{{ $t('portal.paymentsKicker') }}</div>
    <h1>{{ $t('portal.paymentsTitle') }}</h1>
    <p class="plead">{{ $t('portal.paymentsLead') }}</p>

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
          </div>
        </div>
      </section>

      <!-- "Überweisen" always renders, Stripe on or off (T-07's own
           acceptance: a client can always see what to pay, to which account
           and under which reference). No mockup was drawn for this screen —
           structure and tokens follow Contracts/Show.vue's own split
           layout. -->
      <div class="split" style="margin-top: var(--s5)">
        <div class="prail">
          <div class="card static">
            <div class="cardhead"><span class="kick">{{ $t('portal.transferTitle') }}</span></div>
            <p class="hint">{{ $t('portal.transferHint') }}</p>
            <dl class="legal" style="margin: var(--s3) 0 0">
              <div><strong>{{ $t('portal.transferAccountHolder') }}:</strong> {{ bankDetails.accountHolder }}</div>
              <div><strong>{{ $t('portal.transferIban') }}:</strong> {{ bankDetails.iban }}</div>
              <div><strong>{{ $t('portal.transferBic') }}:</strong> {{ bankDetails.bic }}</div>
              <div><strong>{{ $t('portal.transferReference') }}:</strong> {{ plan.reference }}</div>
            </dl>
          </div>
        </div>

        <div v-if="stripeEnabled" class="prail">
          <div class="card static">
            <div class="cardhead"><span class="kick">{{ $t('portal.payNowTitle') }}</span></div>
            <!-- ponytail: no Stripe Checkout wired up yet — T-07 only names
                 the seam ("nothing is created in Stripe until the client
                 clicks pay"). M-04/M-05 build the actual charge; add the
                 real button here once that action exists. -->
            <p class="hint">{{ $t('portal.payNowComingSoon') }}</p>
          </div>
        </div>
      </div>
    </template>

    <p v-else class="hint">{{ $t('portal.paymentsNone') }}</p>
  </main>

  <footer class="pfoot">
    <span>Atelier Nordlicht · Hamburg</span>
  </footer>
</template>
