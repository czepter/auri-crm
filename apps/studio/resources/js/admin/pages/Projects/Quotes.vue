<script setup lang="ts">
import { reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import QuoteTable, { type QuoteRow } from '@/admin/components/QuoteTable.vue'
import ValidUntilField from '@/admin/components/ValidUntilField.vue'
import { debounced } from '@/admin/lib/autosave'
import { basisPointsToPercent, centsToEuros, eurosToCents, formatDiscount, formatEuros, parseDiscount } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  quotes: App.Quotes.Data.QuoteData[]
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
  canManageCatalog: boolean
}>()

type Draft = { validUntil: string; discount: string; rows: QuoteRow[] }

function draftOf(quote: App.Quotes.Data.QuoteData): Draft {
  return {
    validUntil: quote.validUntil ?? '',
    discount: formatDiscount(quote.discountType, quote.discountValue),
    rows: quote.items.map((item) => ({
      title: item.title,
      description: item.description ?? '',
      quantity: item.quantity,
      unitPrice: centsToEuros(item.unitPrice),
      discount: formatDiscount(item.discountType, item.discountValue),
      // Abgeleitet statt gespeichert: gemerkt heisst, die Bibliothek führt
      // diesen Titel.
      remember: props.catalogItems.some((entry) => entry.title === item.title),
    })),
  }
}

// Keyed by quote id and filled on demand rather than built once at setup: a
// partial reload that adds a quote used to leave the map behind.
const drafts = reactive<Record<string, Draft>>({})

watch(
  () => props.quotes,
  (quotes) => {
    for (const quote of quotes) {
      if (quote.status === 'draft' && !drafts[quote.id]) {
        drafts[quote.id] = draftOf(quote)
      }
    }
  },
  { immediate: true, deep: false },
)

const save = debounced((quoteId: string) => {
  const draft = drafts[quoteId]
  const quoteDiscount = parseDiscount(draft.discount)

  router.patch(
    `/angebote/${quoteId}`,
    {
      valid_until: draft.validUntil || null,
      discount_type: quoteDiscount.type || null,
      discount_value: quoteDiscount.value,
      items: draft.rows.map((row) => {
        const discount = parseDiscount(row.discount)

        return {
          title: row.title,
          description: row.description || null,
          quantity: row.quantity,
          unit_price: eurosToCents(row.unitPrice),
          discount_type: discount.type || null,
          discount_value: discount.value,
          // Ohne die Faehigkeit geht der Schluessel gar nicht erst mit, damit
          // der Waechter im Controller nicht ohne Anlass greift.
          ...(props.canManageCatalog ? { remember: row.remember } : {}),
        }
      }),
    },
    // Die Zeile, in der getippt wird, überlebt die Antwort; zurück kommen nur
    // die serverseitig gerechneten Summen.
    { preserveScroll: true, preserveState: true, only: ['quotes'] },
  )
}, 600)

function create() {
  router.post(`/auftraege/${props.header.id}/angebote`, {}, { preserveScroll: true })
}

function send(quoteId: string) {
  router.post(`/angebote/${quoteId}/versenden`, {}, { preserveScroll: true })
}

