<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Chip from '@/ui/page/Chip.vue'
import { Button } from '@/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import LockDialog from '@/admin/components/finance/LockDialog.vue'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  report: App.Finance.Data.EuerReportData
  years: number[]
  lockable: boolean
}>()
useLiveProps(['report', 'lockable'])

const lockOpen = ref(false)
const standard = computed(() => props.report.vatMode === 'standard')
const year = computed(() => props.report.year)
const list = (path: string, query = '') => `${path}?jahr=${year.value}${query}`

const checklist = computed(() => {
  const c = props.report.checklist

  return [
    { key: 'withoutReceipt', count: c.withoutReceipt, href: list('/finanzen/ausgaben', '&ohne-beleg=1') },
    { key: 'drafts', count: c.drafts, href: list('/finanzen/ausgaben', '&status=draft') },
    { key: 'openInvoices', count: c.openInvoices, href: '/rechnungen', remaining: c.openInvoicesRemaining },
    { key: 'foreign', count: c.foreignWithoutEur, href: list('/finanzen/ausgaben', '&status=booked') },
  ].filter((row) => row.count > 0)
})

const ROW = 'flex items-baseline justify-between gap-4 border-b border-rule py-2.5'
const LINK = 'tabular-nums text-foreground underline decoration-line-hover underline-offset-4 hover:decoration-foreground'
</script>

<template>
  <AdminLayout active="euer">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('financeEuer.title', { year })">
          <Select :model-value="String(year)" @update:model-value="(value) => router.get('/finanzen/euer', { jahr: Number(value) }, { preserveScroll: true, replace: true })">
            <SelectTrigger :aria-label="$t('financeEuer.year')" size="sm" class="w-[100px]"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="y in years" :key="y" :value="String(y)">{{ y }}</SelectItem>
            </SelectContent>
          </Select>
          <Chip v-if="report.lockedAt">{{ $t('financeEuer.lockedOn', { date: dayWithWeekday(report.lockedAt) }) }}</Chip>
          <Select v-else :model-value="report.vatMode" @update:model-value="(value) => router.patch(`/finanzen/euer/${year}/ust`, { vat_mode: String(value) }, { preserveScroll: true })">
            <SelectTrigger :aria-label="$t('financeEuer.vatStatus')" size="sm" class="w-[170px]"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="standard">{{ $t('financeEuer.standard') }}</SelectItem>
              <SelectItem value="small_business">{{ $t('financeEuer.smallBusiness') }}</SelectItem>
            </SelectContent>
          </Select>
          <div class="ml-auto flex items-center gap-2">
            <Button as="a" variant="outline" :href="`/finanzen/euer/${year}/pdf`" target="_blank">{{ $t('financeEuer.pdf') }}</Button>
            <Button v-if="lockable" variant="default" type="button" @click="lockOpen = true">{{ $t('financeEuer.lock') }}</Button>
          </div>
        </PageHead>

        <div class="grid max-w-[1100px] items-start gap-x-12 gap-y-8 px-6 pt-6 pb-10 max-[640px]:px-5 min-[900px]:grid-cols-2">
          <section>
            <h2 class="text-ui font-semibold">{{ $t('financeEuer.income') }}</h2>
            <div :class="ROW"><span>{{ $t('financeEuer.operatingIncome') }}</span><Link :class="LINK" href="/rechnungen">{{ formatEuros(report.operatingIncome) }}</Link></div>
            <div v-if="report.creditNotes" :class="[ROW, 'text-ink-2']"><span class="pl-3">{{ $t('financeEuer.creditNotes') }}</span><Link :class="LINK" href="/gutschriften">−{{ formatEuros(report.creditNotes) }}</Link></div>
            <div v-if="standard" :class="ROW"><span>{{ $t('financeEuer.collectedVat') }}</span><Link :class="LINK" href="/rechnungen">{{ formatEuros(report.collectedVat) }}</Link></div>
            <div :class="ROW"><span>{{ $t('financeEuer.assetSales') }}</span><Link :class="LINK" :href="list('/finanzen/anlagen')">{{ formatEuros(report.assetSales) }}</Link></div>
            <div :class="[ROW, 'font-semibold']"><span>{{ $t('financeEuer.sum') }}</span><span class="tabular-nums">{{ formatEuros(report.income) }}</span></div>
          </section>

          <section>
            <h2 class="text-ui font-semibold">{{ $t('financeEuer.expenses') }}</h2>
            <div v-for="category in report.categories" :key="category.key" :class="ROW">
              <span>{{ category.label }}</span>
              <Link :class="LINK" :href="list('/finanzen/ausgaben', `&kategorie=${category.key}`)">{{ formatEuros(category.amount) }}</Link>
            </div>
            <div :class="ROW"><span>{{ $t('financeEuer.depreciation') }}</span><Link :class="LINK" :href="list('/finanzen/anlagen')">{{ formatEuros(report.depreciation) }}</Link></div>
            <div :class="ROW"><span>{{ $t('financeEuer.residual') }}</span><Link :class="LINK" :href="list('/finanzen/anlagen')">{{ formatEuros(report.residualBookValue) }}</Link></div>
            <div v-if="standard" :class="ROW"><span>{{ $t('financeEuer.inputVat') }}</span><Link :class="LINK" :href="list('/finanzen/ausgaben', '&status=booked')">{{ formatEuros(report.paidInputVat) }}</Link></div>
            <div :class="[ROW, 'font-semibold']"><span>{{ $t('financeEuer.sum') }}</span><span class="tabular-nums">{{ formatEuros(report.expenses) }}</span></div>
          </section>

          <section class="min-[900px]:col-span-2">
            <div :class="[ROW, 'text-[18px] font-semibold']"><span>{{ $t('financeEuer.profit') }}</span><span class="tabular-nums">{{ formatEuros(report.profit) }}</span></div>
          </section>

          <section v-if="checklist.length" class="min-[900px]:col-span-2">
            <h2 class="text-ui font-semibold">{{ $t('financeEuer.checklist') }}</h2>
            <div v-for="row in checklist" :key="row.key" :class="ROW">
              <span>{{ $t(`financeEuer.${row.key}`) }}</span>
              <Link :class="LINK" :href="row.href">
                {{ row.count }}<template v-if="row.remaining"> · {{ formatEuros(row.remaining) }}</template>
              </Link>
            </div>
          </section>
        </div>
      </div>
    </Page>
    <LockDialog v-model:open="lockOpen" :year="year" />
  </AdminLayout>
</template>
