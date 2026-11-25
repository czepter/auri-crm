<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import RailHistory from "@/admin/components/crm/RailHistory.vue"
import SettlePlanDialog from "@/admin/components/payments/SettlePlanDialog.vue"
import { dayWithWeekday as day } from "@/admin/lib/dates"
import { roleLabel } from "@/admin/lib/roles"
import { formatEurosRounded } from "@/lib/money"
import RailItem from "@/ui/page/RailItem.vue"
import RailSection from "@/ui/page/RailSection.vue"

defineProps<{ header: App.Crm.Screens.CaseHeaderData }>()

const link = "block truncate text-small leading-[1.47] text-ring no-underline hover:underline max-[1080px]:flex max-[1080px]:min-h-11 max-[1080px]:items-center"
</script>

<template>
    <RailSection v-if="header.people.length" :title="$t('lead.people')">
        <div v-for="person in header.people" :key="person.id" class="py-2 border-t border-rule first:border-t-0">
            <div class="flex items-center gap-2">
                <Link :href="`/kontakte/${person.id}`" class="min-w-0 truncate text-small leading-[1.47] font-semibold tracking-[-0.012em]">
                    {{ person.name }}
                </Link>
                <span class="ml-auto flex-none rounded-[var(--r-sm)] bg-sunk-2 px-2 py-0.5 text-small leading-[1.4] text-ink-2">
                    {{ roleLabel(person.role) }}
                </span>
            </div>
            <a v-if="person.phone" :class="[link, 'tabular-nums']" :href="`tel:${person.phone}`">{{ person.phone }}</a>
            <a v-if="person.email" :class="link" :href="`mailto:${person.email}`">{{ person.email }}</a>
        </div>
    </RailSection>

    <RailSection v-if="header.paymentPlan" :title="$t('dashboard.payments')">
        <div v-for="instalment in header.paymentPlan.installments" :key="instalment.id" class="py-[5px] text-small leading-[1.47]">
            <div class="flex gap-2.5">
                <span :class="['min-w-0 flex-1 truncate', instalment.status === 'paid' ? 'text-ink-2' : '']">{{ instalment.label }}</span>
                <span class="tabular-nums" :class="instalment.status === 'paid' ? 'text-ink-2' : ''">{{ formatEurosRounded(Number(instalment.amount)) }}</span>
            </div>
            <div class="flex gap-2.5 text-ink-2">
                <span>{{ instalment.status === 'paid' && instalment.paidOn ? day(instalment.paidOn) : day(instalment.dueOn) }}</span>
                <span v-if="instalment.status === 'paid'">{{ instalment.statusLabel }}</span>
                <span v-else-if="instalment.isLate" class="text-destructive">{{ $t('dashboard.overdue') }}</span>
                <Link v-if="instalment.invoiceId" :href="`/rechnungen/${instalment.invoiceId}`" class="ml-auto text-ring no-underline hover:underline">{{ instalment.invoiceNumber }}</Link>
                <button
                    v-else-if="header.canManagePaymentPlan && instalment.status !== 'cancelled'"
                    type="button"
                    class="ml-auto cursor-pointer border-0 bg-transparent p-0 text-small text-ring hover:underline"
                    @click="router.post(`/raten/${instalment.id}/rechnung`, {}, { preserveScroll: true })"
                >{{ $t('payments.issueInvoice') }}</button>
            </div>
        </div>

        <SettlePlanDialog
            v-if="header.canManagePaymentPlan && header.primaryShootCancelled && !header.paymentPlan.settledOn"
            class="mt-2"
            :plan="header.paymentPlan"
        />
    </RailSection>

    <RailSection v-if="header.galleries.length" :title="$t('contacts.galleries')">
        <RailItem
            v-for="gallery in header.galleries"
            :key="gallery.id"
            :title="gallery.title ?? '—'"
            :href="`/galerien/${gallery.id}`"
            :meta="gallery.expiresAt ? $t('contacts.expiresOn', { date: day(gallery.expiresAt) }) : null"
        />
    </RailSection>

    <RailHistory :entries="header.history" />
</template>
