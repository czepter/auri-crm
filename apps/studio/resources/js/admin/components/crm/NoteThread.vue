<script setup lang="ts">
import { computed, ref } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { Check, X } from "lucide-vue-next";
import { Button } from "@/ui/button";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/ui/alert-dialog";
import { DatePicker } from "@/ui/date-picker";
import { Textarea } from "@/ui/textarea";
import CappedList from "@/admin/components/crm/CappedList.vue";
import Composer from "@/admin/components/crm/Composer.vue";
import { dayWithWeekday } from "@/admin/lib/dates";

/*
 * The internal trace read as what it is: a conversation the studio has with
 * itself. Notes are bubbles, what the system recorded stands between them as
 * a quiet line, and both are sorted into one order — three blocks stacked by
 * kind made the newest note sit above an event from March.
 */
const props = withDefaults(
    defineProps<{
        entries: App.Crm.Data.ThreadEntryData[];
        action: string;
        compact?: boolean;
        limit?: number;
        moreHref?: string;
        canManage?: boolean;
    }>(),
    { canManage: true },
);

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

// Empty is the moment it is written. Filled when the call it records
// happened earlier — the thread sorts on it.
const note = useForm({ body: "", occurred_at: "" });

function send() {
    if (!note.body.trim()) return;

    note.post(props.action, {
        preserveScroll: true,
        onSuccess: () => note.reset(),
    });
}

const editing = ref<string | null>(null);
const edit = useForm({ body: "" });

function startEdit(entry: App.Crm.Data.ThreadEntryData) {
    edit.clearErrors();
    edit.body = entry.body;
    editing.value = entry.note;
}

