<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef, watch } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import { Clock } from "lucide-vue-next";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import LeadQueue from "@/admin/components/crm/LeadQueue.vue";
import Page from "@/ui/page/Page.vue";
import PageHead from "@/ui/page/PageHead.vue";
import ProjectSettings from "@/admin/components/crm/ProjectSettings.vue";
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
    AlertDialogBody,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/ui/alert-dialog";
import { formatEurosRounded } from "@/lib/money";
import { daysAgo, TYPE_DOT } from "@/admin/lib/boardCard";
import { useLiveProps } from "@/admin/lib/liveReload";
import { Button } from "@/ui/button";
import { ContextMenu, ContextMenuContent, ContextMenuItem, ContextMenuSeparator, ContextMenuTrigger } from "@/ui/context-menu";
import { QueueGroup, QueueItem } from "@/ui/queue";
import { ChoiceGroup } from "@/ui/choice-group";
import { Field } from "@/ui/field";
import { Input } from "@/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/ui/select";

type Card = App.Crm.Data.LeadCardData;
type Column = { stage: string; label: string; cards: Card[]; total: number };
type Group = { key: string; rows: Card[] };

const props = defineProps<{
    columns: Column[];
    groups: Group[];
    queue: { key: string; rows: App.Crm.Data.LeadQueueRowData[] }[];
    view: string;
    stageSettings: Omit<
        InstanceType<typeof ProjectSettings>["$props"],
        "workflow" | "canManageTasks" | "selection" | "returnTo"
    > | null;
    workflow?: Record<string, unknown> | null;
    settingsSelection: InstanceType<typeof ProjectSettings>["$props"]["selection"] | null;
    returnTo: string | null;
}>();
useLiveProps(["columns", "groups", "queue", "stageSettings"]);

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
const DONE_STAGES = ["booked"];

function shortDate(date: string | null) {
    return date
        ? new Date(date).toLocaleDateString("de-DE", {
              day: "2-digit",
              month: "2-digit",
              year: "numeric",
          })
        : null;
}

// Nothing travels with the pointer, so the row itself has to say it is under
// way — otherwise one drags something that visibly stays put. The insertion
// mark is the only thing that moves.
const moving = ref<{ id: string; column: number } | null>(null);
const target = ref<number | null>(null);
const insertAt = ref<number | null>(null);

function onDragStart(event: DragEvent, card: Card, column: number) {
    moving.value = { id: card.id, column };
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

    move(moving.value.id, props.columns[column].stage);
    reset();
}

function reset() {
    moving.value = null;
    target.value = null;
    insertAt.value = null;
}

// The move to "Gebucht" is the booking (AdvanceLeadStage): the booking is
// written, the shoots go to booked and the card leaves this board for
// /projekte. Every other move is a stage correction and asks nothing.
// Kept apart from the open state: AlertDialogAction closes the dialog before
// its @click runs, so an id cleared on close is already null in book().
const booking = ref<string | null>(null);
const confirmingBook = ref(false);

function move(id: string, stage: string) {
    if (stage === "booked") {
        booking.value = id;
        confirmingBook.value = true;

        return;
    }

    router.post(
        `/anfragen/${id}/stufe`,
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
}

// T-20: schliessen mit dem Ausgang. Bis hierhin liess sich eine Anfrage
// ueberhaupt nicht abschliessen — markLost() hatte keinen Aufrufer.
const closing = ref<string | null>(null);
const confirmingClose = ref(false);
const closeForm = useForm({ outcome: "lost", reason: "" });

function closeLead() {
    closeForm.post(`/anfragen/${closing.value}/schliessen`, {
        preserveScroll: true,
        onSuccess: () => {
            closeForm.reset();
        },
    });
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
        move(card.id, stage);
    }
}

const query = ref("");
const stage = ref<string | null>(null);
const onlyStale = ref(false);

const staleCount = computed(
    () => props.groups.flatMap((group) => group.rows).filter((card) => card.stale).length,
);

const pills = computed(() =>
    props.columns
        .map((column) => ({
            key: column.stage,
            name: column.label,
            count: props.groups
                .flatMap((group) => group.rows)
                .filter((card) => card.stage === column.stage).length,
        }))
        .filter((option) => option.count > 0),
);

const total = computed(() => props.groups.flatMap((group) => group.rows).length);
const stageOptions = computed(() =>
    pills.value.map((option) => ({ value: option.key, label: `${option.name} ${option.count}` })),
);

