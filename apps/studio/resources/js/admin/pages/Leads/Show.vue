<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import CaseSection from "@/admin/components/CaseSection.vue";
import LeadQueue from "@/admin/components/LeadQueue.vue";
import NoteThread from "@/admin/components/NoteThread.vue";
import ShootFields from "@/admin/components/ShootFields.vue";
import ShootRow from "@/admin/components/ShootRow.vue";
import { Button } from "@/ui/button";
import { ChoiceGroup } from "@/ui/choice-group";
import { DatePicker } from "@/ui/date-picker";
import { Field } from "@/ui/field";
import { Input } from "@/ui/input";
import { NativeSelect } from "@/ui/native-select";
import Page from "@/ui/page/Page.vue";
import Rail from "@/ui/page/Rail.vue";
import RailSection from "@/ui/page/RailSection.vue";
import { Textarea } from "@/ui/textarea";
import { formatEurosRounded } from "@/admin/lib/money";
import { dayWithWeekday as day } from "@/admin/lib/dates";

const props = defineProps<{
    queue: { key: string; rows: App.Crm.Data.LeadQueueRowData[] }[];
    lead: {
        id: string;
        projectId: string;
        name: string;
        type: string;
        typeOther: string | null;
        typeLabel: string;
        source: string;
        sourceOther: string | null;
        sourceLabel: string;
        stage: string;
        stageLabel: string;
        inquiryText: string | null;
        capturedVia: string | null;
        capturedAt: string;
        booked: boolean;
    };
    people: App.Crm.Data.ProjectPersonData[];
    shoots: App.Crm.Data.ShootData[];
    quote: App.Quotes.Data.QuoteData | null;
    packages: { id: string; name: string; itemCount: number; total: number }[];
    tasks: App.Crm.Data.TaskRowData[];
    thread: App.Crm.Data.ThreadEntryData[];
    types: { value: string; label: string }[];
    sources: { value: string; label: string }[];
    presets: { type: string[]; source: string[] };
    week: { date: string; what: string; projectId: string }[];
    conflicts: App.Crm.Data.DateConflictData[];
}>();

// ↑↓ walks the queue without leaving the screen. Response time is the lever:
// eight days of silence happen when every step costs a page load. The pane
// hands over what its filter left standing, so the arrows reach what is on
// screen and nothing else.
const reachable = ref<App.Crm.Data.LeadQueueRowData[]>([]);

function step(by: number) {
    const at = reachable.value.findIndex((row) => row.open);
    const next = reachable.value[at + by];

    if (next) {
        router.get(
            // The pane keeps q and wartet in the URL; carrying them means a
            // step does not land on an unfiltered pane.
            `/anfragen/${next.id}${window.location.search}`,
            {},
            { preserveScroll: true, preserveState: true },
        );
    }
}

function onKey(event: KeyboardEvent) {
    if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
        return;
    }

    // Not every keydown target is an element — one dispatched on the window
    // has no matches() — and an arrow key inside a field belongs to the field.
    const target = event.target as Element | null;

    if (
        typeof target?.matches === "function" &&
        target.matches("input, textarea, select")
    ) {
        return;
    }

    event.preventDefault();
    step(event.key === "ArrowDown" ? 1 : -1);
}

onMounted(() => addEventListener("keydown", onKey));
onUnmounted(() => removeEventListener("keydown", onKey));

// C-10 (#245): editing is a state of this view — no second screen, no route
// of its own. Nothing jumps and nothing moves: the same fields become
// operable where they already stand.
//
// One save for the whole state rather than one per section, and the head is
// sticky so it does not scroll away on a long case — which is the objection
// the draft's single button invites.
const editing = ref(false);

const edit = useForm({
    type: props.lead.type,
    type_other: props.lead.typeOther ?? "",
    source: props.lead.source,
    source_other: props.lead.sourceOther ?? "",
    inquiry_text: props.lead.inquiryText ?? "",
    people: props.people.map((person) => ({
        id: person.id,
        name: person.name,
        email: person.email ?? "",
        phone: person.phone ?? "",
    })),
    // From booked, date and place are contract content and do not travel at
    // all — see the controller.
    shoots: props.lead.booked
        ? []
        : props.shoots.map((shoot) => ({
              id: shoot.id,
              date: shoot.date ?? "",
              venue_name: shoot.venueName ?? "",
              venue_city: shoot.venueCity ?? "",
              call_time: shoot.callTime ?? "",
              end_time: shoot.endTime ?? "",
          })),
});

