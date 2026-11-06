<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { formatEuros, formatPercent } from '@/lib/money'
import PortalBack from '@/portal/components/PortalBack.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalRail from '@/portal/components/PortalRail.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import QuoteSlider from '@/portal/components/QuoteSlider.vue'
import { dayDate } from '@/portal/lib/dates'
import { splitStages } from '@/portal/lib/paymentStages'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'

type QuoteItem = {
  id: string
  title: string
  description: string | null
  isIncluded: boolean
  netTotal: number
  selectable: boolean
  selected: boolean
}

type Quote = {
  id: string
  // Q-08: null heisst klassisches Angebot — dann steht unten die Tabelle,
  // die vor dieser Runde hier stand, unveraendert.
  hourlyRate: number | null
  status: string
  statusLabel: string
  number: string | null
  validUntil: string | null
  total: number
  discountAmount: number
  net: number
  vatMode: string | null
  vatRate: number | null
  vatNote: string | null
  vatAmount: number | null
  vatLines: { rate: number; net: number; vat: number }[]
  items: QuoteItem[]
  intro: string | null
  outro: string | null
  paymentStages: { id: string; percent: number; label: string; minimum: number }[]
  // Display only — see App\Quotes\Actions\AcceptQuote for the re-check that
  // actually gates acceptance. This flag never reaches the accept request.
  expired: boolean
  acceptedAt: string | null
}

const props = defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  // date, venue and href are the portal page's; the share link has none.
  project: { id: string; name: string; date?: string | null; venue?: string | null; href?: string }
  // T-08's "accept quotes online" switch (default off) — the affordance half
  // of the same rule EnsurePortalCapability enforces on the accept route
  // itself. Never hides an already-accepted quote, only the button that
  // would accept a new one.
  canAccept: boolean
  quote: Quote | null
  // Q-10: wie viele Schaufensterbilder es gibt. Null heisst kein Raster.
  // QuoteShareController's own accept/image routes, for a quote opened
  // through its share link rather than a portal session — same screen,
  // different targets. Both default to the session routes every logged-in
  // contact uses.
  acceptUrl?: string
  viewUrl?: string
  imageUrlBase?: string
}>()

const { t } = useI18n()

const attached = computed(() => usePage().props.portalQuoteLegal as { agb?: string; widerruf?: string } | undefined)
const agbUrl = computed(() => attached.value?.agb ?? null)
const widerrufUrl = computed(() => attached.value?.widerruf ?? null)

const form = useForm({ terms: false })

// Link previews fetch the page without running it, so the opening is
// reported from the browser rather than recorded on the GET.
onMounted(() => {
  if (props.viewUrl) {
    fetch(props.viewUrl, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
    })
  }
})

// AcceptQuote's own re-check rejects under a 'quote' key, not a field this
// form declares — see App\Quotes\Actions\AcceptQuote.
const rejection = computed(() => (form.errors as Record<string, string>).quote ?? (form.errors as Record<string, string>).terms)

function accept() {
  form.post(props.acceptUrl ?? `/projekte/${props.project.id}/angebot/annehmen`, { preserveScroll: true })
}


const messaging = computed(() => (usePage().props.portalNav as { messaging: boolean } | null | undefined)?.messaging === true)

// Choice mode already gets its own breakdown from QuoteSlider's own
// `stages`; this is the classic-table half of the same split, against the
// same gross total the table above already prints.
const gross = computed(() => {
  const quote = props.quote

  return quote ? quote.net + (quote.vatMode === 'standard' ? (quote.vatAmount ?? 0) : 0) : 0
})

const stages = computed(() => (props.quote ? splitStages(gross.value, props.quote.paymentStages) : []))

const railNote = computed(() => {
  const quote = props.quote

  if (quote?.acceptedAt) return t('portal.quoteAccepted', { date: dayDate(quote.acceptedAt) })
  if (quote?.expired && quote.validUntil) return t('portal.quoteExpiredOn', { date: dayDate(quote.validUntil) })
  if (quote?.validUntil) return `${t('quotes.validUntil')} ${dayDate(quote.validUntil)}`

  return undefined
})

const ROW = '[&>td]:py-2 [&>td]:align-top [&>td]:text-[14.5px] [&>td]:leading-[1.5] [&>td:last-child]:text-right [&>td:last-child]:tabular-nums [&>td:last-child]:whitespace-nowrap'
</script>