const pillRow = useTemplateRef<HTMLElement>("pillRow");
const showActivePill = () => pillRow.value?.querySelector("label:has(:checked)")?.scrollIntoView({ inline: "center", block: "nearest" });
onMounted(showActivePill);
watch(stage, showActivePill, { flush: "post" });

const collapsed = ref<string[]>([]);

function toggleGroup(key: string) {
    collapsed.value = collapsed.value.includes(key)
        ? collapsed.value.filter((k) => k !== key)
        : [...collapsed.value, key];
}

const groupRows = computed(() => {
    const needle = query.value.trim().toLocaleLowerCase("de");

    return props.groups
        .map((group) => ({
            ...group,
            rows: group.rows.filter(
                (card) =>
                    (!stage.value || card.stage === stage.value) &&
                    (!onlyStale.value || card.stale) &&
                    (!needle ||
                        `${card.names} ${card.venue ?? ""}`
                            .toLocaleLowerCase("de")
                            .includes(needle)),
            ),
        }))
        .filter((group) => group.rows.length);
});

/* crm.css .stufe s.s2 … s.s5 — the stage dot darkens toward the decision and
   goes warm at the one that still needs an answer. */
const STAGE_DOT = [
    "bg-foreground",
    "bg-ink-2",
    "bg-faint",
    "bg-action",
    "bg-mark",
];
const workflowAllowed = computed(() =>
    (usePage().props.auth.can ?? []).includes("manage_tasks"),
);

</script>

