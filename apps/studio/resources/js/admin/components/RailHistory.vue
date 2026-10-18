<script setup lang="ts">
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { dayWithWeekday as day } from "@/admin/lib/dates";

const props = defineProps<{
    entries: App.Crm.Data.ThreadEntryData[];
}>();

// Notes have their own thread on the page; the rail is what the system recorded.
const events = computed(() => props.entries.filter((entry) => entry.system));

// The trace runs oldest first (BuildThread); the rail reads it the other way
// and keeps the first entry, because where the project came from stays the
// one line that does not scroll out.
const recent = computed(() => events.value.slice(-4).reverse());
const origin = computed(() => (events.value.length > 4 ? events.value[0] : null));

const page = usePage();
const byline = (author: string | null) =>
    page.props.soloStudio && author === page.props.auth.user?.name ? null : author;
</script>

<template>
    <!-- Der Verlauf steht am Fuß der Rail: er wird gelesen, wenn die
         Gruppen darüber eine Frage offen lassen. -->
    <div v-if="events.length" class="mt-7">
        <h3
            class="m-0 mb-[7px] text-small leading-none font-medium text-ink-2"
        >
            {{ $t("project.history") }}
        </h3>
        <div
            v-for="entry in recent"
            :key="entry.key"
            class="flex gap-2.5 py-1 text-small leading-[1.47]"
        >
            <span class="w-[74px] flex-none text-ink-2">{{ entry.at ? day(entry.at) : "" }}</span>
            <span class="min-w-0 flex-1">{{ entry.body }}</span>
            <span v-if="byline(entry.author)" class="text-ink-2">{{ entry.author }}</span>
        </div>
        <template v-if="origin">
            <div class="py-0.5 text-small leading-[1.47] text-faint">⋯</div>
            <div class="flex gap-2.5 py-1 text-small leading-[1.47]">
                <span class="w-[74px] flex-none text-ink-2">{{ origin.at ? day(origin.at) : "" }}</span>
                <span class="min-w-0 flex-1">{{ origin.body }}</span>
                <span v-if="byline(origin.author)" class="text-ink-2">{{ origin.author }}</span>
            </div>
        </template>
    </div>
</template>
