<script setup lang="ts">
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import { Link, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import { Alert, AlertDescription, AlertTitle } from "@/ui/alert";
import { Button } from "@/ui/button";
import BandCell from "@/admin/components/BandCell.vue";
import BandValue from "@/admin/components/BandValue.vue";
import LineItems from "@/admin/components/LineItems.vue";
import NoteThread from "@/admin/components/NoteThread.vue";
import ShootFields from "@/admin/components/ShootFields.vue";
import ShootRow from "@/admin/components/ShootRow.vue";
import { Field } from "@/ui/field";
import { Input } from "@/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/ui/select";
import ProjectFields from "@/admin/components/ProjectFields.vue";
import type { ProjectFieldValues } from "@/admin/components/ProjectFields.vue";
import ProjectShell from "@/admin/components/ProjectShell.vue";
import TaskCreateDialog from "@/admin/components/TaskCreateDialog.vue";
import Card from "@/ui/page/Card.vue";
import { ROLE_SUGGESTIONS, roleLabel } from "@/admin/lib/roles";
import Section from "@/ui/page/Section.vue";
import { formatEurosRounded } from "@/admin/lib/money";
import { dayWithWeekday as day } from "@/admin/lib/dates";

type Figure = { value: string | null; proof: string | null };
type ProjectFieldDefinition = InstanceType<typeof ProjectFields>["$props"]["fields"][number];

const props = defineProps<{
    header: App.Crm.Data.ProjectHeaderData;
    shoots: App.Crm.Data.ShootData[];
    thread: App.Crm.Data.ThreadEntryData[];
    tasks: App.Crm.Data.TaskRowData[];
    shootOptions: { value: string; label: string }[];
    staffOptions: { value: string; label: string }[];
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
        next: { key: string; label: string };
    } | null;
}>();

const { t } = useI18n();

const galleriesHref = computed(() => props.header.tabs.find((tab) => tab.key === "galleries")?.href);

function advanceStalled() {
    router.post(
        `/shootings/${props.stalled?.shootId}/status`,
        { status: props.stalled?.next.key },
        { preserveScroll: true },
    );
}

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

/*
 * Jede Karte hält ihr eigenes Formular und schickt ihre eigene Teilmenge an
 * PATCH /auftraege/{id} — wer die Termine speichert, hat die Eckdaten nicht in
 * der Hand.
 */
const detailsEditing = ref(false);
const datesEditing = ref(false);

const details = useForm({
    name: props.header.name,
    stage: props.stage,
    source: props.source,
    source_other: props.sourceOther ?? "",
    fields: { ...props.fieldValues },
});

// Die Rolle hing am Bearbeiten-Modus der rechten Leiste. Der ist weg, also
// steht sie hier — Freitext wie am Fall, die bekannten als Vorschlag.
const kontakteEditing = ref(false);

const kontakte = useForm({
    people: props.people.map((person) => ({ id: person.id, role: person.role })),
});

function saveKontakte() {
    kontakte.patch(`/auftraege/${props.header.id}`, {
        preserveScroll: true,
        onSuccess: () => (kontakteEditing.value = false),
    });
}

const kontakteSummary = computed(() => props.people.map((person) => person.name).join(", "));

const dates = useForm({
    shoots: props.shoots.map((shoot) => ({
        id: shoot.id,
        date: shoot.date ?? "",
        call_time: shoot.callTime ?? "",
        end_time: shoot.endTime ?? "",
        venue_name: shoot.venueName ?? "",
        venue_city: shoot.venueCity ?? "",
    })),
});

const blank = (value: string) => (value === "" ? null : value);

function saveDetails() {
    details.patch(`/auftraege/${props.header.id}`, {
        preserveScroll: true,
        onSuccess: () => (detailsEditing.value = false),
    });
}