<template>
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: showSwitcher }" />

    <PortalWrap class="pb-24">
      <section class="pt-6">
        <PortalBack v-if="project.href" :href="project.href" />
        <p class="animate-rise motion-reduce:animate-none mb-4 text-[12px] leading-snug font-semibold tracking-[0.04em] break-words text-muted-foreground">
          {{ [project.name, railNote].filter(Boolean).join(' · ') }}
        </p>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.quoteTitle') }}
        </h1>
        <p v-if="quote?.number" class="mt-2 text-[13px] tabular-nums text-muted-foreground">{{ quote.number }}</p>
      </section>

      <PortalRail>
        <!-- #480: Editor.js-HTML, beim Speichern bereinigt, Felder escaped (QuoteController). -->
        <div
          v-if="quote?.intro"
          class="animate-rise [animation-delay:190ms] motion-reduce:animate-none mt-6 max-w-[62ch] text-[15.5px] leading-[1.6] [&_p+*]:mt-3 [&_*+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5"
          v-html="quote.intro"
        />

        <QuoteSlider
          v-if="quote && quote.hourlyRate !== null"
          class="animate-rise [animation-delay:230ms] motion-reduce:animate-none"
          :project="project"
          :quote="(quote as unknown as App.Quotes.Data.QuoteData)"
          :can-accept="canAccept"
          :accept-url="acceptUrl"
          :image-url-base="imageUrlBase"
          :expired="quote.expired"
          :accepted-at="quote.acceptedAt"
        />

        <div v-else-if="quote" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-6 max-w-[680px] border-t border-border pt-6">
          <table class="w-full border-collapse">
            <tr v-for="item in quote.items" :key="item.id" :class="[ROW, (item.isIncluded || (item.selectable && !item.selected)) && '[&>td]:text-muted-foreground']">
              <td>
                <template v-if="item.selectable && !item.selected">{{ $t('quotes.optional') }}: </template>{{ item.title }}
                <div v-if="item.description" class="text-[13px] leading-[1.45] text-muted-foreground">
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
              <tr :class="[ROW, '[&>td]:pt-2! [&>td]:font-serif [&>td]:text-[22px]!']">
                <td>{{ $t('quotes.net') }}</td>
                <td>{{ formatEuros(quote.net) }}</td>
              </tr>
            </template>

            <tr v-if="quote.vatMode === 'small_business'" :class="[ROW, '[&>td]:py-1! [&>td]:text-[13px]! [&>td]:text-muted-foreground']">
              <td colspan="2">{{ quote.vatNote }}</td>
            </tr>
            <template v-else-if="quote.vatMode === 'standard'">
              <!-- #412: eine Zeile je Steuersatz (§ 14 Abs. 4 Nr. 7 UStG). -->
              <tr v-for="line in quote.vatLines" :key="line.rate" :class="[ROW, '[&>td]:py-1! [&>td]:text-muted-foreground']">
                <td>{{ $t('quotes.vat', { rate: formatPercent(line.rate) }) }}</td>
                <td>{{ formatEuros(line.vat) }}</td>
              </tr>
              <tr :class="[ROW, '[&>td]:pt-2! [&>td]:font-serif [&>td]:text-[22px]!']">
                <td>{{ $t('quotes.total') }}</td>
                <td>{{ formatEuros(quote.net + (quote.vatAmount ?? 0)) }}</td>
              </tr>
            </template>
          </table>

          <dl v-if="project.date || project.venue" class="m-0 mt-8 grid max-w-[420px] grid-cols-[88px_1fr] gap-x-4 gap-y-2 text-[14.5px] leading-[1.5]">
            <template v-if="project.date">
              <dt class="text-muted-foreground">{{ $t('portal.appointment') }}</dt>
              <dd class="m-0 tabular-nums">{{ dayDate(project.date) }}</dd>
            </template>
            <template v-if="project.venue">
              <dt class="text-muted-foreground">{{ $t('portal.place') }}</dt>
              <dd class="m-0">{{ project.venue }}</dd>
            </template>
          </dl>

          <table v-if="stages.length" class="mt-8 w-full max-w-[420px] border-collapse">
            <caption class="pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">
              {{ $t('quotes.payment') }}
            </caption>
            <tr v-for="stage in stages" :key="stage.label" :class="[ROW, '[&>td]:text-muted-foreground']">
              <td>{{ stage.label }}</td>
              <td>{{ formatEuros(stage.amount) }}</td>
            </tr>
          </table>

          <div v-if="quote.hourlyRate === null" class="mt-6 grid gap-4">
            <p v-if="rejection" class="text-[14.5px] text-destructive">{{ rejection }}</p>

            <div v-if="quote.status === 'sent' && !quote.expired && canAccept" class="flex flex-wrap items-center gap-x-6 gap-y-4">
              <div
                v-if="agbUrl || widerrufUrl"
                class="flex min-w-0 flex-1 basis-60 items-start gap-3.5 text-[13.5px] leading-[1.5] [&_[data-slot=checkbox]]:mt-0.5 [&_[data-slot=checkbox]]:size-[17px] [&_[data-slot=checkbox]]:flex-none [&_[data-slot=checkbox][data-state=checked]]:border-action [&_[data-slot=checkbox][data-state=checked]]:bg-action"
              >
                <Checkbox id="quote-terms" v-model="form.terms" required />
                <Label variant="inline" for="quote-terms">
                  <a v-if="agbUrl" :href="agbUrl" target="_blank" rel="noopener" class="underline">{{ $t('portal.footerAgb') }}</a>
                  <template v-if="agbUrl && widerrufUrl">{{ ` ${$t('portal.quoteTermsAnd')} ` }}</template>
                  <a v-if="widerrufUrl" :href="widerrufUrl" target="_blank" rel="noopener" class="underline">{{ $t('portal.footerWiderruf') }}</a>
                  {{ $t('portal.quoteTerms') }}
                </Label>
              </div>

              <Button
                class="max-[700px]:w-full max-[700px]:justify-center"
                variant="portal"
                type="button"
                :disabled="form.processing || ((agbUrl !== null || widerrufUrl !== null) && !form.terms)"
                @click="accept"
              >
                {{ $t('portal.quoteAccept') }}
              </Button>
            </div>

            <Button v-else-if="quote.expired && messaging" as-child variant="portal-ghost">
              <Link :href="`/nachrichten?betrifft=${project.id}`">{{ $t('portal.personWrite') }}</Link>
            </Button>
          </div>

          <div v-if="quote.outro" class="mt-8 max-w-[62ch] text-[15.5px] leading-[1.6] [&_p+*]:mt-3 [&_*+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5" v-html="quote.outro" />
        </div>

        <p v-else class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-6 text-[15px] text-muted-foreground">{{ $t('portal.quoteNone') }}</p>
      </PortalRail>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
