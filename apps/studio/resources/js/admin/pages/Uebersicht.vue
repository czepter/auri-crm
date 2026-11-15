<script setup lang="ts">
import { computed } from "vue";
import { Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import Page from "@/ui/page/Page.vue";
import PageHead from "@/ui/page/PageHead.vue";
import Panel from "@/admin/components/crm/Panel.vue";
import { Button } from "@/ui/button";
import { Checkbox } from "@/ui/checkbox";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/ui/dropdown-menu";
import { formatEurosRounded } from "@/lib/money";
import { groupEntries } from "@/admin/lib/groupEntries";
import { NOTICEABLE_DAYS, since } from "@/admin/lib/leadQueue";
import { useLiveProps } from "@/admin/lib/liveReload";

type Entry = {
    date: string;
    time: string | null;
    what: string;
    where: string | null;
    away: boolean;
    href: string | null;
};

type Activity = { text: string; href: string; when: string; fresh: boolean };

const props = defineProps<{
    today: string;
    range: "30" | "90" | "year";
    entries: Entry[];
    pipeline: { total: number; priorYear: number | null };
    payments: {
        open: number;
        overdue: number;
        overdueInstalments: number;
        instalments: number;
        overdueSinceDays: number | null;
        nextDueOn: string | null;
        nextAmount: number | null;
    };
    quotes: { sent: number; accepted: number; priorSent: number | null; priorAccepted: number | null };
    shoots: { count: number; priorYear: number | null };
    activity: Activity[];
    tasks: App.Crm.Data.TaskRowData[];
    openTasks: number;
    todo: App.Crm.Data.DomainRowData[];
    dunning: App.Crm.Data.DomainRowData[];
    leads: { key: string; rows: App.Crm.Data.LeadQueueRowData[] }[];
    projects: { key: string; rows: App.Crm.Data.CaseQueueRowData[] }[];
    galleries: App.Crm.Data.DomainRowData[];
}>();
useLiveProps(["today", "entries", "pipeline", "payments", "quotes", "shoots", "activity", "tasks", "openTasks", "todo", "dunning", "leads", "projects", "galleries"]);

const { t } = useI18n();

const heading = computed(() =>
    new Date(props.today).toLocaleDateString("de-DE", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
    }),
);

// Das Jahr steht nur, wo es nicht das laufende ist.
function dayOf(date: string) {
    const other = date.slice(0, 4) !== props.today.slice(0, 4);
    return new Date(date).toLocaleDateString("de-DE", { day: "2-digit", month: "2-digit", ...(other && { year: "numeric" as const }) });
}

const activityGroups = computed(() =>
    groupEntries(props.activity.map((entry) => ({ ...entry, body: entry.text, author: null, at: null }))),
);

const SHOWN = 5;

const upcoming = computed(() =>
    props.entries.slice(0, SHOWN).map((entry) => {
        const day = new Date(`${entry.date}T12:00:00`);

        return {
            ...entry,
            num: day.getDate(),
            month: day.toLocaleDateString("de-DE", { month: "short" }),
        };
    }),
);

// Verglichen wird mit demselben Fenster ein Jahr davor, nicht mit dem davor:
// das Geschäft ist saisonal, Oktober gegen September zeigte nur den Herbst.
function trend(now: number, prior: number | null, format: (value: number) => string) {
    if (prior === null || now === prior) {
        return { trend: null, delta: null };
    }

    return { trend: now > prior ? "up" : "down", delta: format(Math.abs(now - prior)) };
}

