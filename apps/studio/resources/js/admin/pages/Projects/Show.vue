<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import { Alert, AlertDescription, AlertTitle } from "@/ui/alert";
import { Button } from "@/ui/button";
import LineItems from "@/admin/components/quote/LineItems.vue";
import NoteThread from "@/admin/components/crm/NoteThread.vue";
import CappedList from "@/admin/components/crm/CappedList.vue";
import Composer from "@/admin/components/crm/Composer.vue";
import EmptyTiles from "@/admin/components/crm/EmptyTiles.vue";
import InstallmentPaidDialog from "@/admin/components/payments/InstallmentPaidDialog.vue";
import MetricBand from "@/admin/components/crm/MetricBand.vue";
import type { BandMetric } from "@/admin/components/crm/MetricBand.vue";
import NextStepCard from "@/admin/components/crm/NextStepCard.vue";
import Panel from "@/admin/components/crm/Panel.vue";
import PhaseButton from "@/admin/components/crm/PhaseButton.vue";
import ProjectQueue from "@/admin/components/crm/ProjectQueue.vue";
import ProjectStep from "@/admin/components/crm/ProjectStep.vue";
import ShootAdvance from "@/admin/components/crm/ShootAdvance.vue";
import { Field } from "@/ui/field";
import { Input } from "@/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/ui/select";
import ProjectFields from "@/admin/components/crm/ProjectFields.vue";
import type { ProjectFieldValues } from "@/admin/components/crm/ProjectFields.vue";
import ProjectShell from "@/admin/components/crm/ProjectShell.vue";
import TaskCreateDialog from "@/admin/components/crm/TaskCreateDialog.vue";
import Card from "@/ui/page/Card.vue";
import { ROLE_SUGGESTIONS, roleLabel } from "@/admin/lib/roles";
import Section from "@/ui/page/Section.vue";
import { formatEurosRounded } from "@/lib/money";
import Chip from "@/ui/page/Chip.vue";
import { quoteTone } from "@/admin/lib/quoteDisplay";
import { dayWithWeekday as day, daysUntil } from "@/admin/lib/dates";
import { installmentChipClass } from "@/admin/lib/payments";
import { bandMetrics, isBare, splitNextStep, timelineWindow } from "@/admin/lib/projectOverview";
import { chipClass, dayAndMonth } from "@/admin/lib/shoots";
import { groupEntries } from "@/admin/lib/groupEntries";
import { nextStep, paymentCall, stepRows } from "@/admin/lib/projectNextStep";
import { sendQuoteHref } from "@/admin/lib/leadPrimary";
import { stepOnArrow } from "@/admin/lib/queueStep";
import { useLiveProps } from "@/admin/lib/liveReload";

type ProjectFieldDefinition = InstanceType<typeof ProjectFields>["$props"]["fields"][number];

const props = defineProps<{
    header: App.Crm.Screens.CaseHeaderData;
    shoots: App.Crm.Data.ShootData[];
    thread: App.Crm.Data.ThreadEntryData[];
    tasks: App.Crm.Data.TaskRowData[];
    shootOptions: { value: string; label: string }[];
    staffOptions: { value: string; label: string }[];
    // Kommt von CaseController@show; bis dahin bleibt die Ankerliste leer.
    anchors?: { value: string; label: string; group: string; on: string | null }[];
    quote: App.Quotes.Data.QuoteData | null;
    supersededQuotes: { id: string; number: string | null; net: number }[];
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
    fields: ProjectFieldDefinition[];
    fieldValues: ProjectFieldValues;
    stages: { value: string; label: string }[];
    sources: { value: string; label: string }[];
    roles: { value: string; label: string }[];
    presets: { source: string[] };
    stage: string | null;
    source: string | null;
    sourceOther: string | null;
    sourceLabel: string | null;
    inquiry: {
        text: string | null;
        typeLabel: string;
        sourceLabel: string | null;
        capturedVia: string | null;
        capturedAt: string;
    } | null;
    stalled: {
        shootId: string;
        label: string;
        date: string;
        days: number;
        status: string;
        since: string | null;
    } | null;
}>();
useLiveProps(["header", "shoots", "shootKinds", "tasks", "shootOptions", "staffOptions", "anchors", "thread", "scope", "quote", "supersededQuotes", "fields", "fieldValues", "stages", "presets", "stage", "source", "sourceOther", "sourceLabel", "inquiry", "stalled"]);

