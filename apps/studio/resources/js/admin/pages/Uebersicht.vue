<script setup lang="ts">
import { computed } from "vue";
import { Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import Page from "@/ui/page/Page.vue";
import PageHead from "@/ui/page/PageHead.vue";
import Panel from "@/admin/components/Panel.vue";
import { Button } from "@/ui/button";
import { formatEurosRounded } from "@/admin/lib/money";

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
    quotesOut: App.Crm.Data.DomainRowData[];
    galleries: App.Crm.Data.DomainRowData[];
}>();

const { t } = useI18n();

const heading = computed(() =>
    new Date(props.today).toLocaleDateString("de-DE", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
    }),
);

const RANGES = [
    { key: "30", label: "dashboard.range30" },
    { key: "90", label: "dashboard.range90" },
    { key: "year", label: "dashboard.rangeYear" },
];

function dayOf(date: string) {
    return new Date(date).toLocaleDateString("de-DE", { day: "2-digit", month: "2-digit" });
}

const days = computed(() => {
    const groups: { date: string; label: string; entries: Entry[] }[] = [];

    for (const entry of props.entries) {
        const last = groups[groups.length - 1];

        if (last?.date === entry.date) {
            last.entries.push(entry);
        } else {
            groups.push({
                date: entry.date,
                label: new Date(entry.date).toLocaleDateString("de-DE", { weekday: "long", day: "numeric", month: "long" }),
                entries: [entry],
            });
        }
    }

    return groups;
});

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
            note: pipeline.priorYear === null ? null : t("dashboard.priorYear", { value: formatEurosRounded(pipeline.priorYear) }),
        },
        {
            key: "payments",
            label: late ? t("dashboard.overdue") : t("dashboard.open"),
            value: formatEurosRounded(late ? payments.overdue : payments.open),
            unit: null,
            warn: late,
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
            note: shoots.priorYear === null ? null : t("dashboard.priorYear", { value: shoots.priorYear }),
        },
    ];
});

const PAST = { "30": "dashboard.range30Past", "90": "dashboard.range90Past", year: "dashboard.lastTwelveMonths" } as const;

type FooterRow = {
    key: string;
    who: string;
    href: string;
    what: string;
    emphasis: string | null;
    whatTail: string | null;
    action: App.Crm.Data.RowActionData | null;
    task: App.Crm.Data.TaskRowData | null;
};

function fromTask(task: App.Crm.Data.TaskRowData): FooterRow {
    return {
        key: `task:${task.id}`,
        who: task.projectName ?? task.contactName ?? '',
        href: task.projectId ? `/auftraege/${task.projectId}` : task.contactId ? `/kontakte/${task.contactId}` : '/aufgaben',
        what: task.title,
        emphasis: task.isOverdue && task.dueOn ? dayOf(task.dueOn) : null,
        whatTail: !task.isOverdue && task.dueOn ? ` · ${dayOf(task.dueOn)}` : null,
        action: null,
        task,
    };
}

function fromRow(row: App.Crm.Data.DomainRowData): FooterRow {
    return { key: `row:${row.projectId}:${row.what}`, who: row.who, href: `/auftraege/${row.projectId}`, what: row.what, emphasis: row.emphasis, whatTail: row.whatTail, action: row.action, task: null };
}

// Unten links steht, was heute zu tun ist; ist nichts zu tun, die Angebote,
// auf die ein Kunde antworten muss.
const owes = computed(() => props.tasks.length > 0 || props.todo.length > 0);
const footerRows = computed(() =>
    owes.value ? [...props.tasks.map(fromTask), ...props.todo.map(fromRow)].slice(0, 4) : props.quotesOut.slice(0, 4).map(fromRow),
);

function complete(task: App.Crm.Data.TaskRowData) {
    router.post(`/aufgaben/${task.id}/erledigt`, {}, { preserveScroll: true });
}

