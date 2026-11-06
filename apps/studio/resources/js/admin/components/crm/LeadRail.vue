<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import RailHistory from "@/admin/components/crm/RailHistory.vue"
import { dayWithWeekday as day } from "@/admin/lib/dates"
import RailSection from "@/ui/page/RailSection.vue"

defineProps<{
    typeLabel: string
    sourceLabel: string | null
    dated: boolean
    week: { date: string; what: string; projectId: string }[]
    conflicts: App.Crm.Data.DateConflictData[]
    thread: App.Crm.Data.ThreadEntryData[]
}>()
</script>

<template>
    <RailSection :title="`${$t('leads.type')} · ${$t('leads.source')}`">
        <div class="text-small leading-[1.47]">{{ [typeLabel, sourceLabel].filter(Boolean).join(" · ") }}</div>
    </RailSection>

    <RailSection v-if="dated" :title="$t('lead.week')">
        <template v-if="week.length">
            <Link
                v-for="entry in week"
                :key="`${entry.date}${entry.what}`"
                :href="`/auftraege/${entry.projectId}`"
                class="group -mx-2 flex gap-2.5 rounded-lg px-2 py-1.5 text-small leading-[1.47] shadow-[inset_0_1px_0_var(--rule)] transition-colors first:shadow-none hover:bg-paper"
            >
                <span class="w-[62px] flex-none font-semibold tabular-nums">{{ day(entry.date) }}</span>
                <span class="text-ink-2 transition-colors group-hover:text-foreground">{{ entry.what }}</span>
            </Link>
        </template>
        <div v-else class="-mx-2 flex gap-2.5 rounded-lg bg-paper px-2 py-1.5 text-small leading-[1.47]">
            <span class="font-medium text-ok">{{ $t("lead.weekFree") }}</span>
        </div>
    </RailSection>

    <RailSection v-if="conflicts.length" :title="$t('lead.conflicts')">
        <div
            v-for="(conflict, index) in conflicts"
            :key="index"
            class="-mx-2 flex gap-2.5 rounded-lg bg-paper px-2 py-1.5 text-small leading-[1.47]"
        >
            <span class="font-medium text-due">{{ conflict.label ?? conflict.projectName }}</span>
        </div>
    </RailSection>

    <RailHistory :entries="thread" />
</template>
