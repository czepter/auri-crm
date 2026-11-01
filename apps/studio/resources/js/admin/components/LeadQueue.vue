<script setup lang="ts">
/*
 * The open inquiries, grouped by working state — the same pane on the board
 * and on a single case, so moving between them does not change what is beside
 * the work. OpenLeads decides the groups; this only reads them.
 *
 * Narrowing it is the pane's own business: every open inquiry is already in
 * the props, so the term and the switch never go to the server. They do go in
 * the URL, so the pane a link opens is the pane the sender was looking at.
 */
import { computed, ref, watch } from "vue"
import { dayWithWeekday } from "@/admin/lib/dates"
import { NOTICEABLE_DAYS, filterQueue, since } from "@/admin/lib/leadQueue"
import { useQueueQuery } from "@/admin/lib/queueQuery"
import { Input } from "@/ui/input"
import { Queue, QueueGroup, QueueItem } from "@/ui/queue"
import { Switch } from "@/ui/switch"

const props = defineProps<{
    groups: { key: string; rows: App.Crm.Data.LeadQueueRowData[] }[]
}>()

const emit = defineEmits<{ "update:rows": [App.Crm.Data.LeadQueueRowData[]] }>()

const initial = new URLSearchParams(window.location.search)
const term = ref(initial.get("q") ?? "")
const waiting = ref(initial.get("wartet") === "1")

const query = useQueueQuery(() => ({
    q: term.value || null,
    wartet: waiting.value ? "1" : null,
}))

const shown = computed(() => filterQueue(props.groups, term.value, waiting.value))

// What ↑↓ beside the pane may reach — the rows still standing, not all of them.
watch(shown, (groups) => emit("update:rows", groups.flatMap((group) => group.rows)), {
    immediate: true,
})
</script>

<template>
    <Queue>
        <div class="flex flex-col gap-2 px-3.5 pb-3">
            <div class="flex items-center gap-2">
                <span
                    id="queue-waiting"
                    class="cursor-pointer text-small leading-none font-semibold tracking-[-0.012em]"
                    @click="waiting = !waiting"
                >
                    {{ $t("lead.waiting") }}
                </span>

                <Switch
                    v-model="waiting"
                    aria-labelledby="queue-waiting"
                    class="ml-auto"
                />
            </div>

            <Input
                v-model="term"
                size="sm"
                type="search"
                :placeholder="$t('common.search')"
            />
        </div>

        <template v-for="group in shown" :key="group.key">
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
                :title="dayWithWeekday(row.date, null) ?? $t('board.openDate')"
                :snippet="row.text"
                :active="row.open"
                :href="`/anfragen/${row.id}${query}`"
            />
        </template>
    </Queue>
</template>
