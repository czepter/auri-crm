<script setup lang="ts">
import { computed } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import Panel from "@/admin/components/Panel.vue";
import PageHead from "@/ui/page/PageHead.vue";
import Metric from "@/admin/components/Metric.vue";
import { Button } from "@/ui/button";
import type { Tone } from "@/admin/lib/metricDots";
import { formatEurosRounded } from "@/admin/lib/money";

const props = defineProps<{
    today: string;
    nextShoot: App.Crm.Data.NextShootData | null;
    week: { when: string; what: string; away: boolean; href: string | null }[];
    tasks: App.Crm.Data.TaskRowData[];
    openTasks: number;
    inquiries: App.Crm.Data.DomainRowData[];
    payments: App.Crm.Data.DomainRowData[];
    galleries: App.Crm.Data.DomainRowData[];
    newLeads: App.Crm.Data.LeadCardData[];
    pipeline: number;
    pipelineStages: string[];
    overdue: {
        total: number;
        open: number;
        all: number;
        sinceDays: number | null;
    };
    quotes: { sent: number; accepted: number };
    unanswered: number;
}>();

// Quote.total and PaymentInstallment.amount are minor units end to end; this
// is the one place they turn into euros, so no other spot on this screen
// divides by 100 again.
const heading = computed(() =>
    new Date(props.today).toLocaleDateString("de-DE", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
    }),
);

// Which shade a funnel stage gets. Four shades for six stages, so the last
// three share the pale one — the row shows movement through the funnel, not a
// legend of it.
const STAGE_TONES: Record<string, Tone> = {
    new: "on",
    contacted: "s2",
    quote_sent: "s3",
};

const pipelineDots = computed<Tone[]>(() =>
    props.pipelineStages.map((stage) => STAGE_TONES[stage] ?? ""),
);

const overdueDots = computed<Tone[]>(() => [
    ...Array.from<unknown, Tone>({ length: props.overdue.open }, () => "warn"),
    ...Array.from<unknown, Tone>(
        { length: Math.max(0, props.overdue.all - props.overdue.open) },
        () => "on",
    ),
]);

const quoteDots = computed<Tone[]>(() => [
    ...Array.from<unknown, Tone>({ length: props.quotes.accepted }, () => "on"),
    ...Array.from<unknown, Tone>(
        { length: Math.max(0, props.quotes.sent - props.quotes.accepted) },
        () => "",
    ),
]);

const ghostDots = Array.from<unknown, Tone>({ length: 5 }, () => "");

const readinessDots = computed<Tone[]>(() =>
    (props.nextShoot?.readiness ?? []).map((mark) =>
        mark.done ? "on" : "warn",
    ),
);

const openPoints = computed(
    () =>
        (props.nextShoot?.readiness ?? []).filter((mark) => !mark.done).length,
);

// The day's five decisions, from every source that owes one: a task, an offer
// waiting on the studio, money the machine has already chased. Sorted so the
// marked ones lead — a row that is late is the one to press first.
const decisions = computed(() => {
    const fromTasks = props.tasks.map((task) => ({
        key: `task:${task.id}`,
        who: task.projectName,
        href: `/auftraege/${task.projectId}`,
        what: task.title,
        emphasis: task.isOverdue ? dayOf(task.dueOn) : null,
        whatTail:
            !task.isOverdue && task.dueOn ? ` · ${dayOf(task.dueOn)}` : null,
        hot: task.isOverdue,
        done: task,
        action: null,
    }));

    const fromRows = [...props.inquiries, ...props.payments].map((row) => ({
        key: `row:${row.projectId}:${row.what}`,
        who: row.who,
        href: `/auftraege/${row.projectId}`,
        what: row.what,
        emphasis: row.emphasis,
        whatTail: row.whatTail,
        hot: row.hot,
        done: null,
        action: row.action,
    }));

    return [...fromTasks, ...fromRows]
        .sort((a, b) => Number(b.hot) - Number(a.hot))
        .slice(0, 5);
});

const owed = computed(
    () => props.openTasks + props.inquiries.length + props.payments.length,
);

