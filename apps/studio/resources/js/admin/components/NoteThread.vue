<script setup lang="ts">
import { computed } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import { Textarea } from "@/ui/textarea";
import { dayWithWeekday } from "@/admin/lib/dates";

/*
 * The internal trace read as what it is: a conversation the studio has with
 * itself. Notes are bubbles, what the system recorded stands between them as
 * a quiet line, and both are sorted into one order — three blocks stacked by
 * kind made the newest note sit above an event from March.
 */
const props = defineProps<{
    entries: App.Crm.Data.ThreadEntryData[];
    action: string;
}>();

const me = computed(() => usePage().props.auth.user?.name ?? null);

// Tasks carry a due date and nothing else; only a real timestamp gets a clock.
function timeOf(iso: string | null) {
    return iso && iso.length > 10
        ? new Date(iso).toLocaleTimeString("de-DE", {
              hour: "2-digit",
              minute: "2-digit",
          })
        : "";
}

const note = useForm({ body: "" });

function send() {
    if (!note.body.trim()) return;

    note.post(props.action, {
        preserveScroll: true,
        onSuccess: () => note.reset(),
    });
}
</script>

<template>
    <div class="max-w-[820px]">
        <div class="flex flex-col gap-1.5">
            <template v-for="(entry, index) in entries" :key="entry.key">
                <div
                    v-if="dayWithWeekday(entry.at, '') !== dayWithWeekday(entries[index - 1]?.at, '')"
                    class="mt-4 mb-1.5 flex items-center gap-3 first:mt-0"
                >
                    <span class="h-px flex-1 bg-[var(--rule)]" />
                    <span
                        class="text-[10.5px] leading-none tracking-[0.06em] uppercase text-ink-2"
                    >{{ dayWithWeekday(entry.at, '') }}</span>
                    <span class="h-px flex-1 bg-[var(--rule)]" />
                </div>

                <p
                    v-if="entry.system"
                    class="px-4 py-0.5 text-center text-[11.5px] leading-[1.45] text-ink-2"
                >
                    {{ entry.body
                    }}<span v-if="entry.author"> — {{ entry.author }}</span>
                </p>

                <div v-else class="flex" :class="entry.author === me && 'justify-end'">
                    <div
                        class="max-w-[78%] px-3 py-2"
                        :class="
                            entry.author === me
                                ? 'rounded-[14px] rounded-br-[4px] bg-action text-white'
                                : 'rounded-[14px] rounded-bl-[4px] bg-sunk'
                        "
                    >
                        <span
                            v-if="entry.author !== me"
                            class="mb-[3px] block text-[11px] leading-none font-semibold text-ink-2"
                        >{{ entry.author }}</span>
                        <span
                            class="block text-[13.5px] leading-[1.5] tracking-[-0.01em] whitespace-pre-wrap"
                        >{{ entry.body }}</span>
                        <span
                            class="mt-1 block text-[10.5px] leading-none tabular-nums"
                            :class="entry.author === me ? 'text-white/65' : 'text-ink-2'"
                        >{{ timeOf(entry.at) }}</span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Sticky, because the thread grows and the field one writes into is
             the one part of it that may never scroll away. -->
        <form class="sticky bottom-0 mt-4 bg-background pt-2 pb-3" @submit.prevent="send">
            <div
                class="flex items-end gap-2 rounded-[16px] bg-sunk py-1.5 pr-1.5 pl-3.5 transition-[background-color,box-shadow] duration-[120ms] ease-out focus-within:bg-sunk-2 focus-within:shadow-[inset_0_0_0_2px_var(--accent)]"
            >
                <Textarea
                    v-model="note.body"
                    rows="1"
                    class="field-sizing-content max-h-40 min-h-0 resize-none rounded-none border-0 bg-transparent p-0 py-[5px] text-[13.5px] leading-[1.45] tracking-[-0.01em] placeholder:text-[#B0B0B5] hover:border-transparent focus:border-transparent"
                    :placeholder="$t('project.notePlaceholder')"
                    @keydown.enter.exact.prevent="send"
                />
                <button
                    type="submit"
                    :aria-label="$t('lead.addNote')"
                    :disabled="!note.body.trim() || note.processing"
                    class="grid size-[30px] flex-none cursor-pointer place-items-center rounded-full border-0 bg-action text-white transition-[filter,opacity] duration-[120ms] ease-out hover:brightness-[1.08] disabled:cursor-default disabled:opacity-35"
                >
                    <svg
                        viewBox="0 0 16 16"
                        class="size-[15px]"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M8 13.5V3M3.5 7.5 8 3l4.5 4.5" />
                    </svg>
                </button>
            </div>
            <p
                v-if="note.errors.body"
                class="mt-1.5 text-[11.5px] leading-[1.45] text-destructive"
            >
                {{ note.errors.body }}
            </p>
        </form>
    </div>
</template>