const { t } = useI18n();
const page = usePage();

const base = computed(() => `/auftraege/${props.header.id}`);
const galleriesHref = computed(() => props.header.tabs.find((tab) => tab.key === "galleries")?.href);

const reachable = ref<App.Crm.Data.CaseQueueRowData[]>([]);

function onKey(event: KeyboardEvent) {
    stepOnArrow(event, reachable.value, "/auftraege");
}

onMounted(() => addEventListener("keydown", onKey));
onUnmounted(() => removeEventListener("keydown", onKey));

const step = computed(() => (props.header.canManageShoots ? nextStep(props.shoots) : null));
const callHref = computed(() => paymentCall(props.header.paymentPlan, props.header.people));
const sendHref = computed(() => sendQuoteHref(props.quote, props.header.canManageQuotes));
const createQuote = computed(() => !props.header.isBooked && props.header.canManageQuotes && !props.quote);
const addShoot = computed(() => props.header.isBooked && props.header.canManageShoots && !props.shoots.length);
const addGallery = computed(
    () => props.header.isBooked && props.header.canManageShoots && !step.value && !addShoot.value && !props.header.galleries.length && !!galleriesHref.value,
);
const delivered = computed(() => props.shoots.every((shoot) => shoot.isCancelled || !stepRows([shoot]).length));
const completeNow = computed(
    () =>
        props.header.isBooked &&
        props.header.canComplete &&
        !props.header.isCompleted &&
        !!props.header.galleries.length &&
        !step.value &&
        !callHref.value &&
        !addShoot.value &&
        delivered.value &&
        (!plan.value || plan.value.openTotal === 0),
);

function completeProject() {
    router.post(`${base.value}/abschluss`, {}, { preserveScroll: true });
}

function euro(minor: string | number | null) {
    return minor === null ? "—" : formatEurosRounded(Number(minor));
}

function movePhase(stage: string) {
    router.post(`${base.value}/stufe`, { stage }, { preserveScroll: true });
}

const open = computed(() => props.tasks.filter((task) => !task.isDone));
const done = computed(() => props.tasks.filter((task) => task.isDone));
const overdue = computed(() => open.value.filter((task) => task.isOverdue));
const next = computed(() => splitNextStep(props.tasks));
const showDone = ref(false);

const plan = computed(() => props.header.paymentPlan);
const installments = computed(() => plan.value?.installments.filter((row) => row.status !== "cancelled") ?? []);
const nextInstallment = computed(() => installments.value.find((row) => row.status !== "paid"));
const paid = computed(() => (plan.value ? plan.value.total - plan.value.openTotal : 0));
const paidShare = computed(() => (plan.value?.total ? Math.round((paid.value / plan.value.total) * 100) : 0));

const primary = computed(() => props.shoots.find((shoot) => shoot.isPrimary));
const days = computed(() => (props.header.primaryDate ? daysUntil(props.header.primaryDate) : Number.NaN));

const metrics = computed<BandMetric[]>(() => [
    {
        label: t("project.primary"),
        value: props.header.primaryDate ? (dayAndMonth(props.header.primaryDate) ?? null) : null,
        sub: [
            Number.isNaN(days.value) ? null : days.value >= 0 ? t("project.daysUntil", days.value) : t("project.daysAgo", -days.value),
            primary.value?.venue,
        ].filter(Boolean).join(" · "),
    },
    {
        label: t("lead.quote"),
        value: props.quote ? euro(props.quote.net) : null,
        sub: props.quote ? t("project.netStatus", { status: props.quote.statusLabel.toLowerCase() }) : undefined,
    },
    {
        label: t("project.paid"),
        value: plan.value ? euro(paid.value) : null,
        sub: plan.value ? t("project.paidOf", { total: euro(plan.value.total) }) : undefined,
    },
    {
        label: t("project.openTasks"),
        value: props.tasks.length ? String(open.value.length) : null,
        unit: t("project.tasksOf", { all: props.tasks.length }),
        sub: overdue.value[0]?.dueOn ? t("project.overdueSince", { date: day(overdue.value[0].dueOn) }) : undefined,
        subTone: "late",
        subHref: "#aufgaben",
    },
]);

