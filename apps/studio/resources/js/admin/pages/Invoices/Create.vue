<script setup lang="ts">
import { computed, ref } from 'vue'
import { useMediaQuery } from '@vueuse/core'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import ContactTypeahead from '@/admin/components/ContactTypeahead.vue'
import VatRateSelect from '@/admin/components/VatRateSelect.vue'
import InvoicePreviewPane from '@/admin/components/InvoicePreviewPane.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { ChoiceGroup } from '@/ui/choice-group'
import { DatePicker } from '@/ui/date-picker'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Textarea } from '@/ui/textarea'
import { dayWithWeekday } from '@/admin/lib/dates'
import { eurosToCents, formatEuros } from '@/admin/lib/money'

type Plan = { projectId: string; projectName: string; installments: { id: string; label: string; amount: number; dueOn: string }[] }

const props = defineProps<{ plans: Plan[]; project: string | null; vatRate: number }>()

const { t } = useI18n()

const mode = ref(props.plans.length ? 'plan' : 'free')
const modes = computed(() => [
  { value: 'plan', label: t('invoicesCreate.fromPlan') },
  { value: 'free', label: t('invoicesCreate.free') },
])

const projectId = ref(props.plans.some((plan) => plan.projectId === props.project) ? props.project : props.plans[0]?.projectId ?? null)
const plan = computed(() => props.plans.find((candidate) => candidate.projectId === projectId.value) ?? null)

const planForm = useForm({ installments: [] as string[] })

// Der Brieftext gehört zur Rechnung, nicht zum Modus: er bleibt beim Umschalten stehen.
const texts = ref({ intro: '', outro: '' })

function toggle(id: string, checked: boolean | 'indeterminate') {
  planForm.installments = checked === true
    ? [...planForm.installments, id]
    : planForm.installments.filter((chosen) => chosen !== id)
}

// Server-side keys the form's own fields do not name: the tax identifier, the new contact.
const planErrors = computed(() => planForm.errors as Record<string, string | undefined>)

const planTotal = computed(() => plan.value?.installments
  .filter((row) => planForm.installments.includes(row.id))
  .reduce((sum, row) => sum + row.amount, 0) ?? 0)

const contact = ref<App.Crm.Data.ContactSuggestionData | null>(null)
const newContact = ref<{ name: string; email: string; street: string; postcode: string; city: string } | null>(null)

const freeForm = useForm({
  due_on: '',
  items: [{ description: '', quantity: '1', unit_price: '', vat_rate: props.vatRate as number | null }],
})

const freeTotal = computed(() => freeForm.items.reduce((sum, item) => sum + (Number(item.quantity) || 0) * (eurosToCents(item.unit_price) ?? 0), 0))

const freeErrors = computed(() => freeForm.errors as Record<string, string | undefined>)

function freePayload(data: ReturnType<typeof freeForm.data>) {
  return {
    ...data,
    contact_id: contact.value?.id,
    contact: newContact.value ?? undefined,
    items: data.items.map((item) => ({
      description: item.description,
      quantity: Number(item.quantity),
      unit_price: eurosToCents(item.unit_price) ?? 0,
      vat_rate: item.vat_rate ?? props.vatRate,
    })),
  }
}

function submitFree() {
  freeForm.transform((data) => ({ ...freePayload(data), ...texts.value })).post('/rechnungen')
}

function submitPlan() {
  planForm.transform((data) => ({ ...data, ...texts.value })).post('/rechnungen')
}

const previewPayload = computed(() => ({
  ...(mode.value === 'plan' ? { installments: planForm.installments } : freePayload(freeForm.data())),
  ...texts.value,
}))

const showPreview = useMediaQuery('(min-width: 1180px)')
</script>

