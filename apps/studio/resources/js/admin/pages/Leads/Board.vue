<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import LeadQueue from "@/admin/components/LeadQueue.vue";
import Page from "@/ui/page/Page.vue";
import PageHead from "@/ui/page/PageHead.vue";
import WorkflowPanel from "@/admin/components/WorkflowPanel.vue";
import {
    Table,
    TableCell,
    TableGroupRow,
    TableHead,
    TableRow,
} from "@/ui/table";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/ui/alert-dialog";
import { formatEurosRounded } from "@/admin/lib/money";
import { Button } from "@/ui/button";
import { ChoiceGroup } from "@/ui/choice-group";

type Card = App.Crm.Data.LeadCardData;
type Column = { stage: string; label: string; cards: Card[]; total: number };
type Group = { key: string; rows: Card[] };

const props = defineProps<{
    columns: Column[];
    groups: Group[];
    queue: { key: string; rows: App.Crm.Data.LeadQueueRowData[] }[];
    view: string;
    workflow?: Record<string, unknown> | null;
}>();

const view = ref(props.view);

// The switch writes the URL without a navigation and stores the choice, so a
// link stays shareable and the next visit opens on the same one.
function choose(next: string) {
    view.value = next;

    const url = new URL(window.location.href);
    url.searchParams.set("ansicht", next);
    history.replaceState(history.state, "", url);

    router.post(
        "/anfragen/ansicht",
        { ansicht: next },
        { preserveScroll: true, preserveState: true },
    );
}

// Grey rather than faded: opacity would have pushed the text under the
// legibility floor, the same trap as a third grey.
// Drei Typen, drei Farben aus den Tokens. Ueber das Label statt ueber den
// Wert, weil LeadCardData den Typ als Beschriftung traegt — type_other haengt
// dort schon dran.
const TYPE_DOT: Record<string, string> = {
    Hochzeit: "bg-action",
    Portrait: "bg-ok",
    Sonstige: "bg-faint",
};

const DONE_STAGES = ["contract_sent", "booked"];

function shortDate(date: string | null) {
    return date
        ? new Date(date).toLocaleDateString("de-DE", {
              day: "2-digit",
              month: "2-digit",
              year: "2-digit",
          })
        : null;
}

function relative(at: string) {
    const days = Math.round((Date.now() - new Date(at).getTime()) / 86400000);

    return days < 1
        ? "heute"
        : new Intl.RelativeTimeFormat("de-DE", { numeric: "auto" }).format(
              -days,
              "day",
          );
}

// Nothing travels with the pointer, so the row itself has to say it is under
// way — otherwise one drags something that visibly stays put. The insertion
// mark is the only thing that moves.
const moving = ref<{ id: string; leadId: string; column: number } | null>(null);
const target = ref<number | null>(null);
const insertAt = ref<number | null>(null);

function onDragStart(event: DragEvent, card: Card, column: number) {
    moving.value = { id: card.id, leadId: card.leadId, column };
    if (event.dataTransfer) event.dataTransfer.effectAllowed = "move";
}

function onDragOver(event: DragEvent, column: number, row: number | null) {
    if (moving.value === null || moving.value.column === column) return;

    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = "move";
    target.value = column;
    insertAt.value = row;
}

function onDrop(column: number) {
    if (moving.value === null || moving.value.column === column) return;

    move(moving.value.leadId, props.columns[column].stage);
    reset();
}

function reset() {
    moving.value = null;
    target.value = null;
    insertAt.value = null;
}

// The move to "Gebucht" is the booking (AdvanceLeadStage): the job is
// written, the shoots go to booked and the card leaves this board for
// /projekte. Every other move is a stage correction and asks nothing.
const booking = ref<string | null>(null);

function move(leadId: string, stage: string) {
    if (stage === "booked") {
        booking.value = leadId;

        return;
    }

    router.post(
        `/anfragen/${leadId}/stufe`,
        { stage },
        { preserveScroll: true },
    );
}

function book() {
    router.post(
        `/anfragen/${booking.value}/stufe`,
        { stage: "booked" },
        { preserveScroll: true },
    );
    booking.value = null;
}

// Without a mouse, and that is not optional: dragging a row across six
// columns is unusable on an iPad, and the table knows no dragging at all.
function onKey(event: KeyboardEvent, card: Card, column: number) {
    if (!event.metaKey && !event.ctrlKey) return;

    const to =
        event.key === "ArrowRight"
            ? column + 1
            : event.key === "ArrowLeft"
              ? column - 1
              : null;
    const stage = to === null ? null : props.columns[to]?.stage;

    if (stage) {
        event.preventDefault();
        move(card.leadId, stage);
    }
}

// The same targets the arrows reach, for a pointer that has no keyboard
// beside it.
const menu = ref<{ card: Card; x: number; y: number } | null>(null);

function openMenu(event: MouseEvent, card: Card) {
    event.preventDefault();
    menu.value = { card, x: event.clientX, y: event.clientY };
}

const groupRows = computed(() =>
    props.groups.filter((group) => group.rows.length),
);

