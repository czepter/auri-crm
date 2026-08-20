<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import { Button } from "@/ui/button";
import { DatePicker } from "@/ui/date-picker";
import Page from "@/ui/page/Page.vue";

type QueueRow = {
    id: string;
    name: string;
    date: string | null;
    open: boolean;
    waitingDays: number;
};

const props = defineProps<{
    queue: { key: string; rows: QueueRow[] }[];
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
    people: {
        id: string;
        name: string;
        role: string;
        email: string | null;
        phone: string | null;
    }[];
    shoots: App.Crm.Data.ShootData[];
    quote: App.Quotes.Data.QuoteData | null;
    packages: { id: string; name: string; itemCount: number; total: number }[];
    tasks: App.Crm.Data.TaskRowData[];
    notes: App.Crm.Data.InternalNoteData[];
    types: { value: string; label: string }[];
    sources: { value: string; label: string }[];
    presets: { type: string[]; source: string[] };
    week: { date: string; what: string }[];
    conflicts: App.Crm.Data.DateConflictData[];
}>();

const money = new Intl.NumberFormat("de-DE", {
    style: "currency",
    currency: "EUR",
    maximumFractionDigits: 0,
});

function day(iso: string | null) {
    return iso
        ? new Date(iso).toLocaleDateString("de-DE", {
              weekday: "short",
              day: "2-digit",
              month: "2-digit",
          })
        : "—";
}

// ↑↓ walks the queue without leaving the screen. Response time is the lever:
// eight days of silence happen when every step costs a page load.
const flat = computed(() => props.queue.flatMap((group) => group.rows));