<template>
  <AdminLayout active="rechnungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('invoicesCreate.title')" />

        <div class="flex gap-6 px-8 pt-5 pb-8">
          <div class="w-[720px] max-w-full flex-none">
            <ChoiceGroup v-if="plans.length" v-model="mode" variant="segmented" :options="modes" />

            <form v-if="mode === 'plan'" class="mt-5 grid gap-4" @submit.prevent="submitPlan">
              <Field v-slot="{ id }" :label="$t('invoicesCreate.project')">
                <Select v-model="projectId" @update:model-value="planForm.installments = []">
                  <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="candidate in plans" :key="candidate.projectId" :value="candidate.projectId">
                      {{ candidate.projectName }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <div v-if="plan" class="grid gap-2">
                <label v-for="row in plan.installments" :key="row.id" class="flex items-center gap-3 text-[13px]">
                  <Checkbox :model-value="planForm.installments.includes(row.id)" @update:model-value="(checked) => toggle(row.id, checked)" />
                  <span class="flex-1">{{ row.label }}</span>
                  <span class="text-ink-2">{{ dayWithWeekday(row.dueOn) }}</span>
                  <span class="w-[110px] text-right tabular-nums">{{ formatEuros(row.amount) }}</span>
                </label>
              </div>

              <Field v-slot="{ id }" :label="$t('quotes.intro')">
                <Textarea :id="id" v-model="texts.intro" rows="3" />
              </Field>
              <Field v-slot="{ id }" :label="$t('quotes.outro')">
                <Textarea :id="id" v-model="texts.outro" rows="3" />
              </Field>

              <p v-if="planForm.errors.installments" class="text-[12px] text-destructive">{{ planForm.errors.installments }}</p>
              <p v-if="planErrors.invoice" class="text-[12px] text-destructive">{{ planErrors.invoice }}</p>

              <div class="flex items-center gap-3">
                <Button type="submit" :disabled="!planForm.installments.length || planForm.processing">{{ $t('invoicesCreate.submit') }}</Button>
                <span v-if="planTotal" class="tabular-nums">{{ formatEuros(planTotal) }}</span>
              </div>
            </form>

            <form v-else class="mt-5 grid gap-4" @submit.prevent="submitFree">
              <Field :label="$t('invoicesCreate.recipient')" :error="freeErrors['contact.name'] ?? freeErrors.contact_id">
                <div v-if="newContact" class="grid grid-cols-2 gap-3">
                  <Input v-model="newContact.name" :placeholder="$t('invoicesCreate.name')" required />
                  <Input v-model="newContact.email" type="email" :placeholder="$t('invoicesCreate.email')" />
                  <Input v-model="newContact.street" class="col-span-2" :placeholder="$t('invoicesCreate.street')" />
                  <Input v-model="newContact.postcode" :placeholder="$t('invoicesCreate.postcode')" />
                  <Input v-model="newContact.city" :placeholder="$t('invoicesCreate.city')" />
                </div>
                <ContactTypeahead
                  v-else
                  v-model="contact"
                  @create-new="(term) => (newContact = { name: term, email: '', street: '', postcode: '', city: '' })"
                />
              </Field>

              <div v-for="(item, index) in freeForm.items" :key="index" class="grid grid-cols-[1fr_70px_130px_110px_auto] items-end gap-2">
                <Field v-slot="{ id }" :label="$t('invoicesCreate.description')" :error="freeErrors[`items.${index}.description`]">
                  <Input :id="id" v-model="item.description" required />
                </Field>
                <Field v-slot="{ id }" :label="$t('invoicesCreate.quantity')">
                  <Input :id="id" v-model="item.quantity" class="tabular-nums" type="number" min="1" required />
                </Field>
                <Field v-slot="{ id }" :label="$t('invoicesCreate.unitPrice')">
                  <InputGroup>
                    <InputGroupInput :id="id" v-model="item.unit_price" class="tabular-nums" type="text" inputmode="decimal" required />
                    <InputGroupAddon align="inline-end">€</InputGroupAddon>
                  </InputGroup>
                </Field>
                <Field v-if="vatRate > 0" v-slot="{ id }" :label="$t('invoicesCreate.vatRate')">
                  <VatRateSelect :id="id" v-model="item.vat_rate" :placeholder="`${vatRate / 100} %`" />
                </Field>
                <Button
                  v-if="freeForm.items.length > 1"
                  variant="outline"
                  tone="accent"
                  type="button"
                  @click="freeForm.items.splice(index, 1)"
                >{{ $t('common.remove') }}</Button>
              </div>

              <div>
                <Button
                  variant="outline"
                  type="button"
                  @click="freeForm.items.push({ description: '', quantity: '1', unit_price: '', vat_rate: vatRate })"
                >{{ $t('invoicesCreate.addItem') }}</Button>
              </div>

              <Field v-slot="{ id }" class="max-w-[220px]" :label="$t('payments.dueOn')" :error="freeForm.errors.due_on">
                <DatePicker :id="id" v-model="freeForm.due_on" required />
              </Field>

              <Field v-slot="{ id }" :label="$t('quotes.intro')">
                <Textarea :id="id" v-model="texts.intro" rows="3" />
              </Field>
              <Field v-slot="{ id }" :label="$t('quotes.outro')">
                <Textarea :id="id" v-model="texts.outro" rows="3" />
              </Field>

              <p v-if="freeErrors.invoice" class="text-[12px] text-destructive">{{ freeErrors.invoice }}</p>

              <div class="flex items-center gap-3">
                <Button type="submit" :disabled="(!contact && !newContact) || freeForm.processing">{{ $t('invoicesCreate.submit') }}</Button>
                <span class="tabular-nums">{{ formatEuros(freeTotal) }}</span>
              </div>
            </form>
          </div>

          <div class="hidden min-w-[420px] flex-1 min-[1180px]:block">
            <InvoicePreviewPane v-if="showPreview" :payload="previewPayload" />
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