/** draft/sent/accepted/superseded/expired -> the mockup's own three chip states (tokens.css). */
function chipClass(status: string): string {
  return ({ sent: 'now', accepted: 'done' } as Record<string, string>)[status] ?? 'next'
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectShell :header="header" active="quotes">
        <div class="hrow" style="margin-bottom: var(--s4)">
          <span class="kick" style="margin-bottom: 0">{{ $t('quotes.title') }}</span>
          <Button v-if="header.canManageQuotes" type="button" @click="create">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            {{ $t('quotes.create') }}
          </Button>
        </div>

        <!-- Depth behind the Angebot & Vertrag tab: only the quote half exists so
             far — no tax breakdown beyond Q-04's own VAT row, no PDF link,
             Vertrag card, Nachtrag or payment plan yet (all Q-07 and later). A
             superseded quote renders in this same loop rather than being hidden
             or 404ing: Project::quotes() already returns every quote, and this
             card is what "stays reachable and reads as superseded" means here. -->
        <div v-for="quote in quotes" :key="quote.id" class="card static" style="margin-bottom: var(--s4)">
          <div class="cardhead">
            <span class="kick">{{ quote.number ? `${$t('quotes.title')} ${quote.number}` : $t('quotes.title') }}</span>
            <span :class="['chip', chipClass(quote.status)]">{{ quote.statusLabel }}</span>
          </div>

          <!-- Editing stays behind canManageQuotes and behind status === 'draft'
               — the UI half of Q-06's immutability rule, Quote::booted()'s guard
               being the half that still holds even if this condition were ever
               wrong or bypassed. Ein Entwurf ist die Tabelle; erst ab Versendet
               tritt das gelesene Dokument an ihre Stelle. -->
          <template v-if="header.canManageQuotes && quote.status === 'draft' && drafts[quote.id]">
            <QuoteTable
              v-model="drafts[quote.id].rows"
              :can-manage-catalog="canManageCatalog"
              :packages="packages"
              :catalog-items="catalogItems"
              @change="save(quote.id)"
            />

            <div class="fgrid" style="margin-top: var(--s4)">
              <ValidUntilField
                :id="`quote-valid-${quote.id}`"
                v-model="drafts[quote.id].validUntil"
                @blur.capture="save(quote.id)"
              />
              <div class="fld">
                <Label :for="`quote-discount-${quote.id}`">{{ $t('quotes.discount') }}</Label>
                <Input
                  :id="`quote-discount-${quote.id}`"
                  v-model="drafts[quote.id].discount"
                  class="tabular-nums"
                  type="text"
                  inputmode="decimal"
                  @blur="save(quote.id)"
                />
              </div>
              <div class="fld wide">
                <div class="formfoot" style="margin-top: 0">
                  <Button type="button" @click="send(quote.id)">{{ $t('quotes.send') }}</Button>
                </div>
              </div>
            </div>
          </template>

          <table v-else class="qt">
            <tr v-for="item in quote.items" :key="item.id" :class="{ incl: item.isIncluded }">
              <td>
                {{ item.title }}
                <div v-if="item.isIncluded || item.description" class="d">
                  {{ item.isIncluded ? $t('quotes.included') : item.description }}
                </div>
              </td>
              <td>{{ item.isIncluded ? $t('quotes.includedPrice') : formatEuros(item.lineTotal) }}</td>
            </tr>
            <!-- No discount on this quote: the single sum row Q-02 already rendered, unchanged. -->
            <tr v-if="!quote.discountAmount" class="sum">
              <td>{{ $t('quotes.total') }}</td>
              <td>{{ formatEuros(quote.total) }}</td>
            </tr>
            <template v-else>
              <tr class="sum">
                <td>{{ $t('quotes.total') }}</td>
                <td>{{ formatEuros(quote.total) }}</td>
              </tr>
              <tr class="tax">
                <td>{{ $t('quotes.discount') }}</td>
                <td>−{{ formatEuros(quote.discountAmount) }}</td>
              </tr>
              <tr class="total">
                <td>{{ $t('quotes.net') }}</td>
                <td>{{ formatEuros(quote.net) }}</td>
              </tr>
            </template>

            <!-- Q-04: only a quote Q-06 has already sent carries these — a draft's
                 vatMode is always null (see QuoteData), so nothing prints here
                 until that story ships. Vocabulary and order match mockup
                 04-angebot's accepted-quote card: a muted §19 note with no rate
                 shown at all, or a muted rate row followed by the final total
                 including it. -->
            <tr v-if="quote.vatMode === 'small_business'" class="tax">
              <td colspan="2">{{ quote.vatNote }}</td>
            </tr>
            <template v-else-if="quote.vatMode === 'standard'">
              <tr class="tax">
                <td>{{ $t('quotes.vat', { rate: basisPointsToPercent(quote.vatRate) }) }}</td>
                <td>{{ formatEuros(quote.vatAmount ?? 0) }}</td>
              </tr>
              <tr class="total">
                <td>{{ $t('quotes.total') }}</td>
                <td>{{ formatEuros(quote.net + (quote.vatAmount ?? 0)) }}</td>
              </tr>
            </template>
          </table>
        </div>

        <p v-if="!quotes.length && !header.canManageQuotes" class="hint">{{ $t('quotes.empty') }}</p>
    </ProjectShell>
  </AdminLayout>
</template>
