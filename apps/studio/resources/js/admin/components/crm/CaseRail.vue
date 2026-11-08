<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
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
        <div v-for="person in header.people" :key="person.id" class="py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none">
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
        <div v-for="instalment in header.paymentPlan.installments" :key="instalment.id" class="flex gap-2.5 py-[5px] text-small leading-[1.47]">
            <span class="w-24 flex-none text-ink-2">{{ instalment.status === 'paid' && instalment.paidOn ? day(instalment.paidOn) : day(instalment.dueOn) }}</span>
            <span :class="instalment.status === 'paid' ? 'text-ink-2' : ''">{{ formatEurosRounded(Number(instalment.amount)) }}</span>
            <span v-if="instalment.status === 'paid'" class="ml-auto text-ink-2">{{ instalment.statusLabel }}</span>
            <span v-else-if="instalment.isLate" class="ml-auto text-destructive">{{ $t('dashboard.overdue') }}</span>
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
