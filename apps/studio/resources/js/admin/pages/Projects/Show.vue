<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import { Button } from "@/ui/button";
import { DatePicker } from "@/ui/date-picker";
import Page from "@/ui/page/Page.vue";

type Figure = { value: string | null; proof: string | null };

const props = defineProps<{
    header: App.Crm.Data.ProjectHeaderData;
    shoots: App.Crm.Data.ShootData[];
    paymentPlan: App.Payments.Data.PaymentPlanData | null;
    notes: App.Crm.Data.InternalNoteData[];
    tasks: App.Crm.Data.TaskRowData[];
    shootOptions: { value: string; label: string }[];
    activity: {
        event: string;
        label: string;
        at: string;
        actorName: string | null;
    }[];
    figures: Record<string, Figure>;
    scope: {
        items: {
            id: string;
            title: string;
            quantity: number;
            unitPrice: number | null;
            state: string | null;
            wasUnitPrice: number | null;
        }[];
        delta: number;
        hasDraft: boolean;
    };
    people: {
        id: string;
        name: string;
        role: string;
        email: string | null;
        phone: string | null;
    }[];
    galleries: { id: string; title: string | null; expiresAt: string | null }[];
    stages: { value: string; label: string }[];
    sources: { value: string; label: string }[];
    roles: { value: string; label: string }[];
    presets: { source: string[] };
    stage: string | null;
    source: string | null;
    sourceOther: string | null;
    sourceLabel: string | null;
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

function euro(minor: string | number | null) {
    return minor === null ? "—" : money.format(Number(minor) / 100);
}

// C-11 (#247): the same build in every phase. Before the first shoot the
// history is short and the open list long, after delivery the other way
// round, three years later "Offen" holds one row — the follow-up — and the
// trace holds the repeat orders instead of dates. Building a head per phase
// means widening the screen with every phase that turns up.
const open = computed(() => props.tasks.filter((task) => !task.isDone));
const done = computed(() => props.tasks.filter((task) => task.isDone));

// C-12 (#248): the same answer #245 gives for the inquiry — one save for the
// whole state, and the head stays put, so the two screens behave alike.
const editing = ref(false);

const edit = useForm({
    name: props.header.name,
    stage: props.stage,
    source: props.source,
    source_other: props.sourceOther ?? "",
    people: props.people.map((person) => ({
        id: person.id,
        role: person.role,
    })),
    shoots: props.shoots.map((shoot) => ({
        id: shoot.id,
        date: shoot.date ?? "",
        call_time: shoot.callTime ?? "",
        end_time: shoot.endTime ?? "",
        venue_name: shoot.venue?.split(", ")[0] ?? "",
        venue_city: shoot.venue?.split(", ")[1] ?? "",
    })),
});

function save() {
    edit.transform((data) => ({
        ...data,
        shoots: data.shoots.map((shoot) => ({
            ...shoot,
            date: shoot.date === "" ? null : shoot.date,
            call_time: shoot.call_time === "" ? null : shoot.call_time,
            end_time: shoot.end_time === "" ? null : shoot.end_time,
        })),
    })).patch(`/auftraege/${props.header.id}`, {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}

const note = useForm({ body: "" });

function addNote() {
    note.post(`/auftraege/${props.header.id}/notizen`, {
        preserveScroll: true,
        onSuccess: () => note.reset(),
    });
}

function togglePortal() {
    router.post(
        `/auftraege/${props.header.id}/portal-sichtbarkeit`,
        {},
        { preserveScroll: true },
    );
}

function complete(task: App.Crm.Data.TaskRowData) {
    router.post(`/aufgaben/${task.id}/erledigt`, {}, { preserveScroll: true });
}

/* crm.css .work — the wide column and the head that stays on it. */
const WORK = "min-w-0 flex-1 px-[26px] pt-5 pb-7";
const H1 =
    "m-0 mt-0.5 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em] max-[640px]:text-[25px]";
const SEC = "mb-5";
/* Der Kopf steht wie im Klickdummy: Kicker, Name, Aktionen rechts daneben
   (mockups/redesign/crm-projekt.html, .hrow). */
const KICK = "text-[11px] tracking-[0.05em] uppercase text-ink-2";
const TITLE = "sticky top-0 z-[5] flex items-center gap-3 bg-paper";
const ACTS = "ml-auto flex items-center gap-2";

/* crm.css .btn / .btn-2 / .link. */
const LINK =
    "text-[12.5px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline";

/* crm.css .band — the master data across, each value with its proof. */
const BAND = "mb-5 flex";
const CELL =
    "min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none";
const LAB = "text-[10.5px] tracking-[0.06em] uppercase text-ink-2";
const DERIVED = "tracking-normal normal-case text-mark";
const VAL =
    "mt-[5px] text-[17px] leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums";
const VAL_PLAIN =
    "mt-[5px] text-[15px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums";
const SUB = "mt-[3px] text-[11.5px] text-ink-2";
/* on and off exclude each other rather than stacking: two background
   utilities in one class list are decided by the order in the generated CSS,
   not by the order they are written in. */
const SWITCH =
    "relative h-6 w-10 flex-none cursor-pointer rounded-[var(--r-pill)] after:absolute after:top-[3px] after:left-[3px] after:size-[18px] after:rounded-full after:bg-paper after:transition-transform after:duration-[.16s] after:ease-out after:content-['']";
const SWITCH_ON = "bg-action after:translate-x-4";
const SWITCH_OFF = "bg-sunk-2";

/* crm.css .form / .field — the fields the head opens into. */
const INPUTS =
    "[&_:is(input,textarea,select)]:w-full [&_:is(input,textarea,select)]:rounded-[10px] [&_:is(input,textarea,select)]:border-0 [&_:is(input,textarea,select)]:bg-sunk [&_:is(input,textarea,select)]:px-[11px] [&_:is(input,textarea,select)]:py-[7px] [&_:is(input,textarea,select)]:text-[13.5px] [&_:is(input,textarea,select)]:leading-[1.4] [&_:is(input,textarea,select)]:font-normal [&_:is(input,textarea,select)]:tracking-[-0.01em] [&_:is(input,textarea,select)]:text-foreground [&_:is(input,textarea,select)]:outline-0 [&_:is(input,textarea)::placeholder]:text-[#B0B0B5] [&_:is(input,textarea,select):focus]:bg-sunk-2 [&_:is(input,textarea,select):focus]:shadow-[inset_0_0_0_2px_var(--accent)] [&_textarea]:min-h-[88px] [&_textarea]:resize-y";
const FORM = `max-w-[820px] ${INPUTS}`;
const FIELD =
    "flex flex-col gap-1 [&>span]:pl-0.5 [&>span]:text-[10.5px] [&>span]:leading-none [&>span]:font-normal [&>span]:tracking-[0.05em] [&>span]:uppercase [&>span]:text-ink-2 [&_input]:w-full";
const FIELD_GROW = `${FIELD} min-w-[148px] flex-1`;
const DATEPICKER = "w-[148px] [&_input]:pr-8";
const SHOOTBLOCK = "pt-3.5 pb-4 shadow-[inset_0_1px_0_var(--rule)]";

/* crm.css .rowset — the shoots, the time on the left. */
const ROW =
    "flex items-center gap-3.5 py-[11px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none max-[640px]:flex-wrap";
const WHEN =
    "w-[150px] flex-none text-[13px] leading-[1.47] font-semibold tracking-[-0.012em] tabular-nums";
const ST = "block text-[11.5px] leading-[1.47] font-normal text-ink-2";
const WHAT = "min-w-0 flex-1 text-[13.5px] leading-[1.47]";
const WHAT_S = "text-[12px] leading-[1.47] text-ink-2";
const RIGHT =
    "w-[118px] flex-none text-right text-[12px] leading-[1.47] text-ink-2";

/* crm.css .quote — the scope, line by line. */
const QR =
    "flex items-baseline gap-3.5 py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none";
const TI = "min-w-0 flex-1 text-[13.5px] leading-[1.47]";
const TAG = "ml-[3px] text-[11.5px] text-action";
const QTY =
    "w-14 flex-none text-right text-[12.5px] leading-[1.47] tabular-nums text-ink-2";
const AMT = "w-28 flex-none text-right text-[13px] leading-[1.47] tabular-nums";
const WAS = "mt-0.5 block text-[11.5px] font-normal text-action";
const QUOTE_META =
    "mt-[7px] text-[12px] text-ink-2 [&_em]:not-italic [&_em]:text-action";

/* crm.css .todo — what is still open. */
const TASK =
    "flex items-center gap-3 py-[7px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none";
const BOX = "size-[15px] flex-none rounded-[5px] bg-sunk-2";
const TASK_T = "text-[13.5px] leading-[1.47]";
const AUD =
    "text-[10.5px] leading-[1.47] tracking-[0.05em] uppercase text-ink-2";
const DUE = "ml-auto text-[12px] leading-[1.47] tabular-nums";

/* crm.css .composer / .stream — the note and the trace it lands in. */
const COMPOSER = `mt-4 pt-1 pb-0.5 ${FORM}`;
const FOOT = "mt-2.5 flex items-center gap-3";
const EV = "relative flex gap-3.5 py-2.5 shadow-[inset_0_1px_0_var(--rule)]";
const EV_T = "w-[110px] flex-none text-[12px] leading-[1.3] tabular-nums text-ink-2";
const EV_C = "min-w-0 flex-1 text-[13px] leading-[1.47]";

/* crm.css .rail — the trace column Page.vue holds. */
const RAIL =
    "w-70 flex-none overflow-y-auto bg-sunk px-5 py-4 max-[1080px]:hidden";
const RAIL_H2 =
    "m-0 mb-[9px] text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2";
const RAIL_P = "flex items-center gap-2 py-[5px] text-[12.5px] leading-[1.47]";
const RAIL_ST = "ml-auto text-[11.5px] leading-[1.47] text-[#3F6B4A]";
const RAIL_K = "flex gap-2.5 py-[5px] text-[12.5px] leading-[1.47]";
const RAIL_K_1 = "w-[74px] flex-none text-ink-2";
const RAIL_ITEM = "py-2 shadow-[inset_0_1px_0_#ECECEE]";
const RAIL_N =
    "text-[12.5px] leading-[1.47] font-semibold tracking-[-0.012em]";
const RAIL_M = "mt-px text-[11.5px] leading-[1.47] text-ink-2";
</script>

<template>
    <AdminLayout active="projekte">
        <Page>
            <template #right>
                <aside :class="RAIL">
                <h2 :class="RAIL_H2">{{ $t("lead.people") }}</h2>
                <div
                    v-for="(person, index) in people"
                    :key="person.id"
                    :class="RAIL_P"
                >
                    <Link :href="`/kontakte/${person.id}`">{{
                        person.name
                    }}</Link>
                    <select
                        v-if="editing"
                        v-model="edit.people[index].role"
                        class="max-w-[820px]"
                    >
                        <option
                            v-for="option in roles"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ $t(`contacts.role.${option.value}`) }}
                        </option>
                    </select>
                    <span v-else :class="RAIL_ST">{{
                        $t(`contacts.role.${person.role}`)
                    }}</span>
                </div>

                <template v-if="paymentPlan">
                    <h2 :class="RAIL_H2">{{ $t("dashboard.payments") }}</h2>
                    <div
                        v-for="instalment in paymentPlan.installments"
                        :key="instalment.id"
                        :class="RAIL_K"
                    >
                        <span :class="RAIL_K_1">{{
                            day(instalment.dueOn)
                        }}</span>
                        <span>{{ euro(instalment.amount) }}</span>
                    </div>
                </template>

                <template v-if="galleries.length">
                    <h2 :class="RAIL_H2">{{ $t("contacts.galleries") }}</h2>
                    <div
                        v-for="gallery in galleries"
                        :key="gallery.id"
                        :class="RAIL_ITEM"
                    >
                        <Link
                            :class="RAIL_N"
                            :href="`/galerien/${gallery.id}/medien`"
                            >{{ gallery.title ?? "—" }}</Link
                        >
                        <div v-if="gallery.expiresAt" :class="RAIL_M">
                            {{
                                $t("contacts.expiresOn", {
                                    date: day(gallery.expiresAt),
                                })
                            }}
                        </div>
                    </div>
                </template>

                <h2 :class="RAIL_H2">{{ $t("project.documents") }}</h2>
                <div :class="RAIL_ITEM">
                    <Link
                        :class="RAIL_N"
                        :href="`/auftraege/${header.id}/vertrag`"
                        >{{ $t("project.contract") }}</Link
                    >
                </div>
                <div :class="RAIL_ITEM">
                    <Link
                        :class="RAIL_N"
                        :href="`/auftraege/${header.id}/frageboegen`"
                        >{{ $t("project.forms") }}</Link
                    >
                </div>
                <div :class="RAIL_ITEM">
                    <Link
                        :class="RAIL_N"
                        :href="`/auftraege/${header.id}/nachrichten`"
                        >{{ $t("project.messages") }}</Link
                    >
                </div>
                </aside>
            </template>

            <div
                class="flex min-w-0 flex-1 items-stretch"
                data-tokens="crm"
            >
                <div :class="WORK">
                    <div :class="KICK">
                        <Link href="/projekte">{{ $t("projects.title") }}</Link>
                    </div>

                    <div :class="TITLE">
                        <div>
                            <input
                                v-if="editing"
                                v-model="edit.name"
                                class="max-w-[820px]"
                                type="text"
                                style="font-size: 22px"
                            />
                            <h1 v-else :class="H1">
                                {{ header.names }}
                            </h1>
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
                            <Link
                                v-if="header.canManageQuotes"
                                :class="LINK"
                                :href="`/auftraege/${header.id}/angebote`"
                            >
                                {{ $t("project.quotes") }}
                            </Link>
                            <Button
                                :as="Link"
                                variant="pill"
                                :href="`/auftraege/${header.id}/shootings`"
                                >{{ $t("project.shoots") }}</Button
                            >
                        </div>
                    </div>

                    <!-- Master data across, six values on one line, each with its proof
             underneath — not a phase tile that a seventh phase would break. -->
                    <div :class="BAND" style="margin-top: var(--s5)">
                        <div :class="CELL">
                            <div :class="LAB">{{ $t("project.position") }}</div>
                            <select
                                v-if="editing && stage"
                                v-model="edit.stage"
                                class="max-w-[820px]"
                            >
                                <option
                                    v-for="option in stages"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <div v-else :class="VAL_PLAIN">
                                {{ figures.phase.value ?? "—" }}
                            </div>
                            <div v-if="figures.phase.proof" :class="SUB">
                                {{ figures.phase.proof }}
                            </div>
                        </div>
                        <div :class="CELL">
                            <div :class="LAB">
                                {{ $t("project.mainDateLabel") }}
                                <span v-if="editing" :class="DERIVED">
                                    · {{ $t("project.fromShoots") }}</span
                                >
                            </div>
                            <div :class="VAL_PLAIN">
                                {{ day(figures.nextDate.value) }}
                            </div>
                            <div :class="SUB">
                                {{ $t("board.shootCount", shoots.length) }}
                            </div>
                        </div>
                        <div :class="CELL">
                            <div :class="LAB">
                                {{ $t("board.colValue") }}
                                <span v-if="editing" :class="DERIVED">
                                    · {{ $t("project.fromScope") }}</span
                                >
                            </div>
                            <div :class="VAL">
                                {{ euro(figures.value.value) }}
                            </div>
                            <div v-if="figures.value.proof" :class="SUB">
                                {{ figures.value.proof }}
                            </div>
                        </div>
                        <div :class="CELL">
                            <div :class="LAB">
                                {{ $t("project.openAmount") }}
                                <span v-if="editing" :class="DERIVED">
                                    · {{ $t("project.fromPayments") }}</span
                                >
                            </div>
                            <div
                                :class="[
                                    VAL,
                                    Number(figures.open.value ?? 0) > 0 &&
                                        'text-action',
                                ]"
                            >
                                {{ euro(figures.open.value) }}
                            </div>
                            <div v-if="figures.open.proof" :class="SUB">
                                {{
                                    $t(
                                        "project.instalments",
                                        Number(figures.open.proof),
                                    )
                                }}
                            </div>
                        </div>
                        <div v-if="sourceLabel" :class="CELL">
                            <div :class="LAB">{{ $t("leads.source") }}</div>
                            <select
                                v-if="editing"
                                v-model="edit.source"
                                class="max-w-[820px]"
                            >
                                <option
                                    v-for="option in sources"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <div v-else :class="VAL_PLAIN">
                                {{ sourceLabel }}
                            </div>
                            <input
                                v-if="editing && edit.source === 'other'"
                                v-model="edit.source_other"
                                type="text"
                                class="max-w-[820px]"
                                list="source-presets"
                                style="margin-top: 6px"
                            />
                            <datalist id="source-presets">
                                <option
                                    v-for="value in presets.source"
                                    :key="value"
                                    :value="value"
                                />
                            </datalist>
                        </div>
                        <div :class="CELL">
                            <div :class="LAB">{{ $t("project.portal") }}</div>
                            <!-- The figure and the switch that changes it, in one cell: this
                 is the one key value that is a decision rather than a
                 derivation, so it is the one with a control. -->
                            <div
                                :class="[
                                    VAL_PLAIN,
                                    'flex items-center gap-3',
                                ]"
                            >
                                {{
                                    figures.portal.value === "on"
                                        ? $t("contacts.portalOn")
                                        : $t("contacts.portalOff")
                                }}
                                <span
                                    v-if="header.canManagePortalVisibility"
                                    :class="[
                                        SWITCH,
                                        figures.portal.value === 'on'
                                            ? SWITCH_ON
                                            : SWITCH_OFF,
                                    ]"
                                    role="button"
                                    :aria-label="$t('project.portalToggle')"
                                    @click="togglePortal"
                                />
                            </div>
                        </div>
                    </div>

                    <section v-if="editing || shoots.length" :class="SEC">
                        <h2>{{ $t("lead.dates") }}</h2>
                        <div v-if="editing" :class="FORM">
                            <div
                                v-for="shoot in edit.shoots"
                                :key="shoot.id"
                                :class="SHOOTBLOCK"
                            >
                                <div>
                                    <label :class="FIELD"
                                        ><span>{{
                                            $t("project.shootDate")
                                        }}</span
                                        ><DatePicker v-model="shoot.date" :class="DATEPICKER"
                                    /></label>
                                    <label :class="FIELD"
                                        ><span>{{
                                            $t("project.callTime")
                                        }}</span
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
                                <div>
                                    <label :class="FIELD_GROW"
                                        ><span>{{
                                            $t("project.venueName")
                                        }}</span
                                        ><input
                                            v-model="shoot.venue_name"
                                            type="text"
                                    /></label>
                                    <label :class="FIELD_GROW"
                                        ><span>{{
                                            $t("project.venueCity")
                                        }}</span
                                        ><input
                                            v-model="shoot.venue_city"
                                            type="text"
                                    /></label>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="shoots.length">
                            <div
                                v-for="shoot in shoots"
                                :key="shoot.id"
                                :class="ROW"
                            >
                                <span :class="WHEN">
                                    {{ day(shoot.date) }}
                                    <span :class="ST">{{
                                        shoot.statusLabel
                                    }}</span>
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

                    <!-- The scope as it stands, not a copy of the quote. Nothing is
             locked: the order changes in conversation and the document lags
             behind. Forbid the change and the scope is simply wrong while the
             work happens anyway. -->
                    <section v-if="scope.items.length" :class="SEC">
                        <h2>{{ $t("project.scope") }}</h2>
                        <div>
                            <div
                                v-for="item in scope.items"
                                :key="item.id"
                                :class="QR"
                            >
                                <span :class="TI">
                                    {{ item.title }}
                                    <span v-if="item.state" :class="TAG">{{
                                        $t(`project.scope_${item.state}`)
                                    }}</span>
                                </span>
                                <span :class="QTY">{{ item.quantity }}×</span>
                                <span :class="AMT">
                                    {{
                                        item.unitPrice === null
                                            ? $t("packages.included")
                                            : euro(item.unitPrice)
                                    }}
                                    <span
                                        v-if="item.state === 'changed'"
                                        :class="WAS"
                                        >{{ euro(item.wasUnitPrice) }}</span
                                    >
                                </span>
                            </div>
                        </div>

                        <p v-if="scope.delta" :class="QUOTE_META">
                            <em>{{
                                $t("project.scopeDelta", {
                                    amount: euro(Math.abs(scope.delta)),
                                })
                            }}</em>
                            <Link
                                :class="[LINK, 'ml-3']"
                                :href="`/auftraege/${header.id}/angebote`"
                            >
                                {{ $t("project.sendAddendum") }}
                            </Link>
                        </p>
                    </section>

                    <section v-if="open.length" :class="SEC">
                        <h2>{{ $t("project.openItems") }}</h2>
                        <div>
                            <div v-for="task in open" :key="task.id" :class="TASK">
                                <button
                                    :class="BOX"
                                    type="button"
                                    @click="complete(task)"
                                />
                                <span :class="TASK_T">{{ task.title }}</span>
                                <span :class="AUD">{{ task.audience }}</span>
                                <span
                                    :class="[
                                        DUE,
                                        task.isOverdue
                                            ? 'text-action'
                                            : 'text-ink-2',
                                    ]"
                                    >{{
                                        task.dueOn ? day(task.dueOn) : ""
                                    }}</span
                                >
                            </div>
                        </div>
                    </section>

                    <section :class="SEC">
                        <h2>{{ $t("lead.notes") }}</h2>

                        <form :class="COMPOSER" @submit.prevent="addNote">
                            <textarea
                                v-model="note.body"
                                rows="2"
                                class="mb-3"
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

                        <!-- Notes and the audit trail in one trace: what happened and what
               somebody wrote about it are read together or not at all. -->
                        <div>
                            <div
                                v-for="note in notes"
                                :key="note.id"
                                :class="EV"
                            >
                                <span :class="EV_T">{{ day(note.writtenAt) }}</span>
                                <span :class="EV_C"
                                    >{{ note.body }}
                                    <span class="text-ink-2"
                                        >— {{ note.author }}</span
                                    ></span
                                >
                            </div>
                            <div
                                v-for="(entry, index) in activity"
                                :key="index"
                                :class="EV"
                            >
                                <span :class="EV_T">{{ day(entry.at) }}</span>
                                <span :class="[EV_C, 'text-ink-2']">
                                    {{ entry.label }}
                                    <span v-if="entry.actorName">
                                        — {{ entry.actorName }}</span
                                    >
                                </span>
                            </div>
                            <div
                                v-for="task in done"
                                :key="task.id"
                                :class="EV"
                            >
                                <span :class="EV_T">{{
                                    task.dueOn ? day(task.dueOn) : ""
                                }}</span>
                                <span :class="[EV_C, 'text-ink-2']">{{ task.title }}</span>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </Page>
    </AdminLayout>
</template>
