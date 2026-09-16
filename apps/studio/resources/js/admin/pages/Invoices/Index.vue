<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import { Input } from '@/ui/input'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { DatePicker } from '@/ui/date-picker'
import { toIso } from '@/ui/date-picker/parse'
import InstallmentPaidDialog from '@/admin/components/InstallmentPaidDialog.vue'
import InstallmentStornoDialog from '@/admin/components/InstallmentStornoDialog.vue'
import CreditNoteDialog from '@/admin/components/CreditNoteDialog.vue'
import PaymentPlanDialog from '@/admin/components/PaymentPlanDialog.vue'
import { formatEuros } from '@/admin/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'

type Installment = App.Payments.Data.PaymentInstallmentData

const props = defineProps<{ plans: App.Payments.Data.InvoicePlanData[] }>()

const { t } = useI18n()

const query = ref('')
const filter = ref('all')

const filters = computed(() => ['all', 'open', 'paid', 'cancelled'].map((value) => ({ value, label: t(`invoicesOverview.${value}`) })))

const OPEN = ['scheduled', 'invoiced', 'partial']

const CHIP: Record<string, string> = { scheduled: 'next', invoiced: 'now', partial: 'now', paid: 'done', cancelled: 'off' }

function matchesFilter(installment: Installment) {
  if (filter.value === 'open') return OPEN.includes(installment.status)
  if (filter.value === 'all') return true

  return installment.status === filter.value
}

const shown = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')

  return props.plans
    .map((entry) => ({
      ...entry,
      // #418: a settled plan is only paperwork now, and only what was issued is paperwork.
      rows: entry.plan.installments.filter((installment) =>
        (!entry.plan.settledOn || installment.invoiceNumber !== null) && matchesFilter(installment)),
    }))
    .filter((entry) => entry.rows.length && (!needle
      || [entry.projectName, entry.contact ?? '', ...entry.rows.map((row) => row.invoiceNumber ?? '')]
        .join(' ').toLocaleLowerCase('de').includes(needle)))
})

function issue(installment: Installment) {
  router.post(`/raten/${installment.id}/rechnung`, { liste: true })
}

function moveDue(installment: Installment, dueOn: string | null) {
  if (dueOn && dueOn !== installment.dueOn) {
    router.patch(`/raten/${installment.id}/faelligkeit`, { due_on: dueOn }, { preserveScroll: true })
  }
}
</script>