function saveDates() {
    dates
        .transform((data) => ({
            shoots: data.shoots.map((shoot) => ({
                ...shoot,
                date: blank(shoot.date),
                call_time: blank(shoot.call_time),
                end_time: blank(shoot.end_time),
            })),
        }))
        .patch(`/auftraege/${props.header.id}`, {
            preserveScroll: true,
            onSuccess: () => (datesEditing.value = false),
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

const datesSummary = computed(() => props.shoots.map((shoot) => day(shoot.date)).join(", "));

const taskSummary = computed(() =>
    [`${open.value.length}/${props.tasks.length}`, overdue.value ? t("project.tasksOverdue", overdue.value) : null]
        .filter(Boolean)
        .join(" · "),
);

const notes = computed(() => props.thread.filter((entry) => !entry.system));

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
                    <Section v-if="inquiry" :title="$t('project.lead')">
                        <div class="flex max-[1080px]:flex-col max-[1080px]:gap-3">
                            <p class="m-0 max-w-[58ch] min-w-0 flex-1 text-[14px] leading-[1.5] tracking-[-0.008em] whitespace-pre-line">
                                {{ inquiry.text ?? $t("lead.noText") }}
                            </p>
                            <div
                                class="w-[238px] flex-none pl-[22px] text-[12.5px] leading-[1.35] text-ink-2 shadow-[inset_1px_0_0_var(--rule)] max-[1080px]:w-auto max-[1080px]:pl-0 max-[1080px]:shadow-none [&_b]:block [&_b]:text-[13px] [&_b]:font-semibold [&_b]:tracking-[-0.012em] [&_b]:text-foreground"
                            >
                                <div><b>{{ inquiry.typeLabel }}</b>{{ inquiry.sourceLabel }}</div>
                                <div class="mt-[11px] pt-[11px] shadow-[inset_0_1px_0_var(--rule)]">
                                    <b>{{ inquiry.capturedVia ?? $t("leads.capturedAt") }}</b>{{ day(inquiry.capturedAt) }}
                                </div>
                            </div>
                        </div>
                    </Section>

                    <Alert
                        v-if="stalled"
                        class="mt-6 flex items-center gap-4 border-0 bg-[var(--due-bg)] px-4 py-3.5"
                    >
                        <div class="min-w-0">
                            <AlertTitle class="text-[14px] font-semibold text-[var(--due)]">
                                {{ stalled.label }} {{ $t("projectQueue.days", stalled.days) }}
                            </AlertTitle>
                            <AlertDescription class="text-[12.5px] text-ink-2">
                                {{ day(stalled.date) }} ·
                                {{ $t("project.stalledStatus", { stage: stalled.status, date: day(stalled.since) }) }}
                            </AlertDescription>
                        </div>
                        <div v-if="header.canManageShoots" class="ml-auto flex flex-none items-center gap-2">
                            <Button variant="pill" type="button" @click="advanceStalled">{{ stalled.next.label }}</Button>
                            <Button v-if="galleriesHref" variant="outline" as-child>
                                <Link :href="galleriesHref">{{ $t("galleries.create") }}</Link>
                            </Button>
                        </div>
                    </Alert>

                    <!-- Master data across, each value with its proof underneath.
                         Where the project stands is the track in the head; the
                         stage stays here only as the field that changes it. -->
                    <div class="mt-5 mb-5 flex">
                        <BandCell
                            :label="$t('project.mainDateLabel')"
                            :sub="$t('board.shootCount', shoots.length)"
                        >
                            <BandValue plain>{{ day(figures.nextDate.value) }}</BandValue>
                        </BandCell>

                        <BandCell :label="$t('board.colValue')" :sub="figures.value.proof">
                            <BandValue v-if="figures.value.value !== null">{{ euro(figures.value.value) }}</BandValue>
                            <Button v-else-if="header.canManageQuotes" class="mt-[5px]" variant="outline" as-child>
                                <Link :href="`/auftraege/${header.id}/angebote`">{{ $t("project.quoteAdd") }}</Link>
                            </Button>
                            <BandValue v-else>—</BandValue>
                        </BandCell>

                        <BandCell
                            v-if="figures.open.value !== null"
                            :label="$t('project.openAmount')"
                            :sub="
                                figures.open.proof
                                    ? $t('project.instalments', Number(figures.open.proof))
                                    : null
                            "
                        >
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

                    <div class="flex flex-col gap-3">
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

                    <Card
                        v-model:editing="kontakteEditing"
                        :title="$t('contacts.title')"
                        :summary="kontakteSummary"
                        editable
                        @cancel="kontakte.reset()"
                        @save="saveKontakte"
                    >
                        <div
                            v-for="person in people"
                            :key="person.id"
                            class="flex items-center gap-3 py-[9px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
                        >
                            <Link
                                class="w-[168px] flex-none text-[13px] leading-[1.35] font-semibold tracking-[-0.012em] text-foreground no-underline underline-offset-[3px] hover:underline"
                                :href="`/kontakte/${person.id}`"
                            >
                                {{ person.name }}
                            </Link>
                            <span class="rounded-[var(--r-pill)] bg-sunk-2 px-[9px] py-0.5 text-[10.5px] leading-[1.4] tracking-[0.05em] uppercase text-ink-2">
                                {{ roleLabel(person.role) }}
                            </span>
                            <a v-if="person.email" class="text-[12.5px] text-action no-underline hover:underline" :href="`mailto:${person.email}`">{{ person.email }}</a>
                            <a v-if="person.phone" class="ml-auto text-[12.5px] tabular-nums text-action no-underline hover:underline" :href="`tel:${person.phone}`">{{ person.phone }}</a>
                        </div>

                        <template #edit>
                            <datalist id="rollen">
                                <option v-for="value in ROLE_SUGGESTIONS" :key="value" :value="value" />
                            </datalist>

                            <div
                                v-for="(person, index) in kontakte.people"
                                :key="person.id"
                                class="flex items-center gap-3 py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
                            >
                                <span class="w-[168px] flex-none text-[13px] leading-[1.35] font-semibold tracking-[-0.012em]">
                                    {{ people[index].name }}
                                </span>
                                <Field v-slot="{ id }" dense class="w-[168px] flex-none" :label="$t('leads.role')">
                                    <Input :id="id" v-model="person.role" type="text" list="rollen" />
                                </Field>
                            </div>
                        </template>
                    </Card>

                    <Card
                        v-model:editing="datesEditing"
                        :title="$t('lead.dates')"
                        :summary="datesSummary"
                        editable
                        @cancel="dates.reset()"
                        @save="saveDates"
                    >
                        <template v-if="header.canManageShoots" #action>
                            <Button variant="outline" as-child>
                                <Link :href="`/auftraege/${header.id}/shootings`">
                                    <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
                                    {{ $t("project.shootAdd") }}
                                </Link>
                            </Button>
                        </template>

                        <div class="[&>div]:py-[9px] [&>div]:shadow-none">
                            <ShootRow v-for="shoot in shoots" :key="shoot.id" :shoot="shoot" />
                        </div>

                        <template #edit>
                            <div class="max-w-[820px]">
                                <ShootFields
                                    v-for="shoot in dates.shoots"
                                    :key="shoot.id"
                                    :shoot="shoot"
                                />
                            </div>
                        </template>
                    </Card>

                    <Card :title="$t('project.tasks')" :summary="taskSummary" :default-open="false">
                        <div class="mb-1.5 flex items-center gap-2.5">
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
                                :staff-options="staffOptions"
                                :anchors="anchors ?? []"
                            />
                        </div>

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
                    </Card>
                    </div>

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

                    <Card
                        class="mt-10"
                        :title="$t('lead.notes')"
                        :summary="`${notes.length}`"
                        :default-open="false"
                    >
                        <NoteThread
                            :entries="notes"
                            :action="`/auftraege/${header.id}/notizen`"
                        />
                    </Card>
        </ProjectShell>
    </AdminLayout>
</template>
