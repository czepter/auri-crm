<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import QuoteItemsEditor, { type QuoteItemRow } from '@/admin/components/QuoteItemsEditor.vue'
import ValidUntilField from '@/admin/components/ValidUntilField.vue'
import { basisPointsToPercent, centsToEuros, eurosToCents, formatEuros, percentToBasisPoints } from '@/admin/lib/money'
import { NONE, fromOption, toOption } from '@/lib/select'
import { Button } from '@/ui/button'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'

type DiscountType = '' | 'percentage' | 'amount'

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

function setDiscountType(quoteId: string, value: unknown) {
  editForms[quoteId].discountType = fromOption(value) as DiscountType
}

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

// Q-06: one Send per quote, keyed the same way editForms is — a project can
// hold several drafts at once, each sent independently.
const sendForms = Object.fromEntries(props.quotes.map((quote) => [quote.id, useForm({})]))

function send(quoteId: string) {
  sendForms[quoteId].post(`/angebote/${quoteId}/versenden`, { preserveScroll: true })
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
          <!-- Two questions — until when, and from which package — and then the
               quote itself opens below for editing. That is a doorway, not a
               section of the page, and it was sitting on top of the quotes it
               was about to join. -->
          <Dialog v-if="header.canManageQuotes" v-model:open="creating">
            <DialogTrigger as-child>
              <Button type="button">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
                {{ $t('quotes.create') }}
              </Button>
            </DialogTrigger>

            <DialogContent class="max-w-[480px]">
              <DialogHeader>
                <DialogTitle>{{ $t('quotes.create') }}</DialogTitle>
              </DialogHeader>

              <DialogBody>
                <form id="quote-create-form" class="fgrid" @submit.prevent="create">
                  <ValidUntilField id="valid_until" v-model="createForm.valid_until" />
                  <div class="fld">
                    <Label for="package">{{ $t('quotes.fromPackage') }}</Label>
                    <Select
                      :model-value="toOption(createForm.package)"
                      @update:model-value="(value) => (createForm.package = fromOption(value))"
                    >
                      <SelectTrigger id="package">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem :value="NONE">{{ $t('quotes.blank') }}</SelectItem>
                        <SelectItem v-for="pkg in packages" :key="pkg.value" :value="pkg.value">{{ pkg.label }}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </form>
              </DialogBody>

              <DialogFooter>
                <Button type="submit" form="quote-create-form" :disabled="createForm.processing">
                  {{ $t('common.save') }}
                </Button>
                <DialogClose as-child>
                  <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
                </DialogClose>
              </DialogFooter>
            </DialogContent>
          </Dialog>
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

          <!-- Editing and sending both stay behind canManageQuotes, and both
               stay behind status === 'draft' — the UI half of Q-06's
               immutability rule, Quote::booted()'s guard being the half that
               still holds even if this condition were ever wrong or bypassed. -->
          <template v-if="header.canManageQuotes && quote.status === 'draft'">
            <div class="dsec">
              <div class="dlab">{{ $t('quotes.items') }}</div>
              <QuoteItemsEditor v-model="editForms[quote.id].items" />
            </div>

            <form class="fgrid" @submit.prevent="save(quote.id)">
              <ValidUntilField :id="`quote-valid-${quote.id}`" v-model="editForms[quote.id].valid_until" />
              <div class="fld">
                <Label :for="`quote-discount-type-${quote.id}`">{{ $t('quotes.discountType') }}</Label>
                <Select
                  :model-value="toOption(editForms[quote.id].discountType)"
                  @update:model-value="(value) => setDiscountType(quote.id, value)"
                >
                  <SelectTrigger :id="`quote-discount-type-${quote.id}`">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="NONE">{{ $t('quotes.discountNone') }}</SelectItem>
                    <SelectItem value="percentage">{{ $t('quotes.discountPercentage') }}</SelectItem>
                    <SelectItem value="amount">{{ $t('quotes.discountAmount') }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div v-if="editForms[quote.id].discountType" class="fld">
                <Label :for="`quote-discount-value-${quote.id}`">{{ $t('quotes.discountValue') }}</Label>
                <InputGroup>
                  <InputGroupInput
                    :id="`quote-discount-value-${quote.id}`"
                    v-model="editForms[quote.id].discountValue" class="tabular-nums"
                    type="text"
                    inputmode="decimal" />
                  <InputGroupAddon align="inline-end">
                    {{ editForms[quote.id].discountType === 'percentage' ? $t('common.unitPercent') : $t('common.unitEuro') }}
                  </InputGroupAddon>
                </InputGroup>
              </div>
              <div class="fld wide">
                <div class="formfoot" style="margin-top: 0">
                  <Button type="submit" :disabled="editForms[quote.id].processing">
                    {{ $t('common.save') }}
                  </Button>
                  <Button type="button" :disabled="sendForms[quote.id].processing" @click="send(quote.id)">
                    {{ $t('quotes.send') }}
                  </Button>
                </div>
              </div>
            </form>
          </template>
        </div>

        <p v-if="!quotes.length && !header.canManageQuotes" class="hint">{{ $t('quotes.empty') }}</p>
    </ProjectShell>
  </AdminLayout>
</template>