function save() {
    if (!edit.body.trim()) return;

    edit.patch(`/notizen/${editing.value}`, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}

// Kept apart from the open state: AlertDialogAction closes the dialog before
// its @click runs, so an id cleared on close is already null in destroy().
const deleting = ref<string | null>(null);
const confirmingDelete = ref(false);

function destroy() {
    router.delete(`/notizen/${deleting.value}`, { preserveScroll: true });
}
</script>

<template>
    <div class="max-w-[820px]">
        <template v-if="canManage">
            <Composer
                v-model="note.body"
                :placeholder="$t('project.notePlaceholder')"
                :label="$t('project.notePlaceholder')"
                :send-label="$t('lead.addNote')"
                :busy="note.processing"
                @send="send"
            />
            <details class="mt-1.5">
                <summary
                    class="w-fit cursor-pointer text-small text-ink-2 marker:text-ink-2"
                >{{ $t('project.advanced') }}</summary>
                <label class="mt-1.5 flex items-center gap-2 text-small text-ink-2">
                    {{ $t('project.noteOccurredAt') }}
                    <DatePicker v-model="note.occurred_at" size="sm" />
                </label>
            </details>

            <p
                v-if="note.errors.body"
                class="mt-1.5 text-small leading-[1.45] text-destructive"
            >
                {{ note.errors.body }}
            </p>
        </template>

        <div class="mt-4 flex flex-col gap-1.5">
            <CappedList
                v-slot="{ item: entry, index }"
                :items="entries"
                :limit="limit ?? entries.length"
                :more-href="moreHref ?? ''"
            >
                <div
                    v-if="!compact && dayWithWeekday(entry.at, '') !== dayWithWeekday(entries[index - 1]?.at, '')"
                    class="mt-4 mb-1.5 flex items-center gap-3 first:mt-0"
                >
                    <span class="h-px flex-1 bg-[var(--rule)]" />
                    <span
                        class="text-small leading-none text-ink-2"
                    >{{ dayWithWeekday(entry.at, '') }}</span>
                    <span class="h-px flex-1 bg-[var(--rule)]" />
                </div>

                <p
                    v-if="entry.system"
                    class="px-4 py-0.5 text-center text-small leading-[1.45] text-ink-2"
                >
                    {{ entry.body
                    }}<span v-if="entry.author && !($page.props.soloStudio && entry.author === me)"> — {{ entry.author }}</span>
                </p>

                <div v-else-if="editing !== null && editing === entry.note">
                    <div
                        class="flex items-end gap-2 rounded-[16px] bg-sunk-2 py-1.5 pr-1.5 pl-3.5 shadow-[inset_0_0_0_2px_var(--accent)]"
                    >
                        <Textarea
                            v-model="edit.body"
                            rows="1"
                            autofocus
                            class="field-sizing-content max-h-40 min-h-0 resize-none rounded-none border-0 bg-transparent p-0 py-[5px] text-ui leading-[1.45] tracking-[-0.01em] hover:border-transparent focus:border-transparent"
                            @keydown.enter.exact.prevent="save"
                            @keydown.esc="editing = null"
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            size="icon"
                            :aria-label="$t('common.cancel')"
                            @click="editing = null"
                        >
                            <X />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            :aria-label="$t('common.save')"
                            :disabled="!edit.body.trim() || edit.processing"
                            @click="save"
                        >
                            <Check />
                        </Button>
                    </div>
                    <p
                        v-if="edit.errors.body"
                        class="mt-1.5 text-small leading-[1.45] text-destructive"
                    >
                        {{ edit.errors.body }}
                    </p>
                </div>

                <div v-else-if="compact" class="py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none">
                    <p class="m-0 max-w-[68ch] whitespace-pre-line">{{ entry.body }}</p>
                    <span class="flex flex-wrap items-center gap-x-3 text-small text-ink-2">
                        {{ [entry.at ? dayWithWeekday(entry.at, '') : null, entry.author && !($page.props.soloStudio && entry.author === me) ? entry.author : null].filter(Boolean).join(" · ") }}
                        <template v-if="entry.note">
                            <button
                                type="button"
                                class="min-h-10 cursor-pointer border-0 bg-transparent p-0 font-medium text-ink-2 hover:text-foreground"
                                @click="startEdit(entry)"
                            >{{ $t('project.noteEdit') }}</button>
                            <button
                                type="button"
                                class="min-h-10 cursor-pointer border-0 bg-transparent p-0 font-medium text-ink-2 hover:text-foreground"
                                @click="deleting = entry.note; confirmingDelete = true"
                            >{{ $t('project.noteDelete') }}</button>
                        </template>
                    </span>
                </div>

                <div
                    v-else
                    class="group flex flex-col gap-1"
                    :class="entry.author === me ? 'items-end' : 'items-start'"
                >
                    <div
                        class="max-w-[78%] px-3 py-2"
                        :class="
                            entry.author === me
                                ? 'rounded-[14px] rounded-br-[4px] bg-action text-action-foreground'
                                : 'rounded-[14px] rounded-bl-[4px] bg-sunk'
                        "
                    >
                        <span
                            v-if="entry.author !== me"
                            class="mb-[3px] block text-small leading-none font-semibold text-ink-2"
                        >{{ entry.author }}</span>
                        <span
                            class="block text-body leading-[1.55] whitespace-pre-wrap"
                        >{{ entry.body }}</span>
                        <span
                            class="mt-1 block text-small leading-none tabular-nums"
                            :class="entry.author === me ? 'text-white/65' : 'text-ink-2'"
                        >{{ timeOf(entry.at) }}</span>
                    </div>
                    <div
                        v-if="entry.note"
                        class="flex gap-3 px-1 opacity-0 transition-opacity duration-[120ms] group-hover:opacity-100 focus-within:opacity-100"
                    >
                        <button
                            type="button"
                            class="cursor-pointer border-0 bg-transparent p-0 text-small leading-none font-medium text-ink-2 hover:text-foreground"
                            @click="startEdit(entry)"
                        >{{ $t('project.noteEdit') }}</button>
                        <button
                            type="button"
                            class="cursor-pointer border-0 bg-transparent p-0 text-small leading-none font-medium text-ink-2 hover:text-foreground"
                            @click="deleting = entry.note; confirmingDelete = true"
                        >{{ $t('project.noteDelete') }}</button>
                    </div>
                </div>
            </CappedList>
        </div>

        <AlertDialog v-model:open="confirmingDelete">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ $t('project.noteDeleteTitle') }}</AlertDialogTitle>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogAction class="bg-destructive" @click="destroy">{{ $t('common.delete') }}</AlertDialogAction>
                    <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