<template>
  <AdminLayout active="rechnungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('invoicesOverview.title')" />

        <template v-if="plans.length">
          <div class="flex flex-wrap items-center gap-3 px-8 pt-4.5">
            <Input v-model="query" class="h-8 w-64" type="search" :placeholder="$t('invoicesOverview.searchPlaceholder')" />
            <ChoiceGroup v-model="filter" variant="segmented" :options="filters" />
          </div>

          <Table>
            <thead>
              <tr>
                <TableHead>{{ $t('invoicesOverview.rate') }}</TableHead>
                <TableHead>{{ $t('invoicesOverview.number') }}</TableHead>
                <TableHead>{{ $t('invoicesOverview.dueOn') }}</TableHead>
                <TableHead align="right">{{ $t('invoicesOverview.amount') }}</TableHead>
                <TableHead>{{ $t('invoicesOverview.status') }}</TableHead>
                <TableHead />
              </tr>
            </thead>
            <tbody>
              <template v-for="entry in shown" :key="entry.plan.id">
                <TableRow>
                  <TableCell tone="name" colspan="3">
                    <Link :href="`/auftraege/${entry.projectId}/angebote`">{{ entry.projectName }}</Link>
                    <span v-if="entry.contact" class="ml-2 font-normal text-ink-2">{{ entry.contact }}</span>
                  </TableCell>
                  <TableCell align="right">{{ formatEuros(entry.plan.total) }}</TableCell>
                  <TableCell tone="muted" class="whitespace-nowrap">{{ $t('payments.open', { amount: formatEuros(entry.plan.openTotal) }) }}</TableCell>
                  <TableCell align="right">
                    <PaymentPlanDialog v-if="!entry.plan.settledOn" :plan="entry.plan" />
                  </TableCell>
                </TableRow>

                <template v-for="installment in entry.rows" :key="installment.id">
                  <TableRow>
                    <TableCell class="pl-12">{{ installment.label }}</TableCell>
                    <TableCell tone="muted" class="whitespace-nowrap">
                      <a v-if="installment.hasInvoiceDocument" :href="`/raten/${installment.id}/rechnung`" target="_blank">{{ installment.invoiceNumber }}</a>
                      <template v-else>{{ installment.invoiceNumber ?? '—' }}</template>
                    </TableCell>
                    <TableCell>
                      <DatePicker
                        v-if="OPEN.includes(installment.status) && !entry.plan.settledOn"
                        :model-value="installment.dueOn"
                        size="sm"
                        class="w-36"
                        @blur="(text) => moveDue(installment, toIso(text))"
                      />
                      <template v-else>{{ dayWithWeekday(installment.dueOn) }}</template>
                    </TableCell>
                    <TableCell align="right">
                      {{ formatEuros(installment.amount) }}
                      <span v-if="installment.status === 'partial'" class="block text-[11.5px] text-ink-2">
                        {{ $t('payments.open', { amount: formatEuros(installment.remaining) }) }}
                      </span>
                    </TableCell>
                    <TableCell>
                      <span class="chip" :class="installment.isLate ? 'fail' : CHIP[installment.status]">
                        {{ installment.isLate ? $t('invoicesOverview.overdue') : installment.statusLabel }}
                      </span>
                      <span v-if="installment.viewedOn || installment.sentOn" class="ml-2 text-[11.5px] text-ink-2">
                        {{ $t(installment.viewedOn ? 'invoicesOverview.viewed' : 'invoicesOverview.sent') }}
                      </span>
                    </TableCell>
                    <TableCell align="right">
                      <div v-if="!entry.plan.settledOn" class="flex flex-wrap justify-end gap-1.5">
                        <Button
                          v-if="installment.invoiceNumber === null && installment.status !== 'cancelled'"
                          variant="outline"
                          type="button"
                          @click="issue(installment)"
                        >{{ $t('payments.issueInvoice') }}</Button>
                        <Button
                          v-if="installment.invoiceNumber !== null && installment.status !== 'cancelled'"
                          :as="Link"
                          variant="outline"
                          :href="`/raten/${installment.id}/rechnung/versenden?liste=1`"
                        >{{ $t('invoicesOverview.send') }}</Button>
                        <InstallmentPaidDialog
                          v-if="installment.status !== 'cancelled' && installment.remaining > 0"
                          :installment-id="installment.id"
                          :remaining="installment.remaining"
                        />
                        <InstallmentStornoDialog v-if="installment.status === 'invoiced'" :installment-id="installment.id" />
                        <CreditNoteDialog
                          v-if="installment.status === 'paid'"
                          :installment-id="installment.id"
                          :amount="installment.amount"
                        />
                      </div>
                    </TableCell>
                  </TableRow>

                  <TableRow v-for="note in installment.creditNotes" :key="note.id">
                    <TableCell class="pl-12" tone="muted">{{ $t('payments.creditNote') }}</TableCell>
                    <TableCell tone="muted">
                      <a v-if="note.hasDocument" :href="`/raten/${installment.id}/gutschrift/${note.id}`" target="_blank">{{ note.number }}</a>
                      <template v-else>{{ note.number }}</template>
                    </TableCell>
                    <TableCell tone="muted">{{ dayWithWeekday(note.issuedOn) }}</TableCell>
                    <TableCell align="right" tone="muted">−{{ formatEuros(note.amount) }}</TableCell>
                    <TableCell colspan="2" />
                  </TableRow>
                </template>
              </template>
            </tbody>
          </Table>
        </template>
      </div>
    </Page>
  </AdminLayout>
</template>
