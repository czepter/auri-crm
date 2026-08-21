<script lang="ts">
/*
 * The open inquiries, grouped by working state — the same pane on the board
 * and on a single case, so moving between them does not change what is beside
 * the work. BuildLeadQueue decides the groups; this only reads them.
 */
export type LeadQueueRow = {
    id: string
    name: string
    date: string | null
    snippet: string | null
    open: boolean
    waitingDays: number
}
</script>

<script setup lang="ts">
import { Queue, QueueGroup, QueueItem } from "@/ui/queue"

defineProps<{ groups: { key: string; rows: LeadQueueRow[] }[] }>()

// Below this the wait is the normal course of an inquiry; colouring it would
// mark every row. Same floor the inbox uses.
const NOTICEABLE_DAYS = 3

// Same wording the board's table carries in its Zuletzt column.
function since(days: number) {
    return days < 1
        ? "heute"
        : new Intl.RelativeTimeFormat("de-DE", { numeric: "auto" }).format(
              -days,
              "day",
          )
}

function day(iso: string | null) {
    return iso
        ? new Date(iso).toLocaleDateString("de-DE", {
              weekday: "short",
              day: "2-digit",
              month: "2-digit",
          })
        : null
}
</script>

<template>
    <Queue>
        <template v-for="group in groups" :key="group.key">
            <QueueGroup
                :title="$t(`lead.group.${group.key}`)"
                :count="group.rows.length"
            />

            <QueueItem
                v-for="row in group.rows"
                :key="row.id"
                :name="row.name"
                :meta="since(row.waitingDays)"
                :alert="row.waitingDays >= NOTICEABLE_DAYS"
                :title="day(row.date) ?? $t('board.openDate')"
                :snippet="row.snippet"
                :active="row.open"
                :href="`/anfragen/${row.id}`"
            />
        </template>
    </Queue>
</template>
