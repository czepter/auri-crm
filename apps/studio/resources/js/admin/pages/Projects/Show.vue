<script setup lang="ts">
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import { Link, router, useForm } from "@inertiajs/vue3";
import { Pencil } from "lucide-vue-next";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import { Alert, AlertDescription, AlertTitle } from "@/ui/alert";
import { Button } from "@/ui/button";
import LineItems from "@/admin/components/LineItems.vue";
import NoteThread from "@/admin/components/NoteThread.vue";
import QuoteSummary from "@/admin/components/QuoteSummary.vue";
import ShootFields from "@/admin/components/ShootFields.vue";
import ShootRow from "@/admin/components/ShootRow.vue";
import { Field } from "@/ui/field";
import { Input } from "@/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/ui/select";
import { Textarea } from "@/ui/textarea";
import ProjectFields from "@/admin/components/ProjectFields.vue";
import type { ProjectFieldValues } from "@/admin/components/ProjectFields.vue";
import ProjectShell from "@/admin/components/ProjectShell.vue";
import TaskCreateDialog from "@/admin/components/TaskCreateDialog.vue";
import Card from "@/ui/page/Card.vue";
import { ROLE_SUGGESTIONS, roleLabel } from "@/admin/lib/roles";
import Section from "@/ui/page/Section.vue";
import { formatEurosRounded } from "@/admin/lib/money";
import { quoteChipClass } from "@/admin/lib/quoteDisplay";
import { dayWithWeekday as day } from "@/admin/lib/dates";

type ProjectFieldDefinition = InstanceType<typeof ProjectFields>["$props"]["fields"][number];