/* crm.css .stufe s.s2 … s.s5 — the stage dot darkens toward the decision and
   goes warm at the one that still needs an answer. */
const STAGE_DOT = [
    "bg-foreground",
    "bg-[#5F5F63]",
    "bg-[#A1A1A6]",
    "bg-action",
    "bg-mark",
];
// Die Spalten kommen erst beim Oeffnen mit — ein Board laedt sie sonst bei
// jedem Aufruf mit, ohne dass sie jemand sieht.
const workflowOpen = ref(false);
const workflowAllowed = computed(() =>
    (usePage().props.auth.can ?? []).includes("manage_tasks"),
);

function openWorkflow() {
    workflowOpen.value = true;
    router.reload({ only: ["workflow"] });
}

</script>

<template>
    <AdminLayout active="anfragen">
        <Page wide @click="menu = null">
            <div data-tokens class="min-w-0 flex-1">
                <PageHead :title="$t('board.title')">
                    <button
                        v-if="workflowAllowed"
                        class="ml-auto flex cursor-pointer items-center gap-1.5 border-0 bg-transparent text-[13px] font-medium text-muted-foreground"
                        type="button"
                        @click="openWorkflow"
                    >
                        <svg class="size-[15px] fill-none stroke-current stroke-[1.6] [stroke-linecap:round] [stroke-linejoin:round]" viewBox="0 0 24 24">
                            <rect x="5" y="4" width="14" height="17" rx="2" />
                            <path d="M9 3h6v3H9z" />
                            <path d="M9 11h6M9 15h4" />
                        </svg>
                        {{ $t("board.workflows") }}
                    </button>

                    <ChoiceGroup
                        v-model="view"
                        class=""
                        :options="[
                            { value: 'board', label: $t('board.viewBoard') },
                            { value: 'liste', label: $t('board.viewList') },
                        ]"
                        variant="segmented"
                        @update:model-value="choose"
                    />

                    <Button :as="Link" variant="pill" href="/anfragen/neu">
                        {{ $t("board.create") }}
                    </Button>
                </PageHead>

                <!-- Die Spalte traegt das Material, die Karte bleibt weiss
             darauf. Weiss auf Weiss liess keine Spalte als Behaelter lesen —
             sichtbar wurde sie erst an ihrer Trennlinie. -->
                <div
                    v-if="view === 'board'"
                    class="flex items-stretch gap-2.5 overflow-x-auto px-5 pt-5 pb-7"
                >
                    <section
                        v-for="(column, index) in columns"
                        :key="column.stage"
                        class="flex min-w-0 shrink-0 grow basis-[200px] flex-col gap-2 rounded-xl bg-sunk p-2.5"
                        :class="target === index && 'bg-sunk-2'"
                        @dragover="onDragOver($event, index, null)"
                        @drop.prevent="onDrop(index)"
                    >
                        <header class="flex items-baseline gap-1.5 px-1 pt-0.5 pb-1">
                            <b
                                class="text-[11px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2"
                                >{{ column.label }}</b
                            >
                            <span
                                class="ml-auto text-[11px] tabular-nums text-ink-2"
                            >
                                {{ column.cards.length
                                }}<template v-if="column.total">
                                    ·
                                    {{
                                        formatEurosRounded(column.total)
                                    }}</template
                                >
                            </span>
                        </header>

                        <template
                            v-for="(card, row) in column.cards"
                            :key="card.id"
                        >
                            <div
                                v-if="target === index && insertAt === row"
                                class="-mx-1.5 h-0.5 rounded-[1px] bg-action"
                            />

                            <Link
                                class="relative block cursor-grab rounded-lg border border-border bg-paper px-2.5 py-[9px] text-inherit no-underline transition-[background,border-color] duration-[120ms] ease-out hover:border-line-hover"
                                :class="
                                    moving?.id === card.id && 'cursor-grabbing'
                                "
                                :href="`/anfragen/${card.leadId}`"
                                draggable="true"
                                @dragstart="onDragStart($event, card, index)"
                                @dragend="reset"
                                @dragover="onDragOver($event, index, row)"
                                @keydown="onKey($event, card, index)"
                                @contextmenu="openMenu($event, card)"
                            >
                                <span
                                    class="block text-[13.5px] leading-[1.3] tracking-[-0.014em]"
                                    :class="
                                        DONE_STAGES.includes(column.stage) ||
                                        moving?.id === card.id
                                            ? 'font-medium text-ink-2'
                                            : 'font-semibold'
                                    "
                                >
                                    {{ card.names }}
                                </span>
                                <span
                                    class="mt-0.5 block text-[12px]"
                                    :class="
                                        moving?.id === card.id
                                            ? 'text-mark'
                                            : 'text-ink-2'
                                    "
                                >
                                    {{
                                        shortDate(card.date) ??
                                        $t("board.openDate")
                                    }}
                                    <template v-if="card.venue">
                                        · {{ card.venue }}</template
                                    >
                                </span>
                                <!-- Der Auftragstyp als Punkt und als Wort:
                     die Farbe zum Ueberfliegen, das Wort zum Lesen.
                     Die Warnung darunter trug bislang dieselbe
                     Akzentfarbe wie der Punkt einer Hochzeit — sie
                     steht deshalb jetzt auf --late.

                     Auf beiden betroffenen Zeilen und ohne jemanden zu
                     nennen: wer kollidiert, ist die andere markierte
                     Zeile im selben Bild. -->
                                <span class="mt-1.5 flex items-center gap-1.5">
                                    <span
                                        class="size-1.5 flex-none rounded-full"
                                        :class="TYPE_DOT[card.type] ?? 'bg-faint'"
                                    />
                                    <span class="text-[11.5px] leading-none text-muted-foreground">
                                        {{ card.type }}
                                    </span>
                                    <span
                                        v-if="card.conflicts.length"
                                        class="ml-auto text-[11.5px] leading-none text-destructive"
                                    >
                                        {{ $t("board.dateDoubled") }}
                                    </span>
                                </span>
                            </Link>
                        </template>

                        <!-- An empty stage keeps its height, or there is no target there. -->
                        <div :class="target === index && 'min-h-11'" />
                    </section>
                </div>

                <!-- List — grouped by who owes the next move, not by stage. A board
             shows where something stands, a table shows what is next. -->
                <Table v-else>
                    <thead>
                        <tr>
                            <TableHead>{{ $t("board.colInquiry") }}</TableHead>
                            <TableHead>{{ $t("board.colDate") }}</TableHead>
                            <TableHead>{{ $t("board.colVenue") }}</TableHead>
                            <TableHead>{{ $t("board.colStage") }}</TableHead>
                            <TableHead align="right">{{
                                $t("board.colValue")
                            }}</TableHead>
                            <TableHead align="right">{{
                                $t("board.colLast")
                            }}</TableHead>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="group in groupRows" :key="group.key">
                            <TableGroupRow :colspan="6">
                                {{ $t(`lead.group.${group.key}`) }}
                            </TableGroupRow>
                            <TableRow v-for="card in group.rows" :key="card.id">
                                <TableCell tone="name">
                                    <Link :href="`/anfragen/${card.leadId}`">{{
                                        card.names
                                    }}</Link>
                                </TableCell>
                                <TableCell>
                                    {{
                                        shortDate(card.date) ??
                                        $t("board.openDate")
                                    }}
                                    <span
                                        v-if="card.conflicts.length"
                                        class="text-action"
                                    >
                                        · {{ $t("board.dateDoubled") }}</span
                                    >
                                </TableCell>
                                <TableCell tone="muted">{{
                                    card.venue ?? "—"
                                }}</TableCell>
                                <TableCell>
                                    <span
                                        class="inline-flex items-baseline gap-[7px] text-[12.5px]"
                                    >
                                        <s
                                            class="size-1.5 self-center rounded-full no-underline"
                                            :class="
                                                STAGE_DOT[
                                                    columns.findIndex(
                                                        (c) =>
                                                            c.stage ===
                                                            card.stage,
                                                    )
                                                ] ?? 'bg-foreground'
                                            "
                                        />
                                        {{
                                            columns.find(
                                                (c) => c.stage === card.stage,
                                            )?.label
                                        }}
                                    </span>
                                </TableCell>
                                <TableCell align="right">
                                    {{
                                        card.value === null
                                            ? "—"
                                            : formatEurosRounded(card.value)
                                    }}
                                </TableCell>
                                <TableCell tone="muted" align="right">
                                    {{ relative(card.lastAt) }}
                                </TableCell>
                            </TableRow>
                        </template>
                    </tbody>
                </Table>

                <div
                    v-if="menu"
                    class="fixed z-20 w-[208px] rounded-[12px] bg-paper p-[5px] shadow-[0_8px_24px_rgba(0,0,0,0.16)]"
                    :style="{ left: `${menu.x}px`, top: `${menu.y}px` }"
                    @click.stop
                >
                    <div
                        v-for="column in columns"
                        :key="column.stage"
                        class="rounded-lg px-[9px] py-1.5 text-[12.5px]"
                        :class="
                            column.stage === menu.card.stage
                                ? 'cursor-default text-mark'
                                : 'cursor-pointer hover:bg-sunk'
                        "
                        @click="
                            column.stage !== menu.card.stage &&
                            move(menu.card.leadId, column.stage)
                        "
                    >
                        {{ column.label }}
                    </div>
                </div>

                <AlertDialog
                    :open="booking !== null"
                    @update:open="
                        (open: boolean) => {
                            if (!open) booking = null;
                        }
                    "
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{
                                $t("board.bookTitle")
                            }}</AlertDialogTitle>
                        </AlertDialogHeader>
                        <p>{{ $t("board.bookConsequence") }}</p>
                        <AlertDialogFooter>
                            <AlertDialogAction @click="book">{{
                                $t("board.book")
                            }}</AlertDialogAction>
                            <AlertDialogCancel>{{
                                $t("common.cancel")
                            }}</AlertDialogCancel>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>

            <WorkflowPanel
                v-if="workflowOpen"
                :workflow="workflow as never"
                @close="workflowOpen = false"
            />
        </Page>
    </AdminLayout>
</template>