<template>
    <AdminLayout active="anfragen">
        <ProjectSettings
            v-if="settingsSelection && stageSettings"
            v-bind="stageSettings"
            :selection="settingsSelection"
            :return-to="returnTo"
            :can-manage-tasks="workflowAllowed"
            :workflow="workflow"
            @close="router.visit(returnTo ?? '/anfragen', { preserveState: true, preserveScroll: true })"
        />
        <Page v-else wide>
            <div data-tokens class="min-w-0 flex-1">
                <PageHead :title="$t('board.title')">
                    <ChoiceGroup
                        v-model="view"
                        class="ml-3"
                        :options="[
                            { value: 'liste', label: $t('board.viewList') },
                            { value: 'board', label: $t('board.viewBoard') },
                        ]"
                        @update:model-value="choose"
                    />

                    <Button
                        v-if="stageSettings"
                        :as="Link"
                        class="ml-auto max-[640px]:hidden"
                        variant="secondary"
                        href="/anfragen/einstellungen/stufen"
                    >
                        {{ $t("stages.entry") }}
                    </Button>

                    <Button
                        :as="Link"
                        :class="[!stageSettings && 'ml-auto', 'max-[640px]:ml-auto']"
                        variant="default"
                        href="/anfragen/neu"
                    >
                        {{ $t("board.create") }}
                    </Button>
                </PageHead>

                <!-- Die Spalte traegt das Material, die Karte bleibt weiss
             darauf. Weiss auf Weiss liess keine Spalte als Behaelter lesen —
             sichtbar wurde sie erst an ihrer Trennlinie. -->
                <div
                    v-if="view === 'board'"
                    class="flex snap-x snap-mandatory items-stretch gap-6 overflow-x-auto px-6 pt-4 pb-6 max-[640px]:scroll-px-4 max-[640px]:px-4 max-[640px]:pr-12"
                >
                    <section
                        v-for="(column, index) in columns"
                        :key="column.stage"
                        class="flex min-w-0 shrink-0 grow basis-[200px] snap-start max-[640px]:basis-[85vw] flex-col gap-2.5 rounded-[var(--r)]"
                        :class="target === index && 'bg-[var(--accent-bg)]'"
                        @dragover="onDragOver($event, index, null)"
                        @drop.prevent="onDrop(index)"
                    >
                        <header class="flex items-baseline gap-1.5 px-1 pt-1 pb-0.5">
                            <b
                                class="text-[13.5px] leading-[1.2] font-semibold text-foreground"
                                >{{ column.label }}</b
                            >
                            <span
                                class="ml-auto font-mono text-[12.5px] whitespace-nowrap tabular-nums text-ink-2"
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
                                class="-mx-1.5 h-0.5 rounded-[1px] bg-ring"
                            />

                            <ContextMenu>
                            <ContextMenuTrigger as-child>
                            <Link
                                class="relative block cursor-grab rounded-[var(--r)] bg-paper p-3.5 text-inherit no-underline shadow-card transition-[background] duration-[120ms] ease-out hover:bg-accent"
                                :class="
                                    moving?.id === card.id && 'cursor-grabbing'
                                "
                                :href="`/anfragen/${card.id}`"
                                draggable="true"
                                @dragstart="onDragStart($event, card, index)"
                                @dragend="reset"
                                @dragover="onDragOver($event, index, row)"
                                @keydown="onKey($event, card, index)"
                            >
                                <span
                                    class="block text-ui leading-[1.3] tracking-[-0.014em]"
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
                                    class="mt-0.5 block text-small"
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
                                        :class="TYPE_DOT[card.typeKey] ?? 'bg-faint'"
                                    />
                                    <span class="text-small leading-none text-muted-foreground">
                                        {{ card.type }}
                                    </span>
                                    <span
                                        v-if="card.conflicts.length"
                                        class="ml-auto text-small leading-none text-destructive"
                                    >
                                        {{ $t("board.dateDoubled") }}
                                    </span>
                                </span>

                                <!-- Wert und Liegezeit teilen sich eine Zeile:
                     was die Anfrage wert ist und wie lange sie schon
                     liegt, sind die beiden Zahlen, nach denen die
                     naechste Karte ausgesucht wird. -->
                                <span
                                    class="mt-2.5 flex items-center gap-2.5"
                                >
                                    <span
                                        class="font-mono text-small leading-none font-medium tabular-nums"
                                    >
                                        {{
                                            card.value === null
                                                ? "—"
                                                : formatEurosRounded(card.value)
                                        }}
                                    </span>
                                    <span
                                        v-if="!DONE_STAGES.includes(column.stage)"
                                        class="ml-auto inline-flex items-center bg-no-repeat bg-[length:11px_11px] text-small leading-none tabular-nums"
                                        :class="
                                            card.stale
                                                ? 'h-[19px] rounded-md whitespace-nowrap bg-due-bg pr-[7px] pl-[22px] bg-[position:7px_center] bg-icon-clock-due font-medium text-due'
                                                : 'pl-4 bg-[position:left_center] bg-icon-clock text-ink-2'
                                        "
                                    >
                                        {{ daysAgo(card.daysInStage) }}
                                    </span>
                                </span>
                            </Link>
                            </ContextMenuTrigger>
                            <ContextMenuContent class="w-[208px]">
                                <ContextMenuItem
                                    v-for="option in columns"
                                    :key="option.stage"
                                    :disabled="option.stage === card.stage"
                                    @select="move(card.id, option.stage)"
                                >
                                    {{ option.label }}
                                </ContextMenuItem>
                                <ContextMenuSeparator />
                                <ContextMenuItem @select="closing = card.id; confirmingClose = true">
                                    {{ $t("board.close") }}
                                </ContextMenuItem>
                            </ContextMenuContent>
                            </ContextMenu>
                        </template>

                        <!-- An empty stage keeps its height, or there is no target there. -->
                        <div :class="target === index && 'min-h-11'" />
                    </section>
                </div>

                <!-- List — grouped by who owes the next move, not by stage. A board
             shows where something stands, a table shows what is next. -->
                <template v-else>
                <div class="flex flex-wrap items-center gap-4 px-6 pt-4.5">
                    <div ref="pillRow" class="flex flex-wrap gap-1.5 max-[640px]:w-full">
                        <ChoiceGroup
                            :model-value="stage ?? ''"
                            :options="[{ value: '', label: `${$t('project.filterAll')} ${total}` }, ...stageOptions]"
                            @update:model-value="(value) => (stage = value || null)"
                        />

                        <!-- Quer zu den Stufen, deshalb hinter dem Strich: der
                 Filter schneidet dieselbe Menge nach einer anderen
                 Frage. -->
                        <template v-if="staleCount">
                            <span class="mx-1 self-center h-[18px] w-px bg-rule" />

                            <Button
                                variant="secondary"
                                class="shrink-0 max-[640px]:min-h-11"
                                :class="onlyStale && 'bg-due-bg text-due'"
                                type="button"
                                :aria-pressed="onlyStale"
                                @click="onlyStale = !onlyStale"
                            >
                                <Clock />{{ $t("board.stale")
                                }}<span class="text-small tabular-nums opacity-60">{{ staleCount }}</span>
                            </Button>
                        </template>
                    </div>
                    <Input :aria-label="$t('board.searchPlaceholder')" v-model="query" class="ml-auto h-8 w-60 max-[640px]:ml-0 max-[640px]:h-11 max-[640px]:w-full" type="search" :placeholder="$t('board.searchPlaceholder')" />
                </div>

                <div class="hidden px-3 pt-2 max-[640px]:block">
                    <template v-for="group in groupRows" :key="group.key">
                        <QueueGroup :title="$t(`lead.group.${group.key}`)" :count="group.rows.length" />
                        <QueueItem
                            v-for="card in group.rows"
                            :key="card.id"
                            class="min-h-14"
                            :href="`/anfragen/${card.id}`"
                            :name="card.names"
                            :meta="daysAgo(card.daysInStage)"
                            :alert="card.stale"
                            :title="[
                                shortDate(card.date) ?? $t('board.openDate'),
                                columns.find((c) => c.stage === card.stage)?.label,
                                card.value === null ? null : formatEurosRounded(card.value),
                            ].filter(Boolean).join(' · ')"
                        />
                    </template>
                </div>

                <Table class="max-[640px]:hidden">
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
                            <TableGroupRow
                                :colspan="6"
                                :open="!collapsed.includes(group.key)"
                                @toggle="toggleGroup(group.key)"
                            >
                                {{ $t(`lead.group.${group.key}`) }}
                            </TableGroupRow>
                            <TableRow
                                v-for="card in collapsed.includes(group.key) ? [] : group.rows"
                                :key="card.id"
                            >
                                <TableCell tone="name">
                                    <Link :href="`/anfragen/${card.id}`">{{
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
                                        class="text-due"
                                    >
                                        · {{ $t("board.dateDoubled") }}</span
                                    >
                                </TableCell>
                                <TableCell tone="muted">{{
                                    card.venue ?? "—"
                                }}</TableCell>
                                <TableCell>
                                    <span
                                        class="inline-flex items-baseline gap-[7px] text-small"
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
                                    <span
                                        v-if="card.stale"
                                        class="inline-flex items-center h-[19px] rounded-md whitespace-nowrap bg-due-bg bg-no-repeat bg-[length:11px_11px] bg-[position:7px_center] bg-icon-clock-due pr-[7px] pl-[22px] text-small font-medium tabular-nums text-due"
                                    >
                                        {{ daysAgo(card.daysInStage) }}
                                    </span>
                                    <template v-else>{{
                                        daysAgo(card.daysInStage)
                                    }}</template>
                                </TableCell>
                            </TableRow>
                        </template>
                    </tbody>
                </Table>
                </template>

                <AlertDialog v-model:open="confirmingClose">
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{
                                $t("board.closeTitle")
                            }}</AlertDialogTitle>
                        </AlertDialogHeader>

                        <AlertDialogBody>
                            <Field v-slot="{ id }" :label="$t('board.outcome')">
                                <Select
                                    :model-value="closeForm.outcome"
                                    @update:model-value="(value) => (closeForm.outcome = String(value))"
                                >
                                    <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="lost">{{ $t("board.outcomeLost") }}</SelectItem>
                                        <SelectItem value="declined">{{ $t("board.outcomeDeclined") }}</SelectItem>
                                        <SelectItem value="closed">{{ $t("board.outcomeClosed") }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>

                            <Field
                                v-slot="{ id }"
                                :label="$t('board.closeReason')"
                                :error="closeForm.errors.reason"
                            >
                                <Input :id="id" v-model="closeForm.reason" type="text" />
                            </Field>
                        </AlertDialogBody>

                        <AlertDialogFooter>
                            <AlertDialogAction
                                :disabled="!closeForm.reason || closeForm.processing"
                                @click="closeLead"
                            >{{ $t("board.close") }}</AlertDialogAction>
                            <AlertDialogCancel>{{ $t("common.cancel") }}</AlertDialogCancel>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <AlertDialog v-model:open="confirmingBook">
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{
                                $t("board.bookTitle")
                            }}</AlertDialogTitle>
                        </AlertDialogHeader>
                        <AlertDialogBody>
                            <p class="m-0">{{ $t("board.bookConsequence") }}</p>
                        </AlertDialogBody>
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
        </Page>
    </AdminLayout>
</template>