const hasBand = computed(() => bandMetrics(metrics.value).length > 0);

const bare = computed(() =>
    isBare({ shoots: props.shoots.length, quote: props.quote !== null, tasks: props.tasks.length, payments: installments.value.length }),
);

const tiles = computed(() => [
    { key: "shoot" as const, href: `${base.value}/shootings` },
    { key: "quote" as const, href: `/angebote/neu?projekt=${props.header.id}` },
    { key: "task" as const, href: `${base.value}/aufgaben` },
    { key: "form" as const, href: `${base.value}/frageboegen` },
    { key: "gallery" as const, href: galleriesHref.value ?? `${base.value}/galerien` },
].map((tile) => ({ ...tile, label: t(`project.tile.${tile.key}`) })));

const current = computed(() => props.shoots.findIndex((shoot) => chipClass(shoot, props.header.position.value) === "now"));
const timeline = computed(() => timelineWindow(props.shoots, current.value));

const STATE = {
    done: { text: "text-ok", dot: "bg-action", line: "bg-action" },
    now: { text: "font-semibold text-ring", dot: "bg-background shadow-[inset_0_0_0_3px_var(--accent)]", line: "bg-rule" },
    next: { text: "text-ink-2", dot: "bg-line-hover", line: "bg-rule" },
    off: { text: "text-faint", dot: "bg-line-hover", line: "bg-rule" },
};
const stateOf = (shoot: App.Crm.Data.ShootData) => STATE[chipClass(shoot, props.header.position.value)];

const taskRows = computed(() => (showDone.value ? done.value : next.value.rest));

const events = computed(() => groupEntries(props.thread.filter((entry) => entry.system).reverse()));
const byline = (author: string | null) =>
    page.props.soloStudio && author === page.props.auth.user?.name ? null : author;

const toneOf = { paid: "success", late: "danger", due: "warn", upcoming: "neutral" } as const;

const textEditing = ref(false);
const anfrage = useForm({ inquiry_text: props.inquiry?.text ?? "" });

function saveAnfrage() {
    anfrage
        .transform((data) => ({ ...data, inquiry_text: data.inquiry_text.trim() || null }))
        .patch(`/anfragen/${props.header.id}`, {
            preserveScroll: true,
            onSuccess: () => (textEditing.value = false),
        });
}

const kontakteEditing = ref(false);
const kontakte = useForm({ people: props.header.people.map((person) => ({ id: person.id, role: person.role })) });

function saveKontakte() {
    kontakte.patch(`/auftraege/${props.header.id}`, {
        preserveScroll: true,
        onSuccess: () => (kontakteEditing.value = false),
    });
}

const quoteItems = computed(() => props.quote?.items.map((item) => item.title).join(", ") ?? "");

const detailsEditing = ref(false);

const details = useForm({
    name: props.header.name,
    stage: props.stage,
    source: props.source,
    source_other: props.sourceOther ?? "",
    fields: { ...props.fieldValues },
});
function saveDetails() {
    details.patch(`/auftraege/${props.header.id}`, {
        preserveScroll: true,
        onSuccess: () => (detailsEditing.value = false),
    });
}
// Was eine Karte sagt, wenn sie zu ist.
const detailsSummary = computed(() =>
    props.fields
        .map((field) => [field, props.fieldValues[field.key]] as const)
        .filter(
            ([, value]) =>
                value !== null &&
                value !== undefined &&
                value !== "" &&
                !(Array.isArray(value) && !value.length),
        )
        .map(([field, value]) => {
            if (Array.isArray(value)) return value.join(", ");
            if (field.type === "boolean") return value ? "Ja" : "Nein";
            if (field.type === "date") return day(String(value));

            return String(value);
        })
        .join(" · "),
);

