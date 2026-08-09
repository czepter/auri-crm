<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import QuoteItemsEditor, { type QuoteItemRow } from '@/admin/components/QuoteItemsEditor.vue'
import { basisPointsToPercent, centsToEuros, eurosToCents, formatEuros, percentToBasisPoints } from '@/admin/lib/money'

/** discountType/discountValue -> the string a discount's own type expects (percent or euros), and back. */
function discountValueToField(type: string | null, value: number | null): string {
  return type === 'percentage' ? basisPointsToPercent(value) : centsToEuros(value)
}

function discountFieldToValue(type: string, value: string): number | null {
  return type === 'percentage' ? percentToBasisPoints(value) : eurosToCents(value)
}

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  quotes: App.Quotes.Data.QuoteData[]
  packages: { value: string; label: string }[]
}>()

const creating = ref(false)
const createForm = useForm({ valid_until: '', package: '' })

function create() {
  createForm.post(`/auftraege/${props.header.id}/angebote`, {
    preserveScroll: true,
    onSuccess: () => {
      createForm.reset()
      creating.value = false
    },
  })
}

// One edit form per quote, keyed by its id — several drafts stay independent
// rather than fighting over one form's state.
const editForms = Object.fromEntries(
  props.quotes.map((quote) => [
    quote.id,
    useForm({
      valid_until: quote.validUntil ?? '',
      discountType: (quote.discountType ?? '') as '' | 'percentage' | 'amount',
      discountValue: discountValueToField(quote.discountType, quote.discountValue),
      items: quote.items.map(
        (item): QuoteItemRow => ({
          title: item.title,
          description: item.description ?? '',
          quantity: item.quantity,
          unitPrice: centsToEuros(item.unitPrice),
          discountType: (item.discountType ?? '') as '' | 'percentage' | 'amount',
          discountValue: discountValueToField(item.discountType, item.discountValue),
        }),
      ),
    }),
  ]),
)

function save(quoteId: string) {
  editForms[quoteId]
    .transform((data) => ({
      valid_until: data.valid_until,
      discount_type: data.discountType || null,
      discount_value: data.discountType ? discountFieldToValue(data.discountType, data.discountValue) : null,
      items: data.items.map((item: QuoteItemRow) => ({
        title: item.title,
        description: item.description || null,
        quantity: item.quantity,
        unit_price: eurosToCents(item.unitPrice),
        discount_type: item.discountType || null,
        discount_value: item.discountType ? discountFieldToValue(item.discountType, item.discountValue) : null,
      })),
    }))
    .patch(`/angebote/${quoteId}`, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectHeader :header="header" active="quotes" />

    <div class="hrow" style="margin-bottom: var(--s4)">
      <span class="kick" style="margin-bottom: 0">{{ $t('quotes.title') }}</span>
      <button v-if="header.canManageQuotes" class="btn" type="button" @click="creating = !creating">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('quotes.create') }}
      </button>
    </div>

    <div v-if="creating && header.canManageQuotes" class="card static" style="margin-bottom: var(--s4)">
      <div class="cardhead"><span class="kick">{{ $t('quotes.create') }}</span></div>

      <form class="fgrid" @submit.prevent="create">
        <div class="fld">
          <label for="valid_until">{{ $t('quotes.validUntil') }}</label>
          <input id="valid_until" v-model="createForm.valid_until" class="inp" type="date" />
        </div>
        <div class="fld">
          <label for="package">{{ $t('quotes.fromPackage') }}</label>
          <select id="package" v-model="createForm.package" class="inp">
            <option value="">{{ $t('quotes.blank') }}</option>
            <option v-for="pkg in packages" :key="pkg.value" :value="pkg.value">{{ pkg.label }}</option>
          </select>
        </div>
        <div class="fld wide">
          <div class="formfoot" style="margin-top: 0">
            <button class="btn" type="submit" :disabled="createForm.processing">{{ $t('common.save') }}</button>
            <button class="txtbtn" type="button" @click="creating = false">{{ $t('common.cancel') }}</button>
          </div>
        </div>
      </form>
    </div>

    <!-- Depth behind the Angebot & Vertrag tab: only the quote half exists so
         far. The card and table follow mockup 04-angebot's own vocabulary —
         only draft is reachable, so there is no tax breakdown, PDF link,
         Vertrag card, history or payment plan yet. -->
    <div v-for="quote in quotes" :key="quote.id" class="card static" style="margin-bottom: var(--s4)">
      <div class="cardhead">
        <span class="kick">{{ $t('quotes.title') }}</span>
        <span class="chip next">{{ quote.statusLabel }}</span>
      </div>

      <table class="qt">
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
      </table>

      <template v-if="header.canManageQuotes">
        <div class="dsec">
          <div class="dlab">{{ $t('quotes.items') }}</div>
          <QuoteItemsEditor v-model="editForms[quote.id].items" />
        </div>

        <form class="fgrid" @submit.prevent="save(quote.id)">
          <div class="fld">
            <label :for="`quote-valid-${quote.id}`">{{ $t('quotes.validUntil') }}</label>
            <input :id="`quote-valid-${quote.id}`" v-model="editForms[quote.id].valid_until" class="inp" type="date" />
          </div>
          <div class="fld">
            <label :for="`quote-discount-type-${quote.id}`">{{ $t('quotes.discountType') }}</label>
            <select :id="`quote-discount-type-${quote.id}`" v-model="editForms[quote.id].discountType" class="inp">
              <option value="">{{ $t('quotes.discountNone') }}</option>
              <option value="percentage">{{ $t('quotes.discountPercentage') }}</option>
              <option value="amount">{{ $t('quotes.discountAmount') }}</option>
            </select>
          </div>
          <div v-if="editForms[quote.id].discountType" class="fld">
            <label :for="`quote-discount-value-${quote.id}`">
              {{
                editForms[quote.id].discountType === 'percentage'
                  ? $t('quotes.discountValuePercent')
                  : $t('quotes.discountValueEuros')
              }}
            </label>
            <input
              :id="`quote-discount-value-${quote.id}`"
              v-model="editForms[quote.id].discountValue"
              class="inp num"
              type="text"
              inputmode="decimal"
            />
          </div>
          <div class="fld wide">
            <div class="formfoot" style="margin-top: 0">
              <button class="btn" type="submit" :disabled="editForms[quote.id].processing">
                {{ $t('common.save') }}
              </button>
            </div>
          </div>
        </form>
      </template>
    </div>

    <p v-if="!quotes.length" class="hint">{{ $t('quotes.empty') }}</p>
  </AdminLayout>
</template>