function step(by: number) {
    const at = flat.value.findIndex((row) => row.open);
    const next = flat.value[at + by];

    if (next) {
        router.get(
            `/anfragen/${next.id}`,
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
              venue_name: shoot.venue?.split(", ")[0] ?? "",
              venue_city: shoot.venue?.split(", ")[1] ?? "",
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

// Created in the section they belong to. No dialog: a window takes away the
// context one reads while writing — the last three notes, the other dates.
const note = useForm({ body: "" });
const task = useForm({ title: "", audience: "internal", due_on: "" });

function addNote() {
    note.post(`/auftraege/${props.lead.projectId}/notizen`, {
        preserveScroll: true,
        onSuccess: () => note.reset(),
    });
}

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
/* crm.css .queue — the case rail, grouped by working state. */
const QUEUE =
    "w-58 flex-none overflow-y-auto bg-sunk pt-3.5 max-[1080px]:w-[200px]";
const QUEUE_H2 = "m-0 flex items-baseline px-3.5 pb-2 [a+&]:pt-5";
const QUEUE_B = "text-[12.5px] leading-none font-semibold tracking-[-0.012em]";
const QUEUE_N = "ml-auto text-[11px] leading-[1.47] tabular-nums text-ink-2";
const QUEUE_A =
    "block px-3.5 py-[9px] text-inherit no-underline shadow-[inset_0_1px_0_#ECECEE]";
const QUEUE_NM =
    "block text-[12.5px] leading-[1.47] font-semibold tracking-[-0.012em]";
const QUEUE_MT = "mt-px block text-[11.5px] leading-[1.47]";

/* crm.css .detail — the case itself and its section grammar. */
const H1 =
    "m-0 mt-0.5 text-[25px] leading-[1.08] font-semibold tracking-[-0.032em]";
const DETAIL = "min-w-0 flex-1 overflow-y-auto px-[26px] pt-4 pb-5";
const CRUMB =
    "text-[11.5px] leading-[1.47] text-ink-2 [&_kbd]:ml-[3px] [&_kbd]:rounded-[5px] [&_kbd]:bg-sunk-2 [&_kbd]:px-1.5 [&_kbd]:py-0.5 [&_kbd]:text-[11px] [&_kbd]:leading-none [&_kbd]:font-medium";
const TITLE = "mt-[5px] flex items-start gap-3.5";
const ACTS = "ml-auto flex items-center gap-2";
const SECTION = "mt-[22px]";
const HEAD = "mb-[7px] flex items-baseline gap-[9px]";
const H2 = "m-0 text-[15px] leading-[1.2] font-semibold tracking-[-0.02em]";
const N = "text-[11.5px] leading-[1.47] tabular-nums text-ink-2";

/* crm.css .btn / .btn-2 / .link / .ghost. */
const LINK =
    "text-[12.5px] leading-[1.47] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline";
const GHOSTS = "flex gap-2 pt-2.5";
const GHOST =
    "cursor-pointer rounded-[var(--r-pill)] border-0 bg-sunk px-[11px] py-[5px] text-[12px] leading-[1.4] font-normal text-ink-2 hover:bg-sunk-2 [&_b]:font-semibold [&_b]:text-foreground";

/* crm.css .said — the inquiry text beside the contact column. */
const SAID =
    "flex items-start gap-[26px] max-[1080px]:flex-col max-[1080px]:gap-4";
const SAID_P =
    "m-0 min-w-0 max-w-[58ch] flex-1 text-[14px] leading-[1.5] tracking-[-0.008em]";
const WHO =
    "w-[238px] flex-none pr-[18px] pl-[22px] text-[13.5px] leading-[1.35] font-semibold tracking-[-0.015em] shadow-[inset_1px_0_0_var(--rule)] max-[1080px]:w-auto max-[1080px]:pl-0 max-[1080px]:shadow-none";
const PERSON = "[&+&]:mt-[13px]";
const NM = "text-[13px] leading-[1.35] font-semibold tracking-[-0.012em]";
const RO =
    "mt-0.5 text-[10.5px] leading-[1.35] tracking-[0.05em] uppercase text-ink-2";
const V = "mt-1 text-[13.5px] leading-[1.35] tracking-[-0.01em]";
const VIA_B =
    "[&>b]:block [&>b]:text-[13px] [&>b]:leading-[1.35] [&>b]:font-semibold [&>b]:tracking-[-0.012em] [&>b]:text-foreground";
const VIA = `mt-[15px] pt-[11px] text-[12.5px] leading-[1.35] text-ink-2 shadow-[inset_0_1px_0_var(--rule)] ${VIA_B}`;
const VIA_TOP = `mb-[15px] pb-[11px] text-[12.5px] leading-[1.35] text-ink-2 shadow-[inset_0_-1px_0_var(--rule)] ${VIA_B}`;

/* crm.css .rowset — anything with a time on the left. */
const ROW =
    "flex items-center gap-3.5 py-[11px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none max-[640px]:flex-wrap";
const WHEN =
    "w-[150px] flex-none text-[13px] leading-[1.47] font-semibold tracking-[-0.012em] tabular-nums";
const ST = "block text-[11.5px] leading-[1.47] font-normal text-ink-2";
const WHAT = "min-w-0 flex-1 text-[13.5px] leading-[1.47]";
const WHAT_S = "text-[12px] leading-[1.47] text-ink-2";
const RIGHT =
    "w-[118px] flex-none text-right text-[12px] leading-[1.47] text-ink-2";

/* crm.css .quote — the line items, not only the total. */
const QR =
    "flex items-baseline gap-3.5 py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none";
const TI = "min-w-0 flex-1 text-[13.5px] leading-[1.47]";
const QTY =
    "w-14 flex-none text-right text-[12.5px] leading-[1.47] tabular-nums text-ink-2";
const AMT = "w-28 flex-none text-right text-[13px] leading-[1.47] tabular-nums";
const SUM = "flex items-baseline gap-3.5 pt-2.5 shadow-[inset_0_1px_0_#D8D8DC]";
const SUM_TI = "min-w-0 flex-1 text-[13px] leading-[1.47] font-semibold";
const SUM_AMT =
    "w-28 flex-none text-right text-[15px] leading-[1.47] font-semibold tracking-[-0.018em] tabular-nums";
const QUOTE_META =
    "mt-[7px] flex flex-col gap-[3px] text-[12px] leading-[1.47] text-ink-2";

/* crm.css .todo and .log. */
const TASK =
    "flex items-center gap-3 py-[7px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none";
const BOX = "size-[15px] flex-none rounded-[5px]";
const TASK_T = "text-[13.5px] leading-[1.47]";
const AUD =
    "text-[10.5px] leading-[1.47] tracking-[0.05em] uppercase text-ink-2";
const DUE = "ml-auto text-[12px] leading-[1.47] tabular-nums";
const NOTE =
    "flex gap-3.5 py-[7px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none";
const NOTE_T =
    "w-[150px] flex-none text-[12.5px] leading-[1.47] tabular-nums text-ink-2";
const NOTE_C = "min-w-0 flex-1 text-[13px] leading-[1.47]";

/* crm.css .rail — what lies around the requested day. */
const RAIL =
    "w-70 flex-none overflow-y-auto bg-sunk px-5 py-4 max-[1080px]:hidden";
const RAIL_H2 =
    "m-0 mb-[9px] text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2";
const DAY =
    "flex gap-2.5 py-1.5 text-[12.5px] leading-[1.47] shadow-[inset_0_1px_0_#ECECEE] first:shadow-none";
const DAY_MARK =
    "flex gap-2.5 -mx-2 rounded-lg bg-paper px-2 py-1.5 text-[12.5px] leading-[1.47]";
const DD = "w-[62px] flex-none font-semibold tabular-nums";

/* crm.css .form / .composer / .field / .choice — creating in place. */
const INPUTS =
    "[&_:is(input,textarea,select)]:w-full [&_:is(input,textarea,select)]:rounded-[10px] [&_:is(input,textarea,select)]:border-0 [&_:is(input,textarea,select)]:bg-sunk [&_:is(input,textarea,select)]:px-[11px] [&_:is(input,textarea,select)]:py-[7px] [&_:is(input,textarea,select)]:text-[13.5px] [&_:is(input,textarea,select)]:leading-[1.4] [&_:is(input,textarea,select)]:font-normal [&_:is(input,textarea,select)]:tracking-[-0.01em] [&_:is(input,textarea,select)]:text-foreground [&_:is(input,textarea,select)]:outline-0 [&_:is(input,textarea)::placeholder]:text-[#B0B0B5] [&_:is(input,textarea,select):focus]:bg-sunk-2 [&_:is(input,textarea,select):focus]:shadow-[inset_0_0_0_2px_var(--accent)] [&_textarea]:min-h-[88px] [&_textarea]:resize-y";
const FORM = `max-w-[820px] ${INPUTS}`;
const COMPOSER = `pt-1 pb-0.5 ${INPUTS}`;
const LINE = "flex gap-3 [&+&]:mt-3";
const LINE_END = "flex flex-nowrap items-end gap-3 [&+&]:mt-3";
const FIELD =
    "flex flex-col gap-1 [&>span]:pl-0.5 [&>span]:text-[10.5px] [&>span]:leading-none [&>span]:font-normal [&>span]:tracking-[0.05em] [&>span]:uppercase [&>span]:text-ink-2";
const FIELD_GROW = `${FIELD} min-w-[148px] flex-1`;
const FOOT = "mt-2.5 flex items-center gap-3";
const SHOOTBLOCK = "pt-3.5 pb-4 shadow-[inset_0_1px_0_var(--rule)]";
const DATEPICKER = "w-[148px] [&_input]:pr-8";
const CHOICE =
    "flex flex-wrap gap-1.5 [&_label]:relative [&_input]:pointer-events-none [&_input]:absolute [&_input]:opacity-0 [&_span]:inline-block [&_span]:cursor-pointer [&_span]:rounded-[var(--r-pill)] [&_span]:bg-sunk [&_span]:px-3 [&_span]:py-1.5 [&_span]:text-[12.5px] [&_span]:text-foreground [&_input:checked+span]:bg-foreground [&_input:checked+span]:text-paper";
</script>

<template>
    <AdminLayout active="anfragen">
        <Page>
            <template #left>
                <nav :class="QUEUE">
                    <template v-for="group in queue" :key="group.key">
                        <h2 v-if="group.rows.length" :class="QUEUE_H2">
                            <b :class="QUEUE_B">{{
                                $t(`lead.group.${group.key}`)
                            }}</b>
                            <span :class="QUEUE_N">{{
                                group.rows.length
                            }}</span>
                        </h2>

                        <Link
                            v-for="row in group.rows"
                            :key="row.id"
                            :class="[QUEUE_A, row.open && 'bg-paper']"
                            :href="`/anfragen/${row.id}`"
                        >
                            <span :class="QUEUE_NM">{{ row.name }}</span>
                            <span
                                :class="[
                                    QUEUE_MT,
                                    row.waitingDays >= 3
                                        ? 'text-action'
                                        : 'text-ink-2',
                                ]"
                            >
                                {{
                                    row.date
                                        ? day(row.date)
                                        : $t("board.openDate")
                                }}
                                <template v-if="row.waitingDays >= 3">
                                    ·
                                    {{
                                        $t("lead.silentFor", row.waitingDays)
                                    }}</template
                                >
                            </span>
                        </Link>
                    </template>
                </nav>
            </template>

            <template #right>
                <aside :class="RAIL">
                    <h2 :class="RAIL_H2">{{ $t("lead.week") }}</h2>
                    <div v-if="week.length">
                        <div
                            v-for="entry in week"
                            :key="`${entry.date}${entry.what}`"
                            :class="DAY"
                        >
                            <span :class="DD">{{ day(entry.date) }}</span>
                            <span class="text-ink-2">{{ entry.what }}</span>
                        </div>
                    </div>
                    <div v-else :class="DAY_MARK">
                        <span class="font-medium text-[#3F6B4A]">{{
                            $t("lead.weekFree")
                        }}</span>
                    </div>

                    <template v-if="conflicts.length">
                        <h2 :class="RAIL_H2">{{ $t("lead.conflicts") }}</h2>
                        <div
                            v-for="(conflict, index) in conflicts"
                            :key="index"
                            :class="DAY_MARK"
                        >
                            <span class="font-medium text-action">{{
                                conflict.label ?? conflict.projectName
                            }}</span>
                        </div>
                    </template>
                </aside>
            </template>

            <!-- Grouped by working state, not by the six funnel stages: six stages
           are the view of the funnel, three groups the view of the day. -->

            <main :class="DETAIL">
                <!-- Opened from the palette, the phase is the one word that is
             missing, so the breadcrumb carries it. -->
                <div :class="CRUMB">
                    <Link href="/anfragen">{{ $t("board.title") }}</Link> ·
                    {{ lead.stageLabel }} <kbd>↑</kbd><kbd>↓</kbd>
                </div>

                <!-- The head carries identity. No phase line: it stood in full twenty
             pixels further down anyway. -->
                <div :class="[TITLE, 'sticky top-0 z-[5] bg-paper']">
                    <div>
                        <h1 :class="H1">{{ lead.name }}</h1>
                    </div>

                    <div v-if="editing" :class="ACTS">
                        <a
                            :class="LINK"
                            href="#"
                            @click.prevent="editing = false"
                            >{{ $t("common.cancel") }}</a
                        >
                        <Button
                            variant="pill"
                            type="button"
                            :disabled="edit.processing"
                            @click="save"
                        >
                            {{ $t("common.save") }}
                        </Button>
                    </div>

                    <div v-else :class="ACTS">
                        <a
                            :class="LINK"
                            href="#"
                            @click.prevent="editing = true"
                            >{{ $t("lead.edit") }}</a
                        >
                        <Button :as="Link" variant="pill" :href="due.href">{{
                            $t(due.label)
                        }}</Button>
                    </div>
                </div>

                <section :class="SECTION">
                    <header :class="HEAD">
                        <h2 :class="H2">{{ $t("lead.said") }}</h2>
                    </header>
                    <div :class="SAID">
                        <div
                            v-if="editing"
                            :class="[FORM, 'min-w-0 max-w-[58ch] flex-1']"
                        >
                            <textarea v-model="edit.inquiry_text" rows="5" />
                        </div>
                        <p v-else-if="lead.inquiryText" :class="SAID_P">
                            {{ lead.inquiryText }}
                        </p>
                        <p v-else :class="SAID_P">
                            {{ $t("lead.noText") }}
                        </p>

                        <div :class="WHO">
                            <!-- Occasion and origin at the top of the same column, not as a
                   subline on the title: they describe the case, not the
                   screen. -->
                            <div v-if="editing" :class="[VIA_TOP, INPUTS]">
                                <div :class="[CHOICE, 'mb-2']">
                                    <label
                                        v-for="option in types"
                                        :key="option.value"
                                    >
                                        <input
                                            v-model="edit.type"
                                            type="radio"
                                            :value="option.value"
                                        />
                                        <span>{{ option.label }}</span>
                                    </label>
                                </div>
                                <input
                                    v-if="edit.type === 'other'"
                                    v-model="edit.type_other"
                                    type="text"
                                    list="type-presets"
                                    class="mb-2"
                                />
                                <datalist id="type-presets">
                                    <option
                                        v-for="value in presets.type"
                                        :key="value"
                                        :value="value"
                                    />
                                </datalist>
                                <div :class="CHOICE">
                                    <label
                                        v-for="option in sources"
                                        :key="option.value"
                                    >
                                        <input
                                            v-model="edit.source"
                                            type="radio"
                                            :value="option.value"
                                        />
                                        <span>{{ option.label }}</span>
                                    </label>
                                </div>
                                <input
                                    v-if="edit.source === 'other'"
                                    v-model="edit.source_other"
                                    type="text"
                                    list="source-presets"
                                    class="mt-2"
                                />
                                <datalist id="source-presets">
                                    <option
                                        v-for="value in presets.source"
                                        :key="value"
                                        :value="value"
                                    />
                                </datalist>
                            </div>
                            <div v-else :class="VIA_TOP">
                                <b>{{ lead.typeLabel }}</b
                                >{{ lead.sourceLabel }}
                            </div>

                            <div v-if="editing" :class="FORM">
                                <div
                                    v-for="person in edit.people"
                                    :key="person.id"
                                    :class="PERSON"
                                >
                                    <input
                                        v-model="person.name"
                                        type="text"
                                        class="mb-1.5"
                                    />
                                    <input
                                        v-model="person.email"
                                        type="email"
                                        inputmode="email"
                                        class="mb-1.5"
                                    />
                                    <input
                                        v-model="person.phone"
                                        type="tel"
                                        inputmode="tel"
                                    />
                                </div>
                            </div>

                            <div
                                v-for="person in editing ? [] : people"
                                :key="person.id"
                                :class="PERSON"
                            >
                                <div :class="NM">{{ person.name }}</div>
                                <div :class="RO">
                                    {{ $t(`contacts.role.${person.role}`) }}
                                </div>
                                <div v-if="person.email" :class="V">
                                    <a
                                        :class="LINK"
                                        :href="`mailto:${person.email}`"
                                        >{{ person.email }}</a
                                    >
                                </div>
                                <div v-if="person.phone" :class="V">
                                    <a
                                        :class="LINK"
                                        :href="`tel:${person.phone}`"
                                        >{{ person.phone }}</a
                                    >
                                </div>
                                <div
                                    v-if="!person.email && !person.phone"
                                    :class="[V, 'text-mark']"
                                >
                                    {{ $t("lead.noReach") }}
                                </div>
                            </div>

                            <div :class="VIA">
                                <b>{{
                                    lead.capturedVia ?? lead.sourceLabel
                                }}</b>
                                {{
                                    new Date(lead.capturedAt).toLocaleString(
                                        "de-DE",
                                        {
                                            day: "2-digit",
                                            month: "2-digit",
                                            hour: "2-digit",
                                            minute: "2-digit",
                                        },
                                    )
                                }}
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="shoots.length" :class="SECTION">
                    <header :class="HEAD">
                        <h2 :class="H2">{{ $t("lead.dates") }}</h2>
                        <span :class="N">{{ shoots.length }}</span>
                    </header>
                    <!-- From booked the date and the place are contract content: they
               leave the edit state rather than standing there locked with a
               reason, and the read view keeps them. -->
                    <div v-if="editing && !lead.booked" :class="FORM">
                        <div
                            v-for="shoot in edit.shoots"
                            :key="shoot.id"
                            :class="SHOOTBLOCK"
                        >
                            <div :class="LINE">
                                <label :class="FIELD"
                                    ><span>{{ $t("project.shootDate") }}</span
                                    ><DatePicker
                                        v-model="shoot.date"
                                        :class="DATEPICKER"
                                /></label>
                                <label :class="FIELD"
                                    ><span>{{ $t("project.callTime") }}</span
                                    ><input
                                        v-model="shoot.call_time"
                                        type="time"
                                /></label>
                                <label :class="FIELD"
                                    ><span>{{ $t("project.endTime") }}</span
                                    ><input
                                        v-model="shoot.end_time"
                                        type="time"
                                /></label>
                            </div>
                            <div :class="LINE">
                                <label :class="FIELD_GROW"
                                    ><span>{{ $t("project.venueName") }}</span
                                    ><input
                                        v-model="shoot.venue_name"
                                        type="text"
                                /></label>
                                <label :class="FIELD_GROW"
                                    ><span>{{ $t("project.venueCity") }}</span
                                    ><input
                                        v-model="shoot.venue_city"
                                        type="text"
                                /></label>
                            </div>
                        </div>
                    </div>

                    <div v-else>
                        <div
                            v-for="shoot in shoots"
                            :key="shoot.id"
                            :class="ROW"
                        >
                            <span :class="WHEN">
                                {{ day(shoot.date) }}
                                <span :class="ST">{{ shoot.statusLabel }}</span>
                            </span>
                            <span :class="WHAT">
                                {{ shoot.label ?? shoot.kind }}
                                <span v-if="shoot.venue" :class="WHAT_S">
                                    · {{ shoot.venue }}</span
                                >
                            </span>
                            <span :class="RIGHT">{{
                                shoot.crew
                                    .map((member) => member.name)
                                    .join(", ") || "—"
                            }}</span>
                        </div>
                    </div>
                </section>

                <section :class="SECTION">
                    <header :class="HEAD">
                        <h2 :class="H2">{{ $t("lead.quote") }}</h2>
                    </header>

                    <div v-if="quote">
                        <div
                            v-for="item in quote.items"
                            :key="item.id"
                            :class="QR"
                        >
                            <span :class="TI">{{ item.title }}</span>
                            <span :class="QTY">{{ item.quantity }}×</span>
                            <span :class="AMT">{{
                                item.unitPrice === null
                                    ? "—"
                                    : money.format(item.unitPrice / 100)
                            }}</span>
                        </div>
                        <div :class="SUM">
                            <span :class="SUM_TI">{{ quote.statusLabel }}</span>
                            <span :class="SUM_AMT">{{
                                money.format(quote.total / 100)
                            }}</span>
                        </div>
                        <p v-if="quote.validUntil" :class="QUOTE_META">
                            {{
                                $t("lead.validUntil", {
                                    date: day(quote.validUntil),
                                })
                            }}
                        </p>
                    </div>

                    <!-- No quote yet: the active packages, because the next move is
               choosing one. An empty section would state the same thing and
               offer nothing. -->
                    <div v-else :class="GHOSTS">
                        <Link
                            v-for="pkg in packages"
                            :key="pkg.id"
                            :class="GHOST"
                            :href="`/auftraege/${lead.projectId}/angebote`"
                        >
                            <b>{{ pkg.name }}</b>
                            {{ $t("packages.itemCount", pkg.itemCount) }} ·
                            {{ money.format(pkg.total / 100) }}
                        </Link>
                    </div>
                </section>

                <section :class="SECTION">
                    <header :class="HEAD">
                        <h2 :class="H2">{{ $t("lead.tasks") }}</h2>
                        <span :class="N">{{ tasks.length }}</span>
                    </header>

                    <!-- One row: title, who it is for, when it is due. -->
                    <form :class="COMPOSER" @submit.prevent="addTask">
                        <div :class="LINE_END">
                            <label :class="FIELD_GROW">
                                <span>{{ $t("lead.taskTitle") }}</span>
                                <input v-model="task.title" type="text" />
                            </label>
                            <label :class="FIELD">
                                <span>{{ $t("lead.taskAudience") }}</span>
                                <select v-model="task.audience">
                                    <option value="internal">
                                        {{ $t("lead.audienceInternal") }}
                                    </option>
                                    <option value="client">
                                        {{ $t("lead.audienceClient") }}
                                    </option>
                                </select>
                            </label>
                            <label :class="FIELD">
                                <span>{{ $t("lead.taskDue") }}</span>
                                <DatePicker
                                    v-model="task.due_on"
                                    :class="DATEPICKER"
                                />
                            </label>
                        </div>
                        <div :class="FOOT">
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
                        <div v-for="task in tasks" :key="task.id" :class="TASK">
                            <span
                                :class="[
                                    BOX,
                                    task.isDone ? 'bg-foreground' : 'bg-sunk-2',
                                ]"
                            />
                            <span
                                :class="[TASK_T, task.isDone && 'text-ink-2']"
                                >{{ task.title }}</span
                            >
                            <span :class="AUD">{{ task.audience }}</span>
                            <span
                                :class="[
                                    DUE,
                                    task.isOverdue
                                        ? 'text-action'
                                        : 'text-ink-2',
                                ]"
                                >{{ task.dueOn ? day(task.dueOn) : "" }}</span
                            >
                        </div>
                    </div>
                </section>

                <section :class="SECTION">
                    <header :class="HEAD">
                        <h2 :class="H2">{{ $t("lead.notes") }}</h2>
                    </header>

                    <!-- Above the log, so the last notes stay visible: that context is
               exactly what a window would take away. -->
                    <form :class="COMPOSER" @submit.prevent="addNote">
                        <textarea
                            v-model="note.body"
                            rows="2"
                            :placeholder="$t('lead.notePlaceholder')"
                        />
                        <div :class="FOOT">
                            <Button
                                variant="pill-muted"
                                type="submit"
                                :disabled="!note.body || note.processing"
                            >
                                {{ $t("lead.addNote") }}
                            </Button>
                        </div>
                    </form>
                    <div>
                        <div v-for="note in notes" :key="note.id" :class="NOTE">
                            <span :class="NOTE_T">{{
                                day(note.writtenAt)
                            }}</span>
                            <span :class="NOTE_C"
                                >{{ note.body }}
                                <span class="text-ink-2"
                                    >— {{ note.author }}</span
                                ></span
                            >
                        </div>
                    </div>
                </section>
            </main>

            <!-- The trace: what lies around the requested day. A date is answered
           by what is already on it. -->
        </Page>
    </AdminLayout>
</template>