const props = defineProps<{
    header: App.Crm.Data.CaseHeaderData;
    shoots: App.Crm.Data.ShootData[];
    thread: App.Crm.Data.ThreadEntryData[];
    tasks: App.Crm.Data.TaskRowData[];
    shootOptions: { value: string; label: string }[];
    staffOptions: { value: string; label: string }[];
    // Kommt von ProjectController@show; bis dahin bleibt die Ankerliste leer.
    anchors?: { value: string; label: string; group: string; on: string | null }[];
    quote: App.Quotes.Data.QuoteData | null;
    supersededQuotes: { id: string; number: string | null; net: number }[];
    packages: { id: string; name: string; itemCount: number; total: number }[];
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
    people: App.Crm.Data.CasePersonData[];
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
const anfrageEditing = ref(false);
const detailsEditing = ref(false);
const datesEditing = ref(false);

const details = useForm({
    name: props.header.name,
    stage: props.stage,
    source: props.source,
    source_other: props.sourceOther ?? "",
    fields: { ...props.fieldValues },
});

const anfrage = useForm({ inquiry_text: props.inquiry?.text ?? "" });

function saveAnfrage() {
    anfrage
        .transform((data) => ({ ...data, inquiry_text: blank(data.inquiry_text) }))
        .patch(`/anfragen/${props.header.id}`, {
            preserveScroll: true,
            onSuccess: () => (anfrageEditing.value = false),
        });
}

const anfrageSummary = computed(() =>
    [props.inquiry?.capturedVia, props.inquiry ? day(props.inquiry.capturedAt) : null].filter(Boolean).join(" · "),
);

const quoteSummary = computed(() => (props.quote === null ? "" : euro(props.quote.net)));

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

function complete(row: App.Crm.Data.TaskRowData) {
    router.post(`/aufgaben/${row.id}/erledigt`, {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout active="projekte">
        <ProjectShell :header="header" active="overview" :people="false">

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
                            <Button variant="pill" type="button" @click="advanceStalled">{{ stalled.next.label }}</Button>
                            <Button v-if="galleriesHref" variant="outline" as-child>
                                <Link :href="galleriesHref">{{ $t("galleries.create") }}</Link>
                            </Button>
                        </div>
                    </Alert>

                    <div class="mt-5 flex flex-col gap-3">
                    <Card
                        v-if="inquiry"
                        v-model:editing="anfrageEditing"
                        :title="$t('project.lead')"
                        :summary="anfrageSummary"
                        editable
                        @cancel="anfrage.reset()"
                        @save="saveAnfrage"
                    >
                        <p class="m-0 max-w-[58ch] text-body leading-[1.55] whitespace-pre-line">
                            {{ inquiry.text ?? $t("lead.noText") }}
                        </p>
                        <p class="mt-2 mb-0 text-small text-ink-2">
                            {{ [inquiry.typeLabel, inquiry.sourceLabel].filter(Boolean).join(" · ") }}
                        </p>

                        <template #edit>
                            <Field v-slot="{ id }" :label="$t('leads.inquiryText')" :error="anfrage.errors.inquiry_text">
                                <Textarea :id="id" v-model="anfrage.inquiry_text" rows="6" />
                            </Field>
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
                                class="w-[168px] flex-none text-small leading-[1.35] font-semibold tracking-[-0.012em] text-foreground no-underline underline-offset-[3px] hover:underline"
                                :href="`/kontakte/${person.id}`"
                            >
                                {{ person.name }}
                            </Link>
                            <span class="rounded-[var(--r-sm)] bg-sunk-2 px-2 py-0.5 text-small leading-[1.4] font-medium text-ink-2">
                                {{ roleLabel(person.role) }}
                            </span>
                            <a v-if="person.email" class="text-small text-action no-underline hover:underline" :href="`mailto:${person.email}`">{{ person.email }}</a>
                            <a v-if="person.phone" class="ml-auto text-small tabular-nums text-action no-underline hover:underline" :href="`tel:${person.phone}`">{{ person.phone }}</a>
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
                                <span class="w-[168px] flex-none text-small leading-[1.35] font-semibold tracking-[-0.012em]">
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
                            <ShootRow v-for="shoot in shoots" :key="shoot.id" :shoot="shoot" :manageable="header.canManageShoots" />
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

                    <Card :title="$t('lead.quote')" :summary="quoteSummary" :default-open="quote !== null">
                        <template v-if="quote" #meta>
                            <span v-if="quote.number" class="text-ui leading-[1.2] tracking-[-0.015em] text-ink-2 tabular-nums">{{ quote.number }}</span>
                            <span :class="['chip', quoteChipClass(quote.status)]">{{ quote.statusLabel }}</span>
                        </template>
                        <template v-if="header.canManageQuotes" #action>
                            <Link
                                v-if="quote"
                                class="grid size-7 place-items-center rounded-[var(--r-sm)] text-ink-2 hover:bg-sunk hover:text-foreground"
                                :href="`/angebote/${quote.id}`"
                                :aria-label="$t('common.edit')"
                                :title="$t('common.edit')"
                            ><Pencil class="size-[15px]" /></Link>
                            <Link
                                v-else
                                class="text-small tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
                                :href="`/angebote/neu?projekt=${header.id}`"
                            >{{ $t("quotes.create") }}</Link>
                        </template>
                        <QuoteSummary
                            v-if="quote"
                            :quote="quote"
                            :superseded="supersededQuotes"
                            :valid-until-label="quote.validUntil ? $t('lead.validUntil', { date: day(quote.validUntil) }) : null"
                        />
                        <div v-else class="flex flex-wrap gap-2">
                            <Link
                                v-for="pkg in packages"
                                :key="pkg.id"
                                class="cursor-pointer rounded-[var(--r-pill)] border-0 bg-sunk px-[11px] py-[5px] text-small leading-[1.4] font-normal text-ink-2 hover:bg-sunk-2"
                                :href="`/auftraege/${header.id}/angebote`"
                                method="post"
                                as="button"
                                :data="{ package: pkg.id }"
                            >
                                <b>{{ pkg.name }}</b>
                                {{ $t("packages.itemCount", pkg.itemCount) }} ·
                                {{ formatEurosRounded(pkg.total) }}
                            </Link>
                        </div>
                    </Card>

                    <Card :title="$t('project.tasks')" :summary="taskSummary" :default-open="overdue > 0">
                        <div class="mb-1.5 flex items-center gap-2.5">
                            <span class="inline-flex gap-0.5 rounded-[var(--r-pill)] bg-sunk-2 p-0.5">
                                <button
                                    class="cursor-pointer rounded-[var(--r-pill)] px-[11px] py-1 text-small leading-none"
                                    :class="showDone ? 'text-ink-2' : 'bg-paper font-semibold'"
                                    type="button"
                                    @click="showDone = false"
                                >
                                    {{ $t("project.tasksOpen", { count: open.length }) }}
                                </button>
                                <button
                                    class="cursor-pointer rounded-[var(--r-pill)] px-[11px] py-1 text-small leading-none"
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
                            <span class="text-ui leading-[1.47]" :class="row.isDone && 'text-ink-2'">{{
                                row.title
                            }}</span>
                            <span
                                class="text-small leading-[1.47] text-ink-2"
                            >{{ row.audienceLabel }}</span>
                            <span v-if="row.shootLabel" class="text-small leading-[1.47] text-faint">{{
                                row.shootLabel
                            }}</span>
                            <span
                                class="ml-auto text-small leading-[1.47] tabular-nums"
                                :class="row.isOverdue && !row.isDone ? 'text-action' : 'text-ink-2'"
                            >{{ row.dueOn ? day(row.dueOn) : "" }}</span>
                        </div>
                    </Card>
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
                    <Section v-if="scope.items.length" :title="$t('project.scope')">
                        <LineItems
                            class="[&>div]:py-[9px] [&>div]:shadow-none"
                            :items="scope.items"
                        >
                            <template #note="{ item }">
                                <span v-if="item.state" class="ml-[3px] text-small text-action">{{
                                    $t(`project.scope_${item.state}`)
                                }}</span>
                            </template>
                            <template #priceNote="{ item }">
                                <span
                                    v-if="item.state === 'changed'"
                                    class="mt-0.5 block text-small font-normal text-action"
                                >{{ euro(item.wasUnitPrice) }}</span>
                            </template>
                        </LineItems>

                        <p
                            v-if="scope.delta"
                            class="mt-[7px] text-small text-ink-2 [&_em]:not-italic [&_em]:text-action"
                        >
                            <em>{{
                                $t("project.scopeDelta", { amount: euro(Math.abs(scope.delta)) })
                            }}</em>
                            <Link
                                class="ml-3 text-small tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
                                :href="`/auftraege/${header.id}/angebote`"
                            >
                                {{ $t("project.sendAddendum") }}
                            </Link>
                        </p>
                    </Section>

                    <Card
                        class="mt-3"
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