// Überfällig trägt den Betrag nur, wenn etwas überfällig ist; sonst zeigt die
// Kachel das Geld, das läuft, mit dem nächsten Fälligkeitstag als Beleg.
const figures = computed(() => {
    const { payments, pipeline, quotes, shoots } = props;
    const late = payments.overdue > 0;

    return [
        {
            key: "pipeline",
            label: t("dashboard.pipeline"),
            value: formatEurosRounded(pipeline.total),
            unit: null,
            warn: false,
            ...trend(pipeline.total, pipeline.priorYear, formatEurosRounded),
            note: pipeline.priorYear === null ? null : t("dashboard.priorYear", { value: formatEurosRounded(pipeline.priorYear) }),
        },
        {
            key: "payments",
            label: late ? t("dashboard.overdue") : t("dashboard.open"),
            value: formatEurosRounded(late ? payments.overdue : payments.open),
            unit: null,
            warn: late,
            trend: null,
            delta: null,
            note: late
                ? t("dashboard.overdueNote", { open: payments.overdueInstalments, all: payments.instalments, days: payments.overdueSinceDays ?? 0 })
                : payments.nextDueOn && payments.nextAmount !== null
                  ? t("dashboard.nextInstalment", { date: dayOf(payments.nextDueOn), amount: formatEurosRounded(payments.nextAmount) })
                  : null,
        },
        {
            key: "quotes",
            label: t("dashboard.acceptance"),
            value: String(quotes.accepted),
            unit: quotes.sent ? t("dashboard.ofSent", { count: quotes.sent }) : null,
            warn: false,
            ...trend(quotes.accepted, quotes.priorSent === null ? null : (quotes.priorAccepted ?? 0), String),
            note:
                quotes.priorSent === null
                    ? null
                    : t("dashboard.priorYear", { value: `${quotes.priorAccepted ?? 0} ${t("dashboard.ofSent", { count: quotes.priorSent })}` }),
        },
        {
            key: "shoots",
            label: t("dashboard.shoots"),
            value: String(shoots.count),
            unit: shoots.count ? t(PAST[props.range]) : null,
            warn: false,
            ...trend(shoots.count, shoots.priorYear, String),
            note: shoots.priorYear === null ? null : t("dashboard.priorYear", { value: shoots.priorYear }),
        },
    ];
});

const hero = computed(() => figures.value[0]);
const acceptance = computed(() => figures.value[2]);
const minor = computed(() => [figures.value[1], figures.value[3]]);
const acceptedShare = computed(() => (props.quotes.sent ? Math.min(100, (props.quotes.accepted / props.quotes.sent) * 100) : 0));

// Day one has nothing to measure: four tiles reading 0 say nothing a new studio does not know.
const measured = computed(() => {
    const { payments, pipeline, quotes, shoots } = props;

    return props.range !== "year" || pipeline.total > 0 || payments.open > 0 || payments.overdue > 0 || quotes.sent > 0 || shoots.count > 0
        || pipeline.priorYear !== null || quotes.priorSent !== null || shoots.priorYear !== null;
});

const PAST = { "30": "dashboard.range30Past", "90": "dashboard.range90Past", year: "dashboard.lastTwelveMonths" } as const;

type Row = {
    key: string;
    who: string;
    href: string;
    what: string;
    emphasis: string | null;
    whatTail: string | null;
    chip: { text: string; tone: "late" | "due" | "plain" } | null;
    action: App.Crm.Data.RowActionData | null;
    task: App.Crm.Data.TaskRowData | null;
};

function fromTask(task: App.Crm.Data.TaskRowData): Row {
    return {
        key: `task:${task.id}`,
        who: task.title,
        href: task.projectId ? `/auftraege/${task.projectId}` : task.contactId ? `/kontakte/${task.contactId}` : '/aufgaben',
        what: task.projectName ?? task.contactName ?? '',
        emphasis: null,
        whatTail: null,
        chip: task.dueOn ? { text: task.isOverdue ? t("dashboard.overdueSince", { date: dayOf(task.dueOn) }) : dayOf(task.dueOn), tone: task.isOverdue ? "late" : "plain" } : null,
        action: null,
        task,
    };
}

function fromRow(row: App.Crm.Data.DomainRowData): Row {
    return { key: `row:${row.projectId}:${row.what}`, who: row.who, href: `/auftraege/${row.projectId}`, what: row.what, emphasis: row.emphasis, whatTail: row.whatTail, chip: null, action: row.action, task: null };
}

// Wie im Queue-Pane: erst ab NOTICEABLE_DAYS wird das Warten zur Betonung.
function fromLead(group: string, lead: App.Crm.Data.LeadQueueRowData): Row {
    const long = lead.waitingDays >= NOTICEABLE_DAYS;

    return {
        key: `lead:${lead.id}`,
        who: lead.name,
        href: `/anfragen/${lead.id}`,
        what: t(`lead.group.${group}`),
        emphasis: null,
        whatTail: null,
        chip: { text: lead.waitingDays < 1 ? since(0) : t("dashboard.waitDays", lead.waitingDays), tone: lead.waitingDays >= 7 ? "late" : long ? "due" : "plain" },
        action: null,
        task: null,
    };
}

function fromProject(project: App.Crm.Data.CaseQueueRowData): Row {
    const day = project.date ? ` · ${dayOf(project.date)}` : null;

    return {
        key: `project:${project.id}`,
        who: project.name,
        href: `/auftraege/${project.id}`,
        what: project.reason ? t(`projectQueue.reason.${project.reason}`, { stage: project.stage }) : project.stage,
        emphasis: project.days === null ? null : t("projectQueue.days", project.days),
        whatTail: project.days === null ? day : null,
        chip: null,
        action: null,
        task: null,
    };
}