const notes = computed(() => props.thread.filter((entry) => !entry.system).reverse());

function complete(row: App.Crm.Data.TaskRowData) {
    router.post(`/aufgaben/${row.id}/erledigt`, {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout :active="header.isBooked ? 'projekte' : 'anfragen'">
        <ProjectShell :header="header" active="overview">
            <template #left>
                <ProjectQueue :groups="header.queue" @update:rows="reachable = $event" />
            </template>
            <template #actions>
                <PhaseButton :stages="stages" :current="stage" @select="movePhase" />
            </template>
            <template v-if="sendHref || createQuote || step || callHref || addShoot || addGallery || completeNow" #stage>
                <Button v-if="callHref" variant="outline" as-child class="max-[640px]:min-h-11">
                    <a :href="callHref">{{ $t("contacts.call") }}</a>
                </Button>
                <Button v-if="sendHref" variant="pill" as-child class="max-[640px]:min-h-11">
                    <Link :href="sendHref">{{ $t("lead.sendQuote") }}</Link>
                </Button>
                <Button v-else-if="createQuote" variant="pill" as-child class="max-[640px]:min-h-11">
                    <Link :href="`/angebote/neu?projekt=${header.id}`">{{ $t("quotes.create") }}</Link>
                </Button>
                <Button v-else-if="addGallery" variant="pill" as-child class="max-[640px]:min-h-11">
                    <Link :href="galleriesHref!">{{ $t("galleries.create") }}</Link>
                </Button>
                <Button v-else-if="completeNow" variant="pill" type="button" class="max-[640px]:min-h-11" @click="completeProject">
                    {{ $t("project.completeMenu") }}
                </Button>
                <ProjectStep v-else :header="header" :shoots="shoots" />
            </template>

                    <Alert
                        v-if="stalled"
                        class="mt-6 flex items-center gap-4 border-0 bg-[var(--due-bg)] px-4 py-3.5"
                    >
                        <div class="min-w-0">
                            <AlertTitle class="text-ui font-semibold text-[var(--due)]">
                                {{ stalled.label }} {{ $t("projectQueue.days", stalled.days) }}
                            </AlertTitle>
                            <AlertDescription class="text-small text-ink-2">
                                {{ day(stalled.date) }} ·
                                {{ $t("project.stalledStatus", { stage: stalled.status, date: day(stalled.since) }) }}
                            </AlertDescription>
                        </div>
                        <div v-if="header.canManageShoots" class="ml-auto flex flex-none items-center gap-2">
                            <Button v-if="galleriesHref" variant="outline" as-child>
                                <Link :href="galleriesHref">{{ $t("galleries.create") }}</Link>
                            </Button>
                        </div>
                    </Alert>

            <div
                v-if="next.step || hasBand"
                class="mt-5 grid gap-4 max-[900px]:grid-cols-1"
                :class="next.step && hasBand ? 'grid-cols-[minmax(0,5fr)_minmax(0,7fr)]' : 'grid-cols-1'"
            >
                <NextStepCard
                    v-if="next.step"
                    :title="next.step.title"
                    :meta="[next.step.audienceLabel, next.step.shootLabel].filter(Boolean).join(' · ')"
                    :late="next.step.dueOn ? $t('project.overdueSince', { date: day(next.step.dueOn) }) : null"
                    :action="$t('project.markDone')"
                    @done="complete(next.step)"
                />
                <MetricBand :metrics="metrics" :columns="next.step ? 2 : bandMetrics(metrics).length" />
            </div>

            <EmptyTiles v-if="bare" class="mt-4" :tiles="tiles" />
            <Panel v-else class="mt-4" :title="$t('lead.dates')" :count="shoots.length > 4 ? shoots.length : null">
                <template #action>
                    <div class="ml-auto flex items-center gap-3">
                        <Link
                            v-if="shoots.length > 4"
                            class="inline-flex min-h-10 items-center text-ui font-medium text-ring no-underline hover:underline"
                            :href="`${base}/shootings`"
                        >{{ $t("project.showAll") }}</Link>
                        <Button v-if="header.canManageShoots" variant="pill-muted" class="min-h-10 px-4" as-child>
                            <Link :href="`${base}/shootings`">+ {{ $t("project.shootAdd") }}</Link>
                        </Button>
                    </div>
                </template>

                <div v-if="shoots.length" class="mt-3 grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(190px,1fr))]">
                    <div v-for="shoot in timeline" :key="shoot.id" class="group relative min-w-0 pt-6">
                        <span class="absolute top-[6px] left-0 -right-4 h-0.5 group-last:hidden" :class="stateOf(shoot).line" />
                        <span class="absolute top-0 left-0 size-3.5 rounded-full" :class="stateOf(shoot).dot" />
                        <span class="block text-small" :class="stateOf(shoot).text">{{ shoot.statusLabel }}</span>
                        <b class="mt-1 block font-semibold">{{ day(shoot.date) }}</b>
                        <div>{{ shoot.label ?? shoot.kind }}</div>
                        <div v-if="shoot.venue" class="text-small text-ink-2">{{ shoot.venue }}</div>
                        <div v-if="!$page.props.soloStudio && shoot.crew.length" class="text-small text-ink-2">
                            {{ shoot.crew.map((member) => member.name).join(", ") }}
                        </div>
                        <ShootAdvance v-if="header.canManageShoots" class="mt-3" :shoot="shoot" />
                    </div>
                </div>
            </Panel>

            <div class="mt-4 grid grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] items-start gap-4 max-[900px]:grid-cols-1">
                <div class="flex flex-col gap-4 empty:hidden">
                    <Panel v-if="!bare" id="aufgaben" :title="$t('project.tasks')">
                        <template #action>
                            <div class="ml-auto">
                                <TaskCreateDialog
                                    v-if="header.canManageTasks"
                                    :project-id="header.id"
                                    :shoot-options="shootOptions"
                                    :staff-options="staffOptions"
                                    :anchors="anchors ?? []"
                                />
                            </div>
                        </template>

                        <span class="mb-2 inline-flex gap-0.5 rounded-lg bg-sunk p-[3px]">
                            <button
                                class="min-h-10 cursor-pointer rounded-[var(--r-sm)] px-3 text-small leading-none"
                                :class="showDone ? 'text-ink-2' : 'bg-paper font-semibold'"
                                type="button"
                                @click="showDone = false"
                            >
                                {{ $t("project.tasksOpen", { count: open.length }) }}
                            </button>
                            <button
                                class="min-h-10 cursor-pointer rounded-[var(--r-sm)] px-3 text-small leading-none"
                                :class="showDone ? 'bg-paper font-semibold' : 'text-ink-2'"
                                type="button"
                                @click="showDone = true"
                            >
                                {{ $t("project.tasksDone", { count: done.length }) }}
                            </button>
                        </span>

                        <CappedList
                            v-slot="{ item: row }"
                            :items="taskRows"
                            :limit="3"
                            :more-href="`${base}/aufgaben`"
                            :total="showDone ? done.length : open.length"
                        >
                            <div class="flex min-h-11 items-center gap-3 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none">
                                <span
                                    v-if="row.isDone"
                                    class="grid size-6 flex-none place-items-center rounded-full bg-ok-bg text-ok"
                                >
                                    <svg class="size-3 fill-none stroke-current [stroke-linecap:round] [stroke-linejoin:round] [stroke-width:3]" viewBox="0 0 24 24">
                                        <path d="M4 12l5 5L20 6" />
                                    </svg>
                                </span>
                                <button
                                    v-else
                                    class="-mx-2 grid size-10 flex-none cursor-pointer place-items-center"
                                    type="button"
                                    :aria-label="$t('project.taskComplete')"
                                    @click="complete(row)"
                                >
                                    <span class="size-6 rounded-full border-[1.5px] border-line-hover hover:border-ring hover:bg-[var(--accent-bg)]" />
                                </button>
                                <span class="min-w-0 flex-1 truncate" :class="row.isDone && 'text-ink-2'">{{ row.title }}</span>
                                <span
                                    class="text-small tabular-nums"
                                    :class="row.isOverdue && !row.isDone ? 'text-destructive' : 'text-ink-2'"
                                >{{ row.dueOn ? day(row.dueOn) : "" }}</span>
                            </div>
                        </CappedList>
                    </Panel>

                    <Panel v-if="installments.length && plan" :title="$t('dashboard.payments')">
                        <template v-if="header.canManagePaymentPlan && installments.some((row) => !row.invoiceNumber)" #action>
                            <Button variant="pill-muted" class="ml-auto min-h-10 px-4" as-child>
                                <Link :href="`/rechnungen/neu?projekt=${header.id}`">{{ $t("project.invoiceAdd") }}</Link>
                            </Button>
                        </template>
                        <div
                            class="mb-3 h-2 overflow-hidden rounded-full bg-rule"
                            role="img"
                            :aria-label="`${paidShare} %`"
                        >
                            <i class="block h-full rounded-full bg-action" :style="{ width: `${paidShare}%` }" />
                        </div>
                        <div
                            v-for="row in installments"
                            :key="row.id"
                            class="grid min-h-16 grid-cols-[1fr_auto_auto] items-center gap-3 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
                        >
                            <div>
                                <b class="font-semibold">{{ day(row.dueOn) }}</b>
                                <div class="text-small text-ink-2">{{ euro(row.amount) }}</div>
                            </div>
                            <Chip :tone="toneOf[installmentChipClass(row)]">{{ row.statusLabel }}</Chip>
                            <InstallmentPaidDialog
                                v-if="header.canManagePaymentPlan && row === nextInstallment && !plan.settledOn"
                                :installment-id="row.id"
                                :remaining="row.remaining"
                                trigger-class="min-h-10"
                            />
                            <span v-else />
                        </div>
                    </Panel>

                    <Panel v-if="events.length" :title="$t('project.history')">
                        <CappedList
                            v-slot="{ item: entry, index }"
                            :items="events"
                            :limit="5"
                            :more-href="`${base}/verlauf`"
                        >
                            <div class="py-1.5" :class="index === 0 && 'text-foreground'">
                                <b class="block font-semibold">{{ entry.count > 1 ? $t("project.historyCount", { body: entry.body, count: entry.count }) : entry.body }}</b>
                                <span class="text-small text-ink-2">
                                    {{ [entry.at ? day(entry.at) : null, byline(entry.author)].filter(Boolean).join(" · ") }}
                                </span>
                            </div>
                        </CappedList>
                    </Panel>
                </div>

                <div class="flex flex-col gap-4">
                    <Panel v-if="!bare" :title="$t('lead.quote')">
                        <template #action>
                            <div class="flex flex-1 items-center gap-2">
                                <template v-if="quote">
                                    <span v-if="quote.number" class="text-small text-ink-2">{{ quote.number }}</span>
                                    <Chip :tone="quoteTone(quote.status)">{{ quote.statusLabel }}</Chip>
                                    <Link
                                        class="ml-auto inline-flex min-h-10 items-center text-ui font-medium text-ring no-underline hover:underline"
                                        :href="`/angebote/${quote.id}`"
                                    >{{ $t("project.view") }}</Link>
                                </template>
                                <Button v-else-if="header.canManageQuotes" variant="pill-muted" class="ml-auto min-h-10 px-4" as-child>
                                    <Link :href="`/angebote/neu?projekt=${header.id}`">{{ $t("lead.quote") }}</Link>
                                </Button>
                            </div>
                        </template>
                        <template v-if="quote">
                            <p class="m-0">{{ quoteItems }}</p>
                            <p class="mt-1 mb-0 text-small text-ink-2">
                                {{ [$t("project.netAmount", { amount: euro(quote.net) }), quote.validUntil ? $t("lead.validUntil", { date: day(quote.validUntil) }) : null].filter(Boolean).join(" · ") }}
                            </p>
                        </template>
                    </Panel>

                    <Panel :title="$t('project.client')">
                        <template v-if="header.canManageShoots && !kontakteEditing" #action>
                            <Button class="ml-auto min-h-10 px-4" variant="ghost" type="button" @click="kontakteEditing = true">
                                {{ $t("lead.edit") }}
                            </Button>
                        </template>

                        <template v-if="kontakteEditing">
                            <datalist id="rollen">
                                <option v-for="value in ROLE_SUGGESTIONS" :key="value" :value="value" />
                            </datalist>
                            <div
                                v-for="(person, index) in kontakte.people"
                                :key="person.id"
                                class="flex items-end gap-3 py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none max-[640px]:flex-wrap"
                            >
                                <span class="min-w-[148px] flex-1 pb-2.5 font-semibold">{{ header.people[index].name }}</span>
                                <Field v-slot="{ id }" dense class="w-[168px] flex-none" :label="$t('leads.role')">
                                    <Input :id="id" v-model="person.role" type="text" list="rollen" />
                                </Field>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <Button class="min-h-10" variant="pill" type="button" :disabled="kontakte.processing" @click="saveKontakte">{{ $t("common.save") }}</Button>
                                <Button class="min-h-10" variant="outline" type="button" @click="kontakte.reset(); kontakteEditing = false">{{ $t("common.cancel") }}</Button>
                            </div>
                        </template>

                        <template v-else>
                        <div
                            v-for="person in header.people"
                            :key="person.id"
                            class="flex min-h-12 flex-wrap items-center gap-x-3 gap-y-1 py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
                        >
                            <Link class="min-w-[200px] flex-1 font-semibold text-foreground no-underline hover:underline" :href="`/kontakte/${person.id}`">
                                {{ person.name }}
                            </Link>
                            <span class="rounded-full bg-sunk px-2.5 py-0.5 text-small">{{ roleLabel(person.role) }}</span>
                            <span v-if="person.email || person.phone" class="basis-full text-small">
                                <a v-if="person.email" class="text-ring no-underline hover:underline" :href="`mailto:${person.email}`">{{ person.email }}</a>
                                <template v-if="person.email && person.phone"> · </template>
                                <a v-if="person.phone" class="text-ring no-underline hover:underline" :href="`tel:${person.phone.replace(/ /g, '')}`">{{ person.phone }}</a>
                            </span>
                            <Button v-else variant="pill-muted" class="ml-auto min-h-10 px-4" as-child>
                                <Link :href="`/kontakte/${person.id}`">{{ $t("project.contactDetails") }}</Link>
                            </Button>
                        </div>
                        </template>

                        <div v-if="inquiry" class="mt-3 border-t border-rule pt-3">
                            <div class="mb-1 flex items-center gap-3">
                                <b class="font-semibold">{{ $t("project.lead") }}</b>
                                <Button
                                    v-if="header.canManageShoots && inquiry.text"
                                    class="ml-auto min-h-10 px-4"
                                    variant="ghost"
                                    type="button"
                                    @click="textEditing = !textEditing"
                                >{{ $t("lead.edit") }}</Button>
                            </div>
                            <template v-if="inquiry.text && !textEditing">
                                <p class="m-0 max-w-[68ch] whitespace-pre-line">{{ inquiry.text }}</p>
                                <p class="mt-2 mb-0 text-small text-ink-2">
                                    {{ [inquiry.typeLabel, inquiry.sourceLabel].filter(Boolean).join(" · ") }}
                                </p>
                            </template>
                            <Composer
                                v-else
                                v-model="anfrage.inquiry_text"
                                :placeholder="$t('leads.inquiryText')"
                                :label="$t('leads.inquiryText')"
                                :send-label="$t('project.send')"
                                :busy="anfrage.processing"
                                :allow-empty="textEditing"
                                @send="saveAnfrage"
                            />
                            <p v-if="anfrage.errors.inquiry_text" class="mt-1.5 mb-0 text-small text-destructive">{{ anfrage.errors.inquiry_text }}</p>
                        </div>
                    </Panel>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3">
                    <Card
                        v-model:editing="detailsEditing"
                        :title="$t('project.details')"
                        :summary="detailsSummary"
                        editable
                        @cancel="details.reset()"
                        @save="saveDetails"
                    >
                        <ProjectFields
                            v-model="details.fields"
                            :editing="false"
                            :errors="details.errors"
                            :fields="fields"
                        />

                        <template #edit>
                            <div class="flex flex-wrap gap-3">
                                <Field v-slot="{ id }" dense class="min-w-[220px] flex-1" :label="$t('leads.name')" :error="details.errors.name">
                                    <Input :id="id" v-model="details.name" type="text" />
                                </Field>

                                <Field v-if="stage" dense class="w-[220px] flex-none" :label="$t('project.position')">
                                    <Select v-model="details.stage">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="option in stages"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field dense class="w-[220px] flex-none" :label="$t('leads.source')">
                                    <Select v-model="details.source">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="option in sources"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        v-if="details.source === 'other'"
                                        v-model="details.source_other"
                                        class="mt-1.5"
                                        type="text"
                                        list="source-presets"
                                    />
                                    <datalist id="source-presets">
                                        <option v-for="value in presets.source" :key="value" :value="value" />
                                    </datalist>
                                </Field>
                            </div>

                            <ProjectFields
                                v-model="details.fields"
                                class="mt-3"
                                editing
                                :errors="details.errors"
                                :fields="fields"
                            />
                        </template>
                    </Card>
            </div>

                    <!-- The scope as it stands, not a copy of the quote. Nothing is
                         locked: the order changes in conversation and the document lags
                         behind. Forbid the change and the scope is simply wrong while the
                         work happens anyway. -->
                    <Section v-if="scope.items.length && (!quote || scope.delta || scope.items.some((item) => item.state))" :title="$t('project.scope')">
                        <LineItems
                            class="[&>div]:py-[9px] [&>div]:shadow-none"
                            :items="scope.items"
                        >
                            <template #note="{ item }">
                                <span v-if="item.state" class="ml-[3px] text-small text-ring">{{
                                    $t(`project.scope_${item.state}`)
                                }}</span>
                            </template>
                            <template #priceNote="{ item }">
                                <span
                                    v-if="item.state === 'changed'"
                                    class="mt-0.5 block text-small font-normal text-ring"
                                >{{ euro(item.wasUnitPrice) }}</span>
                            </template>
                        </LineItems>

                        <p
                            v-if="scope.delta"
                            class="mt-[7px] text-small text-ink-2 [&_em]:not-italic [&_em]:text-ring"
                        >
                            <em>{{
                                $t("project.scopeDelta", { amount: euro(Math.abs(scope.delta)) })
                            }}</em>
                            <Link
                                class="ml-3 text-small tracking-[-0.01em] whitespace-nowrap text-ring no-underline hover:underline"
                                :href="`/auftraege/${header.id}/angebote`"
                            >
                                {{ $t("project.sendAddendum") }}
                            </Link>
                        </p>
                    </Section>

                    <Panel class="mt-4" :title="$t('lead.notes')" :count="notes.length > 3 ? notes.length : null">
                        <NoteThread
                            compact
                            :entries="notes"
                            :action="`${base}/notizen`"
                            :limit="3"
                            :more-href="`${base}/notizen`"
                            :can-manage="header.canManageNotes"
                        />
                    </Panel>
        </ProjectShell>
    </AdminLayout>
</template>
