<script setup lang="ts">
import { computed } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import Metric from "@/admin/components/Metric.vue";
import { Button } from "@/ui/button";
import type { Tone } from "@/admin/lib/metricDots";

const props = defineProps<{
    today: string;
    nextShoot: App.Crm.Data.NextShootData | null;
    week: { when: string; what: string; away: boolean }[];
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
const money = new Intl.NumberFormat("de-DE", {
    style: "currency",
    currency: "EUR",
    maximumFractionDigits: 0,
});

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
// history to count — the screen is then the one control that starts all
// three, not eight containers reporting their own absence.
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

/* crm.css, inlined. The token scope on the wrapper stays — data-theme="crm"
   is both selector prefix and token scope, and only the first is going. */
const BTN_BASE =
    "inline-flex cursor-pointer items-center gap-1.5 rounded-[var(--r-pill)] border-0 px-3.5 py-1.5 text-[12.5px] font-medium tracking-[-0.01em] leading-[1.45] whitespace-nowrap no-underline transition-[filter,background-color] duration-[.12s] ease-out";
const BTN = `${BTN_BASE} bg-action text-white hover:brightness-[1.08]`;
const BTN_2 = `${BTN_BASE} bg-sunk-2 text-foreground hover:bg-[#E9E9EC]`;
const LINK =
    "text-[12.5px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline";
/* border and radius are tokens.css .panel, background and padding crm.css. */
const PANEL = "overflow-hidden rounded-md bg-sunk px-[18px] py-4";
const HEAD = "mb-2 flex items-baseline gap-[9px]";
const H2 = "m-0 text-[15px] leading-[1.2] font-semibold tracking-[-0.02em]";
const COUNT = "text-[11.5px] tabular-nums text-ink-2";
const ROW =
    "flex items-baseline gap-[9px] py-2 shadow-[inset_0_1px_0_#EEEEF0] first:shadow-none";
const NAME = "text-[13px] font-medium tracking-[-0.01em]";
const META = "ml-auto text-[11.5px] whitespace-nowrap text-ink-2";
/* Unexercised: the fixture has no double-booked date. tokens.css .flag draws
   the alert chip, crm.css .list .flag overrides everything but that chip. */
const FLAG =
    "mt-1.5 ml-auto flex items-center rounded-[7px] bg-late-bg bg-icon-alert bg-[length:13px_13px] bg-[position:9px_center] bg-no-repeat py-[7px] pr-[9px] pl-7 text-[11.5px] leading-[1.3] font-medium whitespace-nowrap text-action no-underline hover:underline";
</script>

<template>
    <AdminLayout active="uebersicht">
        <div data-theme="crm">
            <div class="flex items-center gap-3 px-8 pt-[22px]">
                <div>
                    <div
                        class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.05em] uppercase text-ink-2"
                    >
                        {{ heading }}
                    </div>
                    <h1>{{ $t("dashboard.title") }}</h1>
                </div>

                <!-- Unbeantwortet, not ungelesen: ThreadParticipant carries no
             last_read_at and this screen does not add one. Static, because
             threads exist only per project — routes/admin.php has no
             cross-project inbox to send this to, and inventing a destination
             is the same failure as inventing a number. -->
                <span
                    v-if="unanswered"
                    class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.05em] uppercase text-ink-2 ml-auto"
                >
                    {{ $t("dashboard.unanswered", { count: unanswered }) }}
                </span>
            </div>

            <Link v-if="bare" :class="[BTN, 'mt-5']" href="/anfragen/neu">
                {{ $t("leads.createKicker") }}
            </Link>

            <div
                v-else
                class="grid flex-1 auto-rows-min grid-cols-4 items-start gap-x-5 gap-y-7 px-8 py-7"
            >
                <!-- Every tile brings its own proof, or it is not a tile (C-05). A
             figure with nothing behind it is absent rather than a zero. -->
                <Metric
                    v-if="pipeline"
                    :label="$t('dashboard.pipeline')"
                    :value="money.format(pipeline / 100)"
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
                    :value="money.format(overdue.total / 100)"
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
                <!-- bare: the most important field is the only one without a container. -->
                <section
                    v-if="decisions.length || nextShoot"
                    class="col-span-3 overflow-hidden bg-transparent px-[18px] py-4"
                >
                    <header :class="HEAD">
                        <h2 :class="H2">{{ $t("dashboard.decide") }}</h2>
                        <span v-if="decisions.length" :class="COUNT">{{
                            decisions.length
                        }}</span>
                        <span
                            v-if="owed > decisions.length"
                            :class="[COUNT, 'ml-auto']"
                        >
                            {{
                                $t("dashboard.moreOwed", {
                                    count: owed - decisions.length,
                                })
                            }}
                        </span>
                    </header>

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
                                    :class="index === 0 ? BTN : BTN_2"
                                    :href="decision.action.target"
                                    >{{ decision.action.label }}</Button
                                >
                                <Button
                                    v-else-if="decision.done"
                                    type="button"
                                    :class="index === 0 ? BTN : BTN_2"
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
                </section>

                <section v-if="week.length" :class="PANEL">
                    <header :class="HEAD">
                        <h2 :class="H2">{{ $t("dashboard.thisWeek") }}</h2>
                    </header>
                    <div>
                        <div
                            v-for="entry in week"
                            :key="`${entry.when}${entry.what}`"
                            :class="ROW"
                        >
                            <span
                                class="w-[62px] flex-none text-[12px] font-semibold tracking-[-0.01em] tabular-nums"
                            >
                                {{ weekWhen(entry.when) }}
                            </span>
                            <span
                                :class="[
                                    NAME,
                                    entry.away && 'font-normal text-ink-2',
                                ]"
                            >
                                {{
                                    entry.away
                                        ? $t("dashboard.away", {
                                              name: entry.what,
                                          })
                                        : entry.what
                                }}
                            </span>
                        </div>
                    </div>
                </section>

                <section v-if="newLeads.length" :class="[PANEL, 'col-span-2']">
                    <header :class="HEAD">
                        <h2 :class="H2">{{ $t("dashboard.inquiries") }}</h2>
                        <span :class="COUNT">{{ newLeads.length }}</span>
                        <Link
                            :class="[LINK, 'ml-auto text-[12px]']"
                            href="/anfragen"
                            >{{ $t("dashboard.toBoard") }}</Link
                        >
                    </header>
                    <div>
                        <div
                            v-for="lead in newLeads"
                            :key="lead.id"
                            :class="ROW"
                        >
                            <span
                                class="w-14 flex-none text-[10.5px] tracking-[0.05em] uppercase text-ink-2"
                                >{{ lead.type }}</span
                            >
                            <Link
                                :class="NAME"
                                :href="`/anfragen/${lead.leadId}`"
                                >{{ lead.names }}</Link
                            >
                            <!-- The conflict is a mark on the row that has it and leads into
                   the resolution; a footer counting collisions names neither
                   which nor where. -->
                            <Link
                                v-if="lead.conflicts.length"
                                :class="FLAG"
                                :href="`/auftraege/${lead.id}/shootings`"
                            >
                                {{
                                    $t("dashboard.dateDoubled", {
                                        date: dayOf(lead.date),
                                    })
                                }}
                            </Link>
                            <span v-else :class="META">{{ lead.source }}</span>
                        </div>
                    </div>
                </section>

                <section v-if="galleries.length" :class="[PANEL, 'col-span-2']">
                    <header :class="HEAD">
                        <h2 :class="H2">{{ $t("dashboard.galleries") }}</h2>
                        <span :class="COUNT">{{ galleries.length }}</span>
                    </header>
                    <div>
                        <div
                            v-for="row in galleries"
                            :key="`${row.projectId}${row.what}`"
                            :class="ROW"
                        >
                            <Link
                                :class="NAME"
                                :href="`/auftraege/${row.projectId}`"
                                >{{ row.who }}</Link
                            >
                            <span :class="META">
                                {{ row.what }}
                                <template v-if="row.emphasis">{{
                                    row.emphasis
                                }}</template>
                                <template v-if="row.whatTail">{{
                                    row.whatTail
                                }}</template>
                            </span>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