// A studio on its first day owes nothing, has booked nothing and has no
// history to count — the grid then stands as placeholders rather than as
// eight containers reporting their own absence.
const bare = computed(
    () =>
        !decisions.value.length &&
        !props.nextShoot &&
        !props.week.length &&
        !props.newLeads.length &&
        !props.galleries.length &&
        !props.pipeline &&
        !props.overdue.total &&
        !props.quotes.sent,
);

function dayOf(date: string | null) {
    return date
        ? new Date(date).toLocaleDateString("de-DE", {
              day: "2-digit",
              month: "2-digit",
          })
        : "";
}

// "Mi 14:00" — the weekday, because a week is read by day, and the time only
// where the entry has one.
function weekWhen(when: string) {
    const [date, time] = when.split(" ");
    const day = new Date(date).toLocaleDateString("de-DE", {
        weekday: "short",
    });

    return time ? `${day} ${time}` : day;
}

const heroDay = computed(() => {
    if (props.nextShoot?.date == null) {
        return undefined;
    }

    const date = new Date(props.nextShoot.date);

    return {
        number: String(date.getDate()),
        weekday: date.toLocaleDateString("de-DE", { weekday: "short" }),
        what: props.nextShoot.title,
        who: [props.nextShoot.projectName, props.nextShoot.callTime]
            .filter(Boolean)
            .join(" · "),
    };
});

function complete(task: App.Crm.Data.TaskRowData) {
    router.post(`/aufgaben/${task.id}/erledigt`, {}, { preserveScroll: true });
}

/* crm.css, inlined. */
/* border and radius are tokens.css .panel, background and padding crm.css. */
/* Unexercised: the fixture has no double-booked date. tokens.css .flag draws
   the alert chip, crm.css .list .flag overrides everything but that chip. */
</script>

