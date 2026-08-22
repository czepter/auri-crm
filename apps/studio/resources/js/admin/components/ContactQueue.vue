<script setup lang="ts">
/*
 * The pane beside a contact: every contact, in the three groups C-15's table
 * already groups them into. Same pane as the inquiries', so moving from an
 * inquiry to a contact does not change what stands beside the work.
 *
 * Narrowing it never goes to the server — every contact is already in the
 * props. The term does go in the URL, so the pane a link opens is the pane
 * the sender was looking at.
 */
import { computed, ref, watch } from "vue"
import { groupContacts } from "@/admin/lib/contactQueue"
import { dayWithWeekday } from "@/admin/lib/dates"
import { Input } from "@/ui/input"
import { Queue, QueueGroup, QueueItem } from "@/ui/queue"

const props = defineProps<{
    contacts: App.Crm.Data.ContactRowData[]
    activeId: string
}>()

const term = ref(new URLSearchParams(window.location.search).get("q") ?? "")

const query = computed(() => (term.value ? `?q=${encodeURIComponent(term.value)}` : ""))

const shown = computed(() => groupContacts(props.contacts, term.value))

// Replace rather than push: a history entry per keystroke makes the back
// button unusable.
watch(term, () => {
    const url = new URL(window.location.href)

    term.value ? url.searchParams.set("q", term.value) : url.searchParams.delete("q")

    history.replaceState(history.state, "", url)
})

function reach(contact: App.Crm.Data.ContactRowData) {
    return [contact.email, contact.phone].filter(Boolean).join(" · ") || null
}
</script>

<template>
    <Queue>
        <div class="px-3.5 pb-3">
            <Input
                v-model="term"
                size="sm"
                type="search"
                :placeholder="$t('contacts.search')"
            />
        </div>

        <template v-for="group in shown" :key="group.key">
            <QueueGroup
                :title="$t(`contacts.group.${group.key}`)"
                :count="group.rows.length"
            />

            <QueueItem
                v-for="contact in group.rows"
                :key="contact.id"
                :name="contact.name"
                :meta="dayWithWeekday(contact.nextDate, null)"
                :title="reach(contact)"
                :snippet="contact.latestProject"
                :active="contact.id === activeId"
                :href="`/kontakte/${contact.id}${query}`"
            />
        </template>
    </Queue>
</template>
