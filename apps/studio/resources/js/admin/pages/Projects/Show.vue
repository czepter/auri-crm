<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import { Button } from "@/ui/button";
import BandCell from "@/admin/components/BandCell.vue";
import BandValue from "@/admin/components/BandValue.vue";
import LineItems from "@/admin/components/LineItems.vue";
import NoteThread from "@/admin/components/NoteThread.vue";
import ShootFields from "@/admin/components/ShootFields.vue";
import ShootRow from "@/admin/components/ShootRow.vue";
import { Input } from "@/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/ui/select";
import ProjectShell from "@/admin/components/ProjectShell.vue";
import TaskCreateDialog from "@/admin/components/TaskCreateDialog.vue";
import Rail from "@/ui/page/Rail.vue";
import RailItem from "@/ui/page/RailItem.vue";
import RailSection from "@/ui/page/RailSection.vue";
import Section from "@/ui/page/Section.vue";
import { formatEurosRounded } from "@/admin/lib/money";
import { dayWithWeekday as day } from "@/admin/lib/dates";

type Figure = { value: string | null; proof: string | null };

const props = defineProps<{
    header: App.Crm.Data.ProjectHeaderData;
    shoots: App.Crm.Data.ShootData[];
    paymentPlan: App.Payments.Data.PaymentPlanData | null;
    thread: App.Crm.Data.ThreadEntryData[];
    tasks: App.Crm.Data.TaskRowData[];
    shootOptions: { value: string; label: string }[];
    // Kommt von ProjectController@show; bis dahin bleibt die Ankerliste leer.
    anchors?: { value: string; label: string; group: string; on: string | null }[];
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
    people: App.Crm.Data.ProjectPersonData[];
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

function euro(minor: string | number | null) {
    return minor === null ? "—" : formatEurosRounded(Number(minor));
}

// C-11 (#247): the same build in every phase. Before the first shoot the
// history is short and the open list long, after delivery the other way
// round, three years later "Offen" holds one row — the follow-up — and the
// trace holds the repeat orders instead of dates. Building a head per phase
// means widening the screen with every phase that turns up.
const open = computed(() => props.tasks.filter((task) => !task.isDone));
const done = computed(() => props.tasks.filter((task) => task.isDone));
const overdue = computed(() => open.value.filter((task) => task.isOverdue).length);
const showDone = ref(false);

// The trace runs oldest first (BuildThread); the rail reads it the other way
// and keeps the first entry, because where the project came from stays the
// one line that does not scroll out.
const recent = computed(() => props.thread.slice(-4).reverse());
const origin = computed(() => (props.thread.length > 4 ? props.thread[0] : null));

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
        venue_name: shoot.venueName ?? "",
        venue_city: shoot.venueCity ?? "",
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

function togglePortal() {
    router.post(
        `/auftraege/${props.header.id}/portal-sichtbarkeit`,
        {},
        { preserveScroll: true },
    );
}

function complete(row: App.Crm.Data.TaskRowData) {
    router.post(`/aufgaben/${row.id}/erledigt`, {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout active="projekte">
        <ProjectShell :header="header" active="overview">
            <template #rail>
                <Rail>
                    <RailSection :title="$t('lead.people')">
                        <div
                            v-for="(person, index) in people"
                            :key="person.id"
                            class="flex items-center gap-2 py-[5px] text-[12.5px] leading-[1.47]"
                        >
                            <Link :href="`/kontakte/${person.id}`">{{ person.name }}</Link>
                            <Select
                                v-if="editing"
                                v-model="edit.people[index].role"
                            >
                                <SelectTrigger class="max-w-[820px]"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in roles"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ $t(`contacts.role.${option.value}`) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <span
                                v-else
                                class="ml-auto text-[11.5px] leading-[1.47] text-[#3F6B4A]"
                            >{{ $t(`contacts.role.${person.role}`) }}</span>
                        </div>
                    </RailSection>

                    <!-- Die Quelle gehört zur Anfrage, nicht zu den Kennzahlen der
                         Arbeit — im Band stand sie als Beleg unter dem Stand, den sie
                         nicht belegt. -->
                    <RailSection v-if="sourceLabel || editing" :title="$t('leads.source')">
                        <Select v-if="editing" v-model="edit.source">
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
                        <span v-else class="text-[12.5px] leading-[1.47]">{{ sourceLabel }}</span>

                        <Input
                            v-if="editing && edit.source === 'other'"
                            v-model="edit.source_other"
                            class="mt-1.5"
                            type="text"
                            list="source-presets"
                        />
                        <datalist id="source-presets">
                            <option v-for="value in presets.source" :key="value" :value="value" />
                        </datalist>
                    </RailSection>

                    <RailSection v-if="paymentPlan" :title="$t('dashboard.payments')">
                        <div
                            v-for="instalment in paymentPlan.installments"
                            :key="instalment.id"
                            class="flex gap-2.5 py-[5px] text-[12.5px] leading-[1.47]"
                        >
                            <span class="w-[74px] flex-none text-ink-2">{{ day(instalment.dueOn) }}</span>
                            <span>{{ euro(instalment.amount) }}</span>
                        </div>
                    </RailSection>

                    <RailSection v-if="galleries.length" :title="$t('contacts.galleries')">
                        <RailItem
                            v-for="gallery in galleries"
                            :key="gallery.id"
                            :title="gallery.title ?? '—'"
                            :href="`/galerien/${gallery.id}`"
                            :meta="
                                gallery.expiresAt
                                    ? $t('contacts.expiresOn', { date: day(gallery.expiresAt) })
                                    : null
                            "
                        />
                    </RailSection>

                    <!-- Der Verlauf steht am Fuß der Rail: er wird gelesen, wenn die
                         Gruppen darüber eine Frage offen lassen. -->
                    <div v-if="thread.length" class="mt-7 border-t border-line-hover pt-3">
                        <h3
                            class="m-0 mb-[7px] text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2"
                        >
                            {{ $t("project.history") }}
                        </h3>
                        <div
                            v-for="entry in recent"
                            :key="entry.key"
                            class="flex gap-2.5 py-1 text-[11.5px] leading-[1.47]"
                        >
                            <span class="w-[74px] flex-none text-ink-2">{{ entry.at ? day(entry.at) : "" }}</span>
                            <span class="min-w-0 flex-1">{{ entry.body }}</span>
                            <span v-if="entry.author" class="text-ink-2">{{ entry.author }}</span>
                        </div>
                        <template v-if="origin">
                            <div class="py-0.5 text-[11.5px] leading-[1.47] text-faint">⋯</div>
                            <div class="flex gap-2.5 py-1 text-[11.5px] leading-[1.47]">
                                <span class="w-[74px] flex-none text-ink-2">{{ origin.at ? day(origin.at) : "" }}</span>
                                <span class="min-w-0 flex-1">{{ origin.body }}</span>
                                <span v-if="origin.author" class="text-ink-2">{{ origin.author }}</span>
                            </div>
                        </template>
                    </div>
                </Rail>
            </template>

            <template #title>
                <Input
                    v-if="editing"
                    v-model="edit.name"
                    class="max-w-[820px] text-[22px]"
                    type="text"
                />
                <h1 v-else class="m-0 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em] max-[640px]:text-[25px]">
                    {{ header.names }}
                </h1>
            </template>

            <template #actions>
                <template v-if="editing">
                    <a
                        class="text-[12.5px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
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
                </template>

                <a
                    v-else
                    class="text-[12.5px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
                    href="#"
                    @click.prevent="editing = true"
                    >{{ $t("lead.edit") }}</a
                >
            </template>

                    <!-- Master data across, six values on one line, each with its proof
                         underneath — not a phase tile that a seventh phase would break. -->
                    <div class="mt-5 mb-5 flex">
                        <BandCell :label="$t('project.position')">
                            <Select
                                v-if="editing && stage"
                                v-model="edit.stage"
                            >
                                <SelectTrigger variant="filled" class="mt-[5px] max-w-[820px]"><SelectValue /></SelectTrigger>
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
                            <BandValue v-else plain>{{ figures.phase.value ?? "—" }}</BandValue>
                        </BandCell>

                        <BandCell
                            :label="$t('project.mainDateLabel')"
                            :sub="$t('board.shootCount', shoots.length)"
                        >
                            <template v-if="editing" #derived> · {{ $t("project.fromShoots") }}</template>
                            <BandValue plain>{{ day(figures.nextDate.value) }}</BandValue>
                        </BandCell>

                        <BandCell :label="$t('board.colValue')" :sub="figures.value.proof">
                            <template v-if="editing" #derived> · {{ $t("project.fromScope") }}</template>
                            <BandValue>{{ euro(figures.value.value) }}</BandValue>
                        </BandCell>

                        <BandCell
                            :label="$t('project.openAmount')"
                            :sub="
                                figures.open.proof
                                    ? $t('project.instalments', Number(figures.open.proof))
                                    : null
                            "
                        >
                            <template v-if="editing" #derived> · {{ $t("project.fromPayments") }}</template>
                            <BandValue :warn="Number(figures.open.value ?? 0) > 0">{{
                                euro(figures.open.value)
                            }}</BandValue>
                        </BandCell>

                        <BandCell :label="$t('project.tasks')">
                            <BandValue plain>
                                {{ open.length }}
                                <span class="text-[12.5px] font-normal text-ink-2">{{
                                    $t("project.tasksOf", { all: tasks.length })
                                }}</span>
                            </BandValue>
                            <div v-if="overdue" class="mt-[3px] text-[11.5px] text-action">
                                {{ $t("project.tasksOverdue", overdue) }}
                            </div>
                        </BandCell>

                        <BandCell :label="$t('project.portal')">
                            <!-- The figure and the switch that changes it, in one cell: this
                                 is the one key value that is a decision rather than a
                                 derivation, so it is the one with a control. -->
                            <BandValue plain class="flex items-center gap-3">
                                {{
                                    figures.portal.value === "on"
                                        ? $t("contacts.portalOn")
                                        : $t("contacts.portalOff")
                                }}
                                <span
                                    v-if="header.canManagePortalVisibility"
                                    class="relative h-6 w-10 flex-none cursor-pointer rounded-[var(--r-pill)] after:absolute after:top-[3px] after:left-[3px] after:size-[18px] after:rounded-full after:bg-paper after:transition-transform after:duration-[.16s] after:ease-out after:content-['']"
                                    :class="
                                        figures.portal.value === 'on'
                                            ? 'bg-action after:translate-x-4'
                                            : 'bg-sunk-2'
                                    "
                                    role="button"
                                    :aria-label="$t('project.portalToggle')"
                                    @click="togglePortal"
                                />
                            </BandValue>
                        </BandCell>
                    </div>

                    <Section v-if="editing || shoots.length" :title="$t('lead.dates')">
                        <template v-if="!editing" #actions>
                            <span class="text-[11.5px] text-ink-2">{{
                                $t("board.shootCount", shoots.length)
                            }}</span>
                            <Button v-if="header.canManageShoots" class="ml-auto" variant="outline" as-child>
                                <Link :href="`/auftraege/${header.id}/shootings`">
                                    <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
                                    {{ $t("project.shootAdd") }}
                                </Link>
                            </Button>
                        </template>

                        <div v-if="editing" class="max-w-[820px]">
                            <ShootFields
                                v-for="shoot in edit.shoots"
                                :key="shoot.id"
                                :shoot="shoot"
                            />
                        </div>

                        <div v-else-if="shoots.length" class="[&>div]:py-[9px] [&>div]:shadow-none">
                            <ShootRow v-for="shoot in shoots" :key="shoot.id" :shoot="shoot" />
                        </div>
                    </Section>

                    <Section :title="$t('project.tasks')">
                        <template #actions>
                            <span class="inline-flex gap-0.5 rounded-[var(--r-pill)] bg-sunk-2 p-0.5">
                                <button
                                    class="cursor-pointer rounded-[var(--r-pill)] px-[11px] py-1 text-[11.5px] leading-none"
                                    :class="showDone ? 'text-ink-2' : 'bg-paper font-semibold'"
                                    type="button"
                                    @click="showDone = false"
                                >
                                    {{ $t("project.tasksOpen", { count: open.length }) }}
                                </button>
                                <button
                                    class="cursor-pointer rounded-[var(--r-pill)] px-[11px] py-1 text-[11.5px] leading-none"
                                    :class="showDone ? 'bg-paper font-semibold' : 'text-ink-2'"
                                    type="button"
                                    @click="showDone = true"
                                >
                                    {{ $t("project.tasksDone", { count: done.length }) }}
                                </button>
                            </span>

                            <TaskCreateDialog
                                v-if="header.canManageTasks"
                                :project-id="header.id"
                                :shoot-options="shootOptions"
                                :anchors="anchors ?? []"
                            />
                        </template>

                        <div
                            v-for="row in showDone ? done : open"
                            :key="row.id"
                            class="flex items-center gap-3 py-[9px]"
                        >
                            <span
                                v-if="row.isDone"
                                class="flex size-[15px] flex-none items-center justify-center rounded-[5px] bg-ok-bg text-ok"
                            >
                                <svg
                                    class="size-[9px] fill-none stroke-current [stroke-linecap:round] [stroke-linejoin:round] [stroke-width:3]"
                                    viewBox="0 0 24 24"
                                >
                                    <path d="M4 12l5 5L20 6" />
                                </svg>
                            </span>
                            <button
                                v-else
                                class="size-[15px] flex-none rounded-[5px] bg-sunk-2"
                                type="button"
                                @click="complete(row)"
                            />
                            <span class="text-[13.5px] leading-[1.47]" :class="row.isDone && 'text-ink-2'">{{
                                row.title
                            }}</span>
                            <span
                                class="text-[10.5px] leading-[1.47] tracking-[0.05em] uppercase text-ink-2"
                            >{{ row.audienceLabel }}</span>
                            <span v-if="row.shootLabel" class="text-[11.5px] leading-[1.47] text-faint">{{
                                row.shootLabel
                            }}</span>
                            <span
                                class="ml-auto text-[12px] leading-[1.47] tabular-nums"
                                :class="row.isOverdue && !row.isDone ? 'text-action' : 'text-ink-2'"
                            >{{ row.dueOn ? day(row.dueOn) : "" }}</span>
                        </div>
                    </Section>

                    <!-- The scope as it stands, not a copy of the quote. Nothing is
                         locked: the order changes in conversation and the document lags
                         behind. Forbid the change and the scope is simply wrong while the
                         work happens anyway. -->
                    <Section v-if="scope.items.length" :title="$t('project.scope')">
                        <LineItems
                            class="[&>div]:py-[9px] [&>div]:shadow-none"
                            :items="scope.items"
                        >
                            <template #note="{ item }">
                                <span v-if="item.state" class="ml-[3px] text-[11.5px] text-action">{{
                                    $t(`project.scope_${item.state}`)
                                }}</span>
                            </template>
                            <template #priceNote="{ item }">
                                <span
                                    v-if="item.state === 'changed'"
                                    class="mt-0.5 block text-[11.5px] font-normal text-action"
                                >{{ euro(item.wasUnitPrice) }}</span>
                            </template>
                        </LineItems>

                        <p
                            v-if="scope.delta"
                            class="mt-[7px] text-[12px] text-ink-2 [&_em]:not-italic [&_em]:text-action"
                        >
                            <em>{{
                                $t("project.scopeDelta", { amount: euro(Math.abs(scope.delta)) })
                            }}</em>
                            <Link
                                class="ml-3 text-[12.5px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
                                :href="`/auftraege/${header.id}/angebote`"
                            >
                                {{ $t("project.sendAddendum") }}
                            </Link>
                        </p>
                    </Section>

                    <Section :title="$t('lead.notes')">
                        <NoteThread
                            :entries="thread"
                            :action="`/auftraege/${header.id}/notizen`"
                        />
                    </Section>
        </ProjectShell>
    </AdminLayout>
</template>