<template>
    <AdminLayout active="uebersicht">
        <div data-tokens>
            <PageHead :kicker="heading" :title="$t('dashboard.title')">

                <!-- Unbeantwortet, not ungelesen: ThreadParticipant carries no
             last_read_at and this screen does not add one. -->
                <Link
                    v-if="unanswered"
                    href="/posteingang"
                    class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.05em] uppercase text-ink-2"
                >
                    {{ $t("dashboard.unanswered", { count: unanswered }) }}
                </Link>

                <Button :as="Link" variant="pill" class="ml-auto" href="/anfragen/neu">
                    {{ $t("leads.createKicker") }}
                </Button>
            </PageHead>

            <div
                class="grid flex-1 auto-rows-min grid-cols-4 items-start gap-x-5 gap-y-7 px-8 py-7"
            >
                <template v-if="bare">
                    <Metric :label="$t('dashboard.pipeline')" ghost :dots="ghostDots" />
                    <Metric :label="$t('dashboard.overdueTotal')" ghost :dots="ghostDots" />
                    <Metric :label="$t('dashboard.acceptance')" ghost :dots="ghostDots" />
                    <Metric :label="$t('dashboard.nextShoot')" ghost :dots="ghostDots" />
                </template>

                <!-- Every tile brings its own proof, or it is not a tile (C-05). A
             figure with nothing behind it is absent rather than a zero. -->
                <Metric
                    v-if="pipeline"
                    :label="$t('dashboard.pipeline')"
                    :value="formatEurosRounded(pipeline)"
                    :dots="pipelineDots"
                >
                    <p>
                        <b>{{
                            $t("dashboard.leadCount", pipelineStages.length)
                        }}</b>
                    </p>
                </Metric>

                <Metric
                    v-if="overdue.total"
                    :label="$t('dashboard.overdueTotal')"
                    :value="formatEurosRounded(overdue.total)"
                    warn
                    :dots="overdueDots"
                >
                    <p>
                        <b>{{
                            $t("dashboard.instalmentsOpen", {
                                open: overdue.open,
                                all: overdue.all,
                            })
                        }}</b>
                        <template v-if="overdue.sinceDays !== null">
                            ·
                            {{
                                $t("dashboard.sinceDays", overdue.sinceDays)
                            }}</template
                        >
                    </p>
                </Metric>

                <Metric
                    v-if="quotes.sent"
                    :label="$t('dashboard.acceptance')"
                    :value="String(quotes.accepted)"
                    :unit="$t('dashboard.ofSent', { count: quotes.sent })"
                    :dots="quoteDots"
                >
                    <p>{{ $t("dashboard.lastTwelveMonths") }}</p>
                </Metric>

                <Metric
                    v-if="heroDay"
                    :label="$t('dashboard.nextShoot')"
                    :href="`/auftraege/${nextShoot!.projectId}/shootings`"
                    :day="heroDay"
                    :dots="readinessDots"
                >
                    <p>
                        <b v-if="openPoints">{{
                            $t("dashboard.openPoints", {
                                count: openPoints,
                                all: readinessDots.length,
                            })
                        }}</b>
                        <b v-else>{{ $t("dashboard.allReady") }}</b>
                        <template v-if="nextShoot?.venue">
                            · {{ nextShoot.venue }}</template
                        >
                    </p>
                </Metric>

                <!-- The most important field is the only one without a container:
                     emphasis comes from position, size and the one filled button. -->
                <Panel
                    v-if="decisions.length || nextShoot || bare"
                    bare
                    class="col-span-3"
                    :title="$t('dashboard.decide')"
                    :count="decisions.length || null"
                >
                    <template #action>
                        <span
                            v-if="owed > decisions.length"
                            class="ml-auto text-[11.5px] tabular-nums text-ink-2"
                        >
                            {{ $t("dashboard.moreOwed", { count: owed - decisions.length }) }}
                        </span>
                        <!-- T-24: die vollständige Liste, in der auch die
                             Zuständigkeit steht. -->
                        <Link
                            class="text-[11.5px] text-ink-2 no-underline"
                            :class="owed > decisions.length ? '' : 'ml-auto'"
                            href="/aufgaben"
                            >{{ $t("dashboard.allTasks") }}</Link
                        >
                    </template>

                    <div v-if="decisions.length">
                        <div
                            v-for="(decision, index) in decisions"
                            :key="decision.key"
                            class="flex items-center gap-3.5 py-[11px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
                        >
                            <!-- leading-[1.35] is tokens.css .who, which decides it here too -->
                            <Link
                                class="w-52 flex-none overflow-hidden text-[14.5px] leading-[1.35] font-semibold tracking-[-0.018em] text-ellipsis whitespace-nowrap"
                                :href="decision.href"
                                >{{ decision.who }}</Link
                            >
                            <span
                                class="min-w-0 flex-1 overflow-hidden text-[12.5px] text-ellipsis whitespace-nowrap text-ink-2 [&_em]:text-action [&_em]:not-italic"
                            >
                                {{ decision.what }}
                                <em v-if="decision.emphasis">{{
                                    decision.emphasis
                                }}</em>
                                <template v-if="decision.whatTail">{{
                                    decision.whatTail
                                }}</template>
                            </span>
                            <span
                                class="flex w-42 flex-none items-center justify-end gap-2"
                            >
                                <!-- Exactly one filled button on the screen, and it is the due
                     action in the first decision row — not `Anfrage anlegen`,
                     which is never the thing that is due. -->
                                <Button
                                    v-if="decision.action"
                                    :as="Link"
                                    :variant="index === 0 ? 'pill' : 'pill-muted'"
                                    :href="decision.action.target"
                                    >{{ decision.action.label }}</Button
                                >
                                <Button
                                    v-else-if="decision.done"
                                    type="button"
                                    :variant="index === 0 ? 'pill' : 'pill-muted'"
                                    @click="complete(decision.done)"
                                    >{{ $t("dashboard.done") }}</Button
                                >
                            </span>
                        </div>
                    </div>

                    <!-- A day with nothing to decide is the normal one. The next
               shooting day takes the field's place rather than a heading with
               nothing under it. -->
                    <p
                        v-else-if="nextShoot"
                        class="text-[11.5px] leading-[1.45] font-normal text-faint"
                    >
                        {{
                            $t("dashboard.nothingDueNext", {
                                what: nextShoot.title,
                                date: dayOf(nextShoot.date),
                            })
                        }}
                    </p>

                    <div v-else>
                        <div
                            v-for="row in 3"
                            :key="row"
                            class="flex items-center gap-3.5 py-[11px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
                        >
                            <div class="h-[9px] w-52 flex-none rounded-full bg-sunk-2" />
                            <div class="h-[9px] flex-1 rounded-full bg-sunk-2" />
                            <div class="h-[9px] w-20 flex-none rounded-full bg-sunk-2" />
                        </div>
                    </div>
                </Panel>

                <Panel v-if="week.length || bare" :title="$t('dashboard.thisWeek')">
                    <p v-if="!week.length" class="text-[11.5px] leading-[1.45] text-faint">
                        {{ $t("dashboard.weekEmpty") }}
                    </p>
                    <div
                        v-for="entry in week"
                        :key="`${entry.when}${entry.what}`"
                        class="flex items-baseline gap-[9px] py-2 shadow-[inset_0_1px_0_#EEEEF0] first:shadow-none"
                    >
                        <span
                            class="w-[62px] flex-none text-[12px] font-semibold tracking-[-0.01em] tabular-nums"
                        >
                            {{ weekWhen(entry.when) }}
                        </span>
                        <component
                            :is="entry.href ? Link : 'span'"
                            :href="entry.href"
                            class="text-[13px] font-medium tracking-[-0.01em]"
                            :class="entry.away && 'font-normal text-ink-2'"
                        >
                            {{ entry.away ? $t("dashboard.away", { name: entry.what }) : entry.what }}
                        </component>
                    </div>
                </Panel>

                <Panel
                    v-if="newLeads.length || bare"
                    class="col-span-2"
                    :title="$t('dashboard.inquiries')"
                    :count="newLeads.length || null"
                >
                    <template #action>
                        <Link class="text-[12.5px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline ml-auto text-[12px]" href="/anfragen">{{
                            $t("dashboard.toBoard")
                        }}</Link>
                    </template>
                    <p v-if="!newLeads.length" class="text-[11.5px] leading-[1.45] text-faint">
                        {{ $t("dashboard.inquiriesEmpty") }}
                    </p>
                    <div v-for="lead in newLeads" :key="lead.id" class="flex items-baseline gap-[9px] py-2 shadow-[inset_0_1px_0_#EEEEF0] first:shadow-none">
                        <span
                            class="w-14 flex-none text-[10.5px] tracking-[0.05em] uppercase text-ink-2"
                        >{{ lead.type }}</span>
                        <Link class="text-[13px] font-medium tracking-[-0.01em]" :href="`/anfragen/${lead.leadId}`">{{ lead.names }}</Link>
                        <!-- The conflict is a mark on the row that has it and leads into
                             the resolution; a footer counting collisions names neither
                             which nor where. -->
                        <Link
                            v-if="lead.conflicts.length"
                            class="mt-1.5 ml-auto flex items-center rounded-[7px] bg-late-bg bg-icon-alert bg-[length:13px_13px] bg-[position:9px_center] bg-no-repeat py-[7px] pr-[9px] pl-7 text-[11.5px] leading-[1.3] font-medium whitespace-nowrap text-action no-underline hover:underline"
                            :href="`/auftraege/${lead.id}/shootings`"
                        >
                            {{ $t("dashboard.dateDoubled", { date: dayOf(lead.date) }) }}
                        </Link>
                        <span v-else class="ml-auto text-[11.5px] whitespace-nowrap text-ink-2">{{ lead.source }}</span>
                    </div>
                </Panel>

                <Panel
                    v-if="galleries.length || bare"
                    class="col-span-2"
                    :title="$t('dashboard.galleries')"
                    :count="galleries.length || null"
                >
                    <p v-if="!galleries.length" class="text-[11.5px] leading-[1.45] text-faint">
                        {{ $t("dashboard.galleriesEmpty") }}
                    </p>
                    <div
                        v-for="row in galleries"
                        :key="`${row.projectId}${row.what}`"
                        class="flex items-baseline gap-[9px] py-2 shadow-[inset_0_1px_0_#EEEEF0] first:shadow-none"
                    >
                        <Link class="text-[13px] font-medium tracking-[-0.01em]" :href="`/auftraege/${row.projectId}`">{{ row.who }}</Link>
                        <span class="ml-auto text-[11.5px] whitespace-nowrap text-ink-2">
                            {{ row.what }}
                            <template v-if="row.emphasis">{{ row.emphasis }}</template>
                            <template v-if="row.whatTail">{{ row.whatTail }}</template>
                        </span>
                    </div>
                </Panel>
            </div>
        </div>
    </AdminLayout>
</template>