function save() {
    edit.transform((data) => ({
        ...data,
        inquiry_text: data.inquiry_text === "" ? null : data.inquiry_text,
        people: data.people.map((person) => ({
            ...person,
            email: person.email === "" ? null : person.email,
            phone: person.phone === "" ? null : person.phone,
        })),
        shoots: data.shoots.map((shoot) => ({
            ...shoot,
            date: shoot.date === "" ? null : shoot.date,
            call_time: shoot.call_time === "" ? null : shoot.call_time,
            end_time: shoot.end_time === "" ? null : shoot.end_time,
        })),
    })).patch(`/anfragen/${props.lead.id}`, {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}

const task = useForm({ title: "", audience: "internal", due_on: "" });

function addTask() {
    task.post(`/auftraege/${props.lead.projectId}/aufgaben`, {
        preserveScroll: true,
        onSuccess: () => task.reset(),
    });
}

// Exactly one filled button, and it moves with the action that is due.
const due = computed(() => {
    if (props.quote === null) {
        return {
            label: "lead.writeQuote",
            href: `/auftraege/${props.lead.projectId}/angebote`,
        };
    }

    return props.lead.stage === "quote_accepted"
        ? {
              label: "lead.sendContract",
              href: `/auftraege/${props.lead.projectId}/vertrag`,
          }
        : {
              label: "lead.openProject",
              href: `/auftraege/${props.lead.projectId}`,
          };
});
</script>

<template>
    <AdminLayout active="anfragen">
        <Page>
            <!-- Grouped by working state, not by the six funnel stages: six stages
                 are the view of the funnel, three groups the view of the day. -->
            <template #left>
                <LeadQueue :groups="queue" @update:rows="reachable = $event" />
            </template>

            <!-- The trace: what lies around the requested day. A date is answered
                 by what is already on it. -->
            <template #right>
                <Rail data-tokens>
                    <RailSection :title="$t('lead.week')">
                        <template v-if="week.length">
                            <Link
                                v-for="entry in week"
                                :key="`${entry.date}${entry.what}`"
                                :href="`/auftraege/${entry.projectId}`"
                                class="group -mx-2 flex gap-2.5 rounded-lg px-2 py-1.5 text-[12.5px] leading-[1.47] shadow-[inset_0_1px_0_#ECECEE] transition-colors first:shadow-none hover:bg-paper"
                            >
                                <span class="w-[62px] flex-none font-semibold tabular-nums">{{ day(entry.date) }}</span>
                                <span class="text-ink-2 transition-colors group-hover:text-foreground">{{
                                    entry.what
                                }}</span>
                            </Link>
                        </template>
                        <div
                            v-else
                            class="-mx-2 flex gap-2.5 rounded-lg bg-paper px-2 py-1.5 text-[12.5px] leading-[1.47]"
                        >
                            <span class="font-medium text-[#3F6B4A]">{{ $t("lead.weekFree") }}</span>
                        </div>
                    </RailSection>

                    <RailSection v-if="conflicts.length" :title="$t('lead.conflicts')">
                        <div
                            v-for="(conflict, index) in conflicts"
                            :key="index"
                            class="-mx-2 flex gap-2.5 rounded-lg bg-paper px-2 py-1.5 text-[12.5px] leading-[1.47]"
                        >
                            <span class="font-medium text-action">{{
                                conflict.label ?? conflict.projectName
                            }}</span>
                        </div>
                    </RailSection>
                </Rail>
            </template>

            <main data-tokens class="min-w-0 flex-1 overflow-y-auto px-[26px] pt-4 pb-5">
                <!-- Opened from the palette, the phase is the one word that is
                     missing, so the breadcrumb carries it. -->
                <div
                    class="text-[11.5px] leading-[1.47] text-ink-2 [&_kbd]:ml-[3px] [&_kbd]:rounded-[5px] [&_kbd]:bg-sunk-2 [&_kbd]:px-1.5 [&_kbd]:py-0.5 [&_kbd]:text-[11px] [&_kbd]:leading-none [&_kbd]:font-medium"
                >
                    <Link href="/anfragen">{{ $t("board.title") }}</Link> ·
                    {{ lead.stageLabel }} <kbd>↑</kbd><kbd>↓</kbd>
                </div>

                <!-- The head carries identity. No phase line: it stood in full twenty
                     pixels further down anyway. -->
                <div class="sticky top-0 z-[5] mt-[5px] flex items-start gap-3.5 bg-paper">
                    <div>
                        <h1
                            class="m-0 mt-0.5 text-[25px] leading-[1.08] font-semibold tracking-[-0.032em]"
                        >
                            {{ lead.name }}
                        </h1>
                    </div>

                    <div v-if="editing" class="ml-auto flex items-center gap-2">
                        <a
                            class="text-[12.5px] leading-[1.47] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
                            href="#"
                            @click.prevent="editing = false"
                        >{{ $t("common.cancel") }}</a>
                        <Button
                            variant="pill"
                            type="button"
                            :disabled="edit.processing"
                            @click="save"
                        >
                            {{ $t("common.save") }}
                        </Button>
                    </div>

                    <div v-else class="ml-auto flex items-center gap-2">
                        <a
                            class="text-[12.5px] leading-[1.47] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
                            href="#"
                            @click.prevent="editing = true"
                        >{{ $t("lead.edit") }}</a>
                        <Button :as="Link" variant="pill" :href="due.href">{{
                            $t(due.label)
                        }}</Button>
                    </div>
                </div>

                <CaseSection :title="$t('lead.said')">
                    <!-- The inquiry text beside the contact details: the phone number
                         is the reason the screen is open, so it does not stand small
                         under the quote. -->
                    <div class="flex items-start gap-[26px] max-[1080px]:flex-col max-[1080px]:gap-4">
                        <div v-if="editing" class="min-w-0 max-w-[58ch] flex-1">
                            <Textarea v-model="edit.inquiry_text" variant="filled" rows="5" />
                        </div>
                        <p
                            v-else
                            class="m-0 min-w-0 max-w-[58ch] flex-1 text-[14px] leading-[1.5] tracking-[-0.008em]"
                        >
                            {{ lead.inquiryText ?? $t("lead.noText") }}
                        </p>

                        <div
                            class="w-[238px] flex-none pr-[18px] pl-[22px] text-[13.5px] leading-[1.35] font-semibold tracking-[-0.015em] shadow-[inset_1px_0_0_var(--rule)] max-[1080px]:w-auto max-[1080px]:pl-0 max-[1080px]:shadow-none"
                        >
                            <!-- Occasion and origin at the top of the same column, not as
                                 a subline on the title: they describe the case, not the
                                 screen. -->
                            <div
                                v-if="editing"
                                class="mb-[15px] pb-[11px] text-[12.5px] leading-[1.35] text-ink-2 shadow-[inset_0_-1px_0_var(--rule)]"
                            >
                                <ChoiceGroup v-model="edit.type" class="mb-2" :options="types" />
                                <Input
                                    v-if="edit.type === 'other'"
                                    v-model="edit.type_other"
                                    variant="filled"
                                    class="mb-2"
                                    type="text"
                                    list="type-presets"
                                />
                                <datalist id="type-presets">
                                    <option v-for="value in presets.type" :key="value" :value="value" />
                                </datalist>

                                <ChoiceGroup v-model="edit.source" :options="sources" />
                                <Input
                                    v-if="edit.source === 'other'"
                                    v-model="edit.source_other"
                                    variant="filled"
                                    class="mt-2"
                                    type="text"
                                    list="source-presets"
                                />
                                <datalist id="source-presets">
                                    <option v-for="value in presets.source" :key="value" :value="value" />
                                </datalist>
                            </div>
                            <div
                                v-else
                                class="mb-[15px] pb-[11px] text-[12.5px] leading-[1.35] text-ink-2 shadow-[inset_0_-1px_0_var(--rule)] [&>b]:block [&>b]:text-[13px] [&>b]:leading-[1.35] [&>b]:font-semibold [&>b]:tracking-[-0.012em] [&>b]:text-foreground"
                            >
                                <b>{{ lead.typeLabel }}</b>{{ lead.sourceLabel }}
                            </div>

                            <div v-if="editing">
                                <div
                                    v-for="person in edit.people"
                                    :key="person.id"
                                    class="[&+&]:mt-[13px]"
                                >
                                    <Input v-model="person.name" variant="filled" class="mb-1.5" type="text" />
                                    <Input
                                        v-model="person.email"
                                        variant="filled"
                                        class="mb-1.5"
                                        type="email"
                                        inputmode="email"
                                    />
                                    <Input v-model="person.phone" variant="filled" type="tel" inputmode="tel" />
                                </div>
                            </div>

                            <div
                                v-for="person in editing ? [] : people"
                                :key="person.id"
                                class="[&+&]:mt-[13px]"
                            >
                                <Link
                                    class="block text-[13px] leading-[1.35] font-semibold tracking-[-0.012em] text-foreground no-underline underline-offset-[3px] hover:underline"
                                    :href="`/kontakte/${person.id}`"
                                >
                                    {{ person.name }}
                                </Link>
                                <div
                                    class="mt-0.5 text-[10.5px] leading-[1.35] tracking-[0.05em] uppercase text-ink-2"
                                >
                                    {{ $t(`contacts.role.${person.role}`) }}
                                </div>
                                <div v-if="person.email" class="mt-1 text-[13.5px] leading-[1.35] tracking-[-0.01em]">
                                    <a
                                        class="text-[12.5px] leading-[1.47] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
                                        :href="`mailto:${person.email}`"
                                    >{{ person.email }}</a>
                                </div>
                                <div v-if="person.phone" class="mt-1 text-[13.5px] leading-[1.35] tracking-[-0.01em]">
                                    <a
                                        class="text-[12.5px] leading-[1.47] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
                                        :href="`tel:${person.phone}`"
                                    >{{ person.phone }}</a>
                                </div>
                                <div
                                    v-if="!person.email && !person.phone"
                                    class="mt-1 text-[13.5px] leading-[1.35] tracking-[-0.01em] text-mark"
                                >
                                    {{ $t("lead.noReach") }}
                                </div>
                            </div>

                            <div
                                class="mt-[15px] pt-[11px] text-[12.5px] leading-[1.35] text-ink-2 shadow-[inset_0_1px_0_var(--rule)] [&>b]:block [&>b]:text-[13px] [&>b]:leading-[1.35] [&>b]:font-semibold [&>b]:tracking-[-0.012em] [&>b]:text-foreground"
                            >
                                <b>{{ lead.capturedVia ?? lead.sourceLabel }}</b>
                                {{
                                    new Date(lead.capturedAt).toLocaleString("de-DE", {
                                        day: "2-digit",
                                        month: "2-digit",
                                        hour: "2-digit",
                                        minute: "2-digit",
                                    })
                                }}
                            </div>
                        </div>
                    </div>
                </CaseSection>

                <CaseSection v-if="shoots.length" :title="$t('lead.dates')" :count="shoots.length">
                    <!-- From booked the date and the place are contract content: they
                         leave the edit state rather than standing there locked with a
                         reason, and the read view keeps them. -->
                    <div v-if="editing && !lead.booked" class="max-w-[820px]">
                        <ShootFields
                            v-for="shoot in edit.shoots"
                            :key="shoot.id"
                            :shoot="shoot"
                        />
                    </div>

                    <div v-else>
                        <ShootRow v-for="shoot in shoots" :key="shoot.id" :shoot="shoot" />
                    </div>
                </CaseSection>

                <CaseSection :title="$t('lead.quote')">
                    <div v-if="quote">
                        <div
                            v-for="item in quote.items"
                            :key="item.id"
                            class="flex items-baseline gap-3.5 py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
                        >
                            <span class="min-w-0 flex-1 text-[13.5px] leading-[1.47]">{{ item.title }}</span>
                            <span
                                class="w-14 flex-none text-right text-[12.5px] leading-[1.47] tabular-nums text-ink-2"
                            >{{ item.quantity }}×</span>
                            <span class="w-28 flex-none text-right text-[13px] leading-[1.47] tabular-nums">{{
                                item.unitPrice === null ? "—" : formatEurosRounded(item.unitPrice)
                            }}</span>
                        </div>
                        <div class="flex items-baseline gap-3.5 pt-2.5 shadow-[inset_0_1px_0_#D8D8DC]">
                            <span class="min-w-0 flex-1 text-[13px] leading-[1.47] font-semibold">{{
                                quote.statusLabel
                            }}</span>
                            <span
                                class="w-28 flex-none text-right text-[15px] leading-[1.47] font-semibold tracking-[-0.018em] tabular-nums"
                            >{{ formatEurosRounded(quote.total) }}</span>
                        </div>
                        <p
                            v-if="quote.validUntil"
                            class="mt-[7px] flex flex-col gap-[3px] text-[12px] leading-[1.47] text-ink-2"
                        >
                            {{ $t("lead.validUntil", { date: day(quote.validUntil) }) }}
                        </p>
                    </div>

                    <!-- No quote yet: the active packages, because the next move is
                         choosing one. An empty section would state the same thing and
                         offer nothing. -->
                    <div v-else class="flex gap-2 pt-2.5">
                        <Link
                            v-for="pkg in packages"
                            :key="pkg.id"
                            class="cursor-pointer rounded-[var(--r-pill)] border-0 bg-sunk px-[11px] py-[5px] text-[12px] leading-[1.4] font-normal text-ink-2 hover:bg-sunk-2 [&_b]:font-semibold [&_b]:text-foreground"
                            :href="`/auftraege/${lead.projectId}/angebote`"
                        >
                            <b>{{ pkg.name }}</b>
                            {{ $t("packages.itemCount", pkg.itemCount) }} ·
                            {{ formatEurosRounded(pkg.total) }}
                        </Link>
                    </div>
                </CaseSection>

                <CaseSection :title="$t('lead.tasks')" :count="tasks.length">
                    <!-- One row: title, who it is for, when it is due. -->
                    <form class="pt-1 pb-0.5" @submit.prevent="addTask">
                        <div class="flex flex-nowrap items-end gap-3">
                            <Field
                                v-slot="{ id }"
                                dense
                                class="min-w-[148px] flex-1"
                                :label="$t('lead.taskTitle')"
                            >
                                <Input :id="id" v-model="task.title" variant="filled" type="text" />
                            </Field>
                            <Field v-slot="{ id }" dense :label="$t('lead.taskAudience')">
                                <NativeSelect :id="id" v-model="task.audience" variant="filled">
                                    <option value="internal">{{ $t("lead.audienceInternal") }}</option>
                                    <option value="client">{{ $t("lead.audienceClient") }}</option>
                                </NativeSelect>
                            </Field>
                            <Field v-slot="{ id }" dense :label="$t('lead.taskDue')">
                                <DatePicker :id="id" v-model="task.due_on" class="w-[148px] [&_input]:pr-8" />
                            </Field>
                        </div>
                        <div class="mt-2.5 flex items-center gap-3">
                            <Button
                                variant="pill-muted"
                                type="submit"
                                :disabled="!task.title || task.processing"
                            >
                                {{ $t("lead.addTask") }}
                            </Button>
                        </div>
                    </form>
                    <div>
                        <div
                            v-for="task in tasks"
                            :key="task.id"
                            class="flex items-center gap-3 py-[7px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
                        >
                            <span
                                class="size-[15px] flex-none rounded-[5px]"
                                :class="task.isDone ? 'bg-foreground' : 'bg-sunk-2'"
                            />
                            <span
                                class="text-[13.5px] leading-[1.47]"
                                :class="task.isDone && 'text-ink-2'"
                            >{{ task.title }}</span>
                            <span
                                class="text-[10.5px] leading-[1.47] tracking-[0.05em] uppercase text-ink-2"
                            >{{ task.audience }}</span>
                            <span
                                class="ml-auto text-[12px] leading-[1.47] tabular-nums"
                                :class="task.isOverdue ? 'text-action' : 'text-ink-2'"
                            >{{ task.dueOn ? day(task.dueOn) : "" }}</span>
                        </div>
                    </div>
                </CaseSection>

                <CaseSection :title="$t('lead.notes')">
                    <NoteThread
                        :entries="thread"
                        :action="`/auftraege/${lead.projectId}/notizen`"
                    />
                </CaseSection>
            </main>
        </Page>
    </AdminLayout>
</template>