const leadRows = computed(() =>
    props.leads
        .flatMap((group) => group.rows.map((lead) => ({ lead, row: fromLead(group.key, lead) })))
        .sort((a, b) => b.lead.waitingDays - a.lead.waitingDays)
        .map(({ row }) => row),
);
const projectRows = computed(() => props.projects.flatMap((group) => group.rows.map(fromProject)));

// The longest-waiting inquiry gets the page's one dark action, but only once
// the wait is worth a nudge.
const urgent = computed(() => {
    const longest = props.leads
        .flatMap((group) => group.rows)
        .reduce<App.Crm.Data.LeadQueueRowData | null>((top, lead) => (top === null || lead.waitingDays > top.waitingDays ? lead : top), null);

    return longest !== null && longest.waitingDays >= NOTICEABLE_DAYS ? longest : null;
});

const cards = computed(() =>
    [
        { key: "leads", title: t("dashboard.leads"), count: leadRows.value.length, href: "/anfragen", link: t("dashboard.toBoard"), rows: leadRows.value, span: "xl:row-span-2" },
        { key: "projects", title: t("dashboard.projects"), count: projectRows.value.length, href: "/projekte", link: t("dashboard.allProjects"), rows: projectRows.value, span: "" },
    ]
        .filter((card) => card.rows.length > 0)
        .map((card) => ({ ...card, rows: card.rows.slice(0, SHOWN) })),
);

const dunning = computed(() => props.dunning.slice(0, SHOWN).map(fromRow));
const todo = computed(() => [...props.tasks.map(fromTask), ...props.todo.map(fromRow)].slice(0, SHOWN));

function complete(task: App.Crm.Data.TaskRowData) {
    router.post(`/aufgaben/${task.id}/erledigt`, {}, { preserveScroll: true });
}

const CREATE = [
    { label: "create.lead", href: "/anfragen/neu" },
    { label: "create.shoot", href: "/shootings/neu" },
    { label: "create.quote", href: "/angebote/neu" },
    { label: "create.questionnaire", href: "/frageboegen/neu" },
    { label: "create.gallery", href: "/galerien/neu" },
];

const ROW = "shadow-[inset_0_1px_0_var(--rule)] first:shadow-none";
const CHIP = {
    late: "bg-late-bg text-destructive",
    due: "bg-due-bg text-due",
    plain: "bg-sunk text-ink-2",
} as const;
</script>

