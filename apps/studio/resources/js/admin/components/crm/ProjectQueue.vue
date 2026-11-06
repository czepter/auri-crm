<script setup lang="ts">
/*
 * The booked projects beside the one open — LeadQueue's pane for the
 * production side. OpenCases decides the groups; this only narrows them,
 * in the URL, so the pane a link opens is the pane the sender was looking at.
 */
import { computed, ref, watch } from "vue"
import { dayWithWeekday } from "@/admin/lib/dates"
import { useQueueQuery } from "@/admin/lib/queueQuery"
import { fold } from "@/admin/lib/search"
import { Input } from "@/ui/input"
import { Queue, QueueGroup, QueueItem } from "@/ui/queue"
import { Switch } from "@/ui/switch"

const props = defineProps<{
    groups: { key: string; rows: App.Crm.Data.CaseQueueRowData[] }[]
}>()

const emit = defineEmits<{ "update:rows": [App.Crm.Data.CaseQueueRowData[]] }>()

const initial = new URLSearchParams(window.location.search)
const term = ref(initial.get("q") ?? "")
const due = ref(initial.get("faellig") === "1")

const query = useQueueQuery(() => ({
    q: term.value || null,
    faellig: due.value ? "1" : null,
}))

// "Später" is the one group with nothing to do this week.
const shown = computed(() => {
    const needle = fold(term.value.trim())

    return props.groups
        .filter((group) => !due.value || group.key !== "spaeter")
        .map((group) => ({
            ...group,
            rows: group.rows.filter((row) => !needle || fold(row.name).includes(needle)),
        }))
})

// What ↑↓ beside the pane may reach — the rows still standing, not all of them.
watch(shown, (groups) => emit("update:rows", groups.flatMap((group) => group.rows)), { immediate: true })
</script>

<template>
    <Queue class="max-[640px]:hidden">
        <div class="flex flex-col gap-2 px-3 pb-3">
            <div class="flex items-center gap-2">
                <span
                    id="queue-due"
                    class="cursor-pointer text-small leading-none font-semibold tracking-[-0.012em]"
                    @click="due = !due"
                >
                    {{ $t("projectQueue.due") }}
                </span>

                <Switch v-model="due" aria-labelledby="queue-due" class="ml-auto" />
            </div>

            <Input :aria-label="$t('common.search')" v-model="term" size="sm" type="search" :placeholder="$t('common.search')" />
        </div>

        <template v-for="group in shown" :key="group.key">
            <QueueGroup
                :title="$t(`projectQueue.group.${group.key}`)"
                :count="group.rows.length"
            />

            <QueueItem
                v-for="row in group.rows"
                :key="row.id"
                :name="row.name"
                :meta="row.days === null ? undefined : $t('projectQueue.days', row.days)"
                :alert="row.days !== null"
                :step="row.reason === 'payment' ? $t('projectQueue.reason.payment') : row.step"
                :title="dayWithWeekday(row.date, null) ?? undefined"
                :active="row.open"
                :href="`/auftraege/${row.id}${query}`"
            />
        </template>
    </Queue>
</template>
