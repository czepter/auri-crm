<script setup lang="ts">
import { computed } from "vue";
import { dayWithWeekday as day } from "@/admin/lib/dates";

const props = defineProps<{
    entries: App.Crm.Data.ThreadEntryData[];
}>();

// The trace runs oldest first (BuildThread); the rail reads it the other way
// and keeps the first entry, because where the project came from stays the
// one line that does not scroll out.
const recent = computed(() => props.entries.slice(-4).reverse());
const origin = computed(() => (props.entries.length > 4 ? props.entries[0] : null));
</script>

<template>
    <!-- Der Verlauf steht am Fuß der Rail: er wird gelesen, wenn die
         Gruppen darüber eine Frage offen lassen. -->
    <div v-if="entries.length" class="mt-7 border-t border-line-hover pt-3">
        <h3
            class="m-0 mb-[7px] text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2"
        >
            {{ $t("project.history") }}
        </h3>
        <div
            v-for="entry in recent"
            :key="entry.key"
            class="flex gap-2.5 py-1 text-[11.5px] leading-[1.47]"
        >
            <span class="w-[74px] flex-none text-ink-2">{{ entry.at ? day(entry.at) : "" }}</span>
            <span class="min-w-0 flex-1">{{ entry.body }}</span>
            <span v-if="entry.author" class="text-ink-2">{{ entry.author }}</span>
        </div>
        <template v-if="origin">
            <div class="py-0.5 text-[11.5px] leading-[1.47] text-faint">⋯</div>
            <div class="flex gap-2.5 py-1 text-[11.5px] leading-[1.47]">
                <span class="w-[74px] flex-none text-ink-2">{{ origin.at ? day(origin.at) : "" }}</span>
                <span class="min-w-0 flex-1">{{ origin.body }}</span>
                <span v-if="origin.author" class="text-ink-2">{{ origin.author }}</span>
            </div>
        </template>
    </div>
</template>