const PRIMARY = [
    { label: "create.lead", href: "/anfragen/neu", icon: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 8v8M13 8v5M18 8v10"/>' },
    { label: "create.shoot", href: "/shootings/neu", icon: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>' },
    { label: "create.quote", href: "/angebote/neu", icon: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>' },
];

const SECONDARY = [
    { label: "create.questionnaire", href: "/frageboegen/neu" },
    { label: "create.gallery", href: "/galerien/neu" },
];

const ROW = "shadow-[inset_0_1px_0_var(--rule)] first:shadow-none";
</script>

<template>
    <AdminLayout active="uebersicht">
        <Page data-tokens wide>
            <div class="flex min-w-0 flex-1 flex-col">
                <PageHead :kicker="heading" :title="$t('dashboard.title')" />

                <div class="flex gap-1.5 px-8 pt-3.5">
                    <Link
                        v-for="option in RANGES"
                        :key="option.key"
                        :href="`/?zeitraum=${option.key}`"
                        class="rounded-[var(--r-pill)] px-3.5 py-[5px] text-small font-medium tracking-[-0.01em] no-underline"
                        :class="range === option.key ? 'bg-foreground font-semibold text-background' : 'text-ink-2 shadow-[inset_0_0_0_1px_var(--line-hover)]'"
                    >
                        {{ $t(option.label) }}
                    </Link>
                </div>

                <div class="max-w-[1180px] columns-3 gap-5 px-8 pt-5 pb-8">
                    <Panel class="mb-5 break-inside-avoid" :title="$t('dashboard.upcoming')" :count="entries.length || null">
                        <template #action>
                            <Link class="ml-auto text-small tracking-[-0.01em] text-action no-underline hover:underline" href="/kalender">
                                {{ $t("dashboard.calendar") }}
                            </Link>
                        </template>

                        <template v-if="entries.length">
                            <div v-for="day in days" :key="day.date" class="pt-3 first:pt-0">
                                <div class="text-small tracking-[0.06em] uppercase text-ink-2">{{ day.label }}</div>
                                <div v-for="entry in day.entries" :key="`${entry.time}${entry.what}`" class="flex items-baseline gap-2.5 py-[7px]" :class="ROW">
                                    <span class="w-[46px] flex-none text-small font-semibold tabular-nums">{{ entry.time ?? "—" }}</span>
                                    <component
                                        :is="entry.href ? Link : 'span'"
                                        :href="entry.href"
                                        class="text-small tracking-[-0.01em] no-underline"
                                        :class="entry.away ? 'font-normal text-ink-2' : 'font-medium text-foreground'"
                                    >
                                        {{ entry.away ? $t("dashboard.away", { name: entry.what }) : entry.what }}
                                    </component>
                                    <span v-if="entry.where" class="ml-auto text-small whitespace-nowrap text-ink-2">{{ entry.where }}</span>
                                </div>
                            </div>
                            <Link class="block pt-3 text-small font-medium text-action no-underline" href="/kalender">{{ $t("dashboard.allUpcoming") }}</Link>
                        </template>

                        <div v-else class="flex flex-col items-center justify-center gap-3.5 py-8">
                            <svg class="size-10 fill-none stroke-faint stroke-[1.4] [stroke-linecap:round] [stroke-linejoin:round]" viewBox="0 0 24 24">
                                <rect x="3" y="5" width="18" height="16" rx="2" />
                                <path d="M8 3v4M16 3v4M3 10h18" />
                            </svg>
                            <Button :as="Link" variant="pill" href="/shootings/neu">+ {{ $t("create.shoot") }}</Button>
                        </div>
                    </Panel>

                    <Panel class="mb-5 break-inside-avoid" :title="$t('dashboard.figures')">
                        <template #action>
                            <Link class="ml-auto text-small tracking-[-0.01em] text-action no-underline hover:underline" href="/auswertungen">
                                {{ $t("dashboard.reports") }}
                            </Link>
                        </template>

                        <div v-for="figure in figures" :key="figure.key" class="py-[11px] first:pt-0" :class="ROW">
                            <div class="text-small tracking-[0.07em] uppercase text-ink-2">{{ figure.label }}</div>
                            <div
                                class="mt-[3px] flex items-baseline gap-1 text-[21px] leading-none font-semibold tracking-[-0.03em] tabular-nums"
                                :class="figure.warn && 'text-action'"
                            >
                                {{ figure.value }}<span v-if="figure.unit" class="text-[0.56em] font-medium text-ink-2">{{ figure.unit }}</span>
                            </div>
                            <div v-if="figure.note" class="mt-[3px] text-small tabular-nums text-faint">{{ figure.note }}</div>
                        </div>
                    </Panel>

                    <Panel class="mb-5 break-inside-avoid" :title="$t('dashboard.create')">
                        <div class="grid gap-1.5">
                            <Link
                                v-for="action in PRIMARY"
                                :key="action.href"
                                :href="action.href"
                                class="flex items-center gap-2.5 rounded-xl bg-paper px-3.5 py-3 text-ui font-medium tracking-[-0.01em] text-foreground no-underline hover:bg-sunk-2"
                            >
                                <svg
                                    class="size-[17px] flex-none fill-none stroke-ink-2 stroke-[1.6] [stroke-linecap:round] [stroke-linejoin:round]"
                                    viewBox="0 0 24 24"
                                    v-html="action.icon"
                                />
                                <span class="text-action">+</span>{{ $t(action.label) }}
                            </Link>
                        </div>
                        <div class="mt-2 flex gap-1.5">
                            <Button v-for="action in SECONDARY" :key="action.href" :as="Link" variant="pill-muted" class="flex-1 justify-center" :href="action.href">
                                + {{ $t(action.label) }}
                            </Button>
                        </div>
                    </Panel>

                    <Panel class="mb-5 break-inside-avoid" :title="$t('dashboard.activity')">
                        <Link
                            v-for="entry in activity"
                            :key="`${entry.when}${entry.text}`"
                            :href="entry.href"
                            class="flex gap-2.5 py-[9px] text-foreground no-underline hover:text-action"
                            :class="ROW"
                        >
                            <i class="mt-[5px] size-[7px] flex-none rounded-full" :class="entry.fresh ? 'bg-action' : 'bg-mark'" />
                            <div class="text-small leading-[1.4]">
                                {{ entry.text }}
                                <span class="mt-0.5 block text-small text-faint">{{ entry.when }}</span>
                            </div>
                        </Link>
                    </Panel>

                    <Panel
                        v-if="footerRows.length"
                        class="mb-5 break-inside-avoid"
                        :title="owes ? $t('dashboard.todo') : $t('dashboard.quotesOut')"
                        :count="owes ? openTasks + todo.length : quotesOut.length"
                    >
                        <template v-if="owes" #action>
                            <Link class="ml-auto text-small text-ink-2 no-underline" href="/aufgaben">{{ $t("dashboard.allTasks") }}</Link>
                        </template>

                        <div v-for="(row, index) in footerRows" :key="row.key" class="flex items-center gap-3 py-2" :class="ROW">
                            <div class="min-w-0 flex-1">
                                <Link class="block truncate text-ui font-semibold tracking-[-0.015em] text-foreground no-underline" :href="row.href">
                                    {{ row.who }}
                                </Link>
                                <span class="block truncate text-small text-ink-2 [&_em]:text-action [&_em]:not-italic">
                                    {{ row.what }} <em v-if="row.emphasis">{{ row.emphasis }}</em>
                                    <template v-if="row.whatTail">{{ row.whatTail }}</template>
                                </span>
                            </div>
                            <Button v-if="row.action" :as="Link" :variant="index === 0 ? 'pill' : 'pill-muted'" :href="row.action.target">
                                {{ row.action.label }}
                            </Button>
                            <Button v-else-if="row.task" type="button" :variant="index === 0 ? 'pill' : 'pill-muted'" @click="complete(row.task)">
                                {{ $t("dashboard.done") }}
                            </Button>
                        </div>
                    </Panel>

                    <Panel v-if="galleries.length" class="mb-5 break-inside-avoid" :title="$t('dashboard.galleries')" :count="galleries.length">
                        <Link
                            v-for="row in galleries"
                            :key="`${row.projectId}${row.what}`"
                            class="block py-2 text-foreground no-underline"
                            :class="ROW"
                            :href="`/auftraege/${row.projectId}`"
                        >
                            <span class="block text-small font-semibold tracking-[-0.015em]">{{ row.who }}</span>
                            <span class="block text-small text-ink-2">{{ row.what }}<template v-if="row.emphasis"> {{ row.emphasis }}</template><template v-if="row.whatTail">{{ row.whatTail }}</template></span>
                        </Link>
                    </Panel>
                </div>
            </div>
        </Page>
    </AdminLayout>
</template>