<template>
    <AdminLayout active="uebersicht">
        <Page data-tokens wide>
            <div class="flex min-w-0 flex-1 flex-col">
                <PageHead :kicker="heading" :title="$t('dashboard.title')">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button class="ml-auto" type="button" :variant="urgent ? 'outline' : 'default'">
                                <svg class="size-3.5 fill-none stroke-current stroke-2 [stroke-linecap:round]" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                                {{ $t("dashboard.create") }}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem v-for="action in CREATE" :key="action.href" as-child>
                                <Link :href="action.href">{{ $t(action.label) }}</Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </PageHead>

                <div v-if="urgent" class="mx-6 mt-5 flex flex-wrap items-center gap-x-5 gap-y-3 rounded-[var(--r)] bg-foreground px-6 py-4 text-background">
                    <span class="text-small opacity-70">{{ $t("create.lead") }}</span>
                    <span class="min-w-0 flex-1 truncate text-[20px] leading-tight font-semibold tracking-[-0.02em]">{{ urgent.name }}</span>
                    <span class="rounded-[var(--r-sm)] bg-late-bg px-2.5 py-1 text-small font-medium text-destructive">
                        {{ $t("dashboard.waited", urgent.waitingDays) }}
                    </span>
                    <Button :as="Link" class="bg-background text-foreground" variant="default" :href="`/anfragen/${urgent.id}`">
                        {{ $t("dashboard.reply") }}
                    </Button>
                </div>

                <div class="grid grid-flow-dense grid-cols-1 items-stretch gap-4 px-6 pt-5 pb-8 min-[900px]:grid-cols-2 xl:grid-cols-3">
                    <Panel v-for="card in cards" :key="card.key" :class="card.span" :title="card.title" :count="card.count">
                        <template #action>
                            <Link class="ml-auto text-small text-ink-2 no-underline" :href="card.href">{{ card.link }}</Link>
                        </template>

                        <div v-for="row in card.rows" :key="row.key" class="flex items-center gap-3 py-2.5" :class="ROW">
                            <div class="min-w-0 flex-1">
                                <Link class="block truncate text-ui font-semibold tracking-[-0.015em] text-foreground no-underline" :href="row.href">
                                    {{ row.who }}
                                </Link>
                                <span class="block truncate text-small text-ink-2 [&_em]:text-ring [&_em]:not-italic">
                                    {{ row.what }} <em v-if="row.emphasis">{{ row.emphasis }}</em>
                                    <template v-if="row.whatTail">{{ row.whatTail }}</template>
                                </span>
                            </div>
                            <span v-if="row.chip" class="flex-none rounded-full px-2.5 py-1 text-small font-medium tabular-nums" :class="CHIP[row.chip.tone]">
                                {{ row.chip.text }}
                            </span>
                        </div>
                    </Panel>

                    <section v-if="measured" class="rounded-[var(--r)] bg-[var(--accent-bg)] px-6 py-5">
                        <div class="flex flex-col gap-1">
                            <div class="text-small font-medium text-ink-2">{{ hero.label }}</div>
                            <div class="text-[40px] leading-[1.1] font-semibold tracking-[-0.03em] tabular-nums">{{ hero.value }}</div>
                            <div v-if="hero.note || hero.delta" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-small tabular-nums text-ink-2">
                                <span
                                    v-if="hero.delta"
                                    class="rounded-[var(--r-sm)] px-1.5 py-px font-medium"
                                    :class="hero.trend === 'up' ? 'bg-ok-bg text-ok' : 'bg-late-bg text-destructive'"
                                >
                                    {{ hero.trend === "up" ? "↑" : "↓" }} {{ hero.delta }}
                                </span>
                                <span v-if="hero.note">{{ hero.note }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-1">
                            <div class="text-small font-medium text-ink-2">{{ acceptance.label }}</div>
                            <div class="flex items-baseline gap-1 text-[24px] leading-tight font-semibold tracking-[-0.02em] tabular-nums">
                                {{ acceptance.value }}<span v-if="acceptance.unit" class="text-small font-medium tracking-normal text-ink-2">{{ acceptance.unit }}</span>
                            </div>
                            <div v-if="quotes.sent" class="mt-1 h-2 overflow-hidden rounded-full bg-line-hover" aria-hidden="true">
                                <div class="h-full rounded-full bg-ring" :style="{ width: `${acceptedShare}%` }" />
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-4">
                            <div v-for="figure in minor" :key="figure.key" class="flex flex-col gap-1">
                                <div class="text-small font-medium text-ink-2">{{ figure.label }}</div>
                                <div class="text-[24px] leading-tight font-semibold tracking-[-0.02em] tabular-nums" :class="figure.warn && 'text-due'">
                                    {{ figure.value }}
                                </div>
                                <div v-if="figure.note" class="text-small tabular-nums text-ink-2">{{ figure.note }}</div>
                            </div>
                        </div>
                    </section>

                    <Panel v-if="dunning.length" :title="$t('dashboard.payments')" :count="dunning.length">
                        <div v-for="row in dunning" :key="row.key" class="flex items-center gap-3 py-2" :class="ROW">
                            <div class="min-w-0 flex-1">
                                <Link class="block truncate text-ui font-semibold tracking-[-0.015em] text-foreground no-underline" :href="row.href">
                                    {{ row.who }}
                                </Link>
                                <span class="block truncate text-small text-ink-2 [&_em]:text-ring [&_em]:not-italic">
                                    {{ row.what }} <em v-if="row.emphasis">{{ row.emphasis }}</em>
                                    <template v-if="row.whatTail">{{ row.whatTail }}</template>
                                </span>
                            </div>
                            <Button v-if="row.action" :as="Link" variant="secondary" :href="row.action.target">
                                {{ row.action.label }}
                            </Button>
                        </div>
                    </Panel>

                    <Panel v-if="galleries.length" :title="$t('dashboard.galleries')" :count="galleries.length">
                        <Link
                            v-for="row in galleries"
                            :key="`${row.projectId}${row.what}`"
                            class="block py-2 text-foreground no-underline"
                            :class="ROW"
                            :href="`/auftraege/${row.projectId}`"
                        >
                            <span class="block text-ui font-semibold tracking-[-0.015em]">{{ row.who }}</span>
                            <span class="block text-small text-ink-2">{{ row.what }}<template v-if="row.emphasis"> {{ row.emphasis }}</template><template v-if="row.whatTail">{{ row.whatTail }}</template></span>
                        </Link>
                    </Panel>

                    <Panel :title="$t('dashboard.upcoming')" :count="entries.length || null">
                        <template #action>
                            <Link class="ml-auto text-small tracking-[-0.01em] text-ring no-underline hover:underline" href="/kalender">
                                {{ $t("dashboard.calendar") }}
                            </Link>
                        </template>

                        <component
                            :is="entry.href ? Link : 'div'"
                            v-for="entry in upcoming"
                            :key="`${entry.date}${entry.time}${entry.what}`"
                            :href="entry.href"
                            class="grid grid-cols-[48px_1fr] items-center gap-3 py-2.5 text-foreground no-underline"
                            :class="ROW"
                        >
                            <span class="text-center leading-tight">
                                <span class="block text-[22px] font-semibold tracking-[-0.03em] tabular-nums">{{ entry.num }}</span>
                                <span class="block text-small text-ink-2">{{ entry.month }}</span>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-ui font-semibold tracking-[-0.015em]">
                                    {{ entry.away ? $t("dashboard.away", { name: entry.what }) : entry.what }}
                                </span>
                                <span v-if="entry.time || entry.where" class="block truncate text-small text-ink-2">
                                    <span v-if="entry.time" class="tabular-nums">{{ entry.time }}</span>
                                    <template v-if="entry.time && entry.where"> · </template>{{ entry.where }}
                                </span>
                            </span>
                        </component>
                        <Link
                            v-if="!upcoming.length"
                            class="flex h-14 items-center justify-center rounded-[var(--r-sm)] bg-sunk text-[18px] text-ink-2 no-underline hover:bg-sunk-2 hover:text-foreground"
                            href="/shootings/neu"
                            :aria-label="$t('create.shoot')"
                        >
                            +
                        </Link>
                    </Panel>

                    <Panel v-if="todo.length" class="xl:col-span-2 min-[900px]:col-span-2" :title="$t('dashboard.todo')" :count="openTasks + props.todo.length">
                        <template #action>
                            <Link class="ml-auto text-small text-ink-2 no-underline" href="/aufgaben">{{ $t("dashboard.allTasks") }}</Link>
                        </template>

                        <div v-for="row in todo" :key="row.key" class="flex items-center gap-3 py-2.5" :class="ROW">
                            <Checkbox
                                v-if="row.task"
                                :model-value="false"
                                :aria-label="$t('dashboard.done')"
                                @update:model-value="complete(row.task)"
                            />
                            <div class="min-w-0 flex-1">
                                <Link class="block truncate text-ui font-semibold tracking-[-0.015em] text-foreground no-underline" :href="row.href">
                                    {{ row.who }}
                                </Link>
                                <span class="block truncate text-small text-ink-2 [&_em]:text-ring [&_em]:not-italic">
                                    {{ row.what }} <em v-if="row.emphasis">{{ row.emphasis }}</em>
                                    <template v-if="row.whatTail">{{ row.whatTail }}</template>
                                </span>
                            </div>
                            <span v-if="row.chip" class="flex-none rounded-full px-2.5 py-1 text-small font-medium tabular-nums" :class="CHIP[row.chip.tone]">
                                {{ row.chip.text }}
                            </span>
                            <Button v-if="row.action" :as="Link" variant="secondary" :href="row.action.target">
                                {{ row.action.label }}
                            </Button>
                        </div>
                    </Panel>

                    <Panel v-if="activity.length" :title="$t('dashboard.activity')">
                        <Link
                            v-for="entry in activityGroups"
                            :key="`${entry.when}${entry.text}`"
                            :href="entry.href"
                            class="flex items-baseline justify-between gap-3 py-2.5 text-foreground no-underline hover:text-ring"
                            :class="ROW"
                        >
                            <span class="flex min-w-0 gap-2.5 text-small leading-[1.4]">
                                <i class="mt-[5px] size-[7px] flex-none rounded-full" :class="entry.fresh ? 'bg-ring' : 'bg-mark'" />
                                {{ entry.count > 1 ? $t("project.historyCount", { body: entry.text, count: entry.count }) : entry.text }}
                            </span>
                            <span class="flex-none text-small whitespace-nowrap text-faint">{{ entry.when }}</span>
                        </Link>
                    </Panel>
                </div>
            </div>
        </Page>
    </AdminLayout>
</template>
