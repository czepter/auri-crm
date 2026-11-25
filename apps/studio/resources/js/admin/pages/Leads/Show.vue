<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { completeTask } from "@/admin/lib/undoable";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import Card from "@/ui/page/Card.vue";
import ContactTypeahead from "@/admin/components/crm/ContactTypeahead.vue";
import LeadQueue from "@/admin/components/crm/LeadQueue.vue";
import LeadRail from "@/admin/components/crm/LeadRail.vue";
import CappedList from "@/admin/components/crm/CappedList.vue";
import Composer from "@/admin/components/crm/Composer.vue";
import EmptyTiles from "@/admin/components/crm/EmptyTiles.vue";
import MetricBand from "@/admin/components/crm/MetricBand.vue";
import type { BandMetric } from "@/admin/components/crm/MetricBand.vue";
import Panel from "@/admin/components/crm/Panel.vue";
import ProjectChain from "@/admin/components/crm/ProjectChain.vue";
import type { ChainRow } from "@/admin/components/crm/ProjectChain.vue";
import QuoteList from "@/admin/components/quote/QuoteList.vue";
import NoteThread from "@/admin/components/crm/NoteThread.vue";
import MessageThread from "@/admin/components/crm/MessageThread.vue";
import ProjectShell from "@/admin/components/crm/ProjectShell.vue";
import ShootFields from "@/admin/components/crm/ShootFields.vue";
import ShootRow from "@/admin/components/crm/ShootRow.vue";
import { Button } from "@/ui/button";
import { ChoiceGroup } from "@/ui/choice-group";
import { DatePicker } from "@/ui/date-picker";
import { Field } from "@/ui/field";
import { Input } from "@/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/ui/select";
import { Textarea } from "@/ui/textarea";
import { AlertDialog, AlertDialogAction, AlertDialogBody, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from "@/ui/alert-dialog";
import { formatEurosRounded } from "@/lib/money";
import { DropdownMenuItem } from "@/ui/dropdown-menu";
import { groupEntries } from "@/admin/lib/groupEntries";
import { stepOnArrow } from "@/admin/lib/queueStep";
import { ROLE_SUGGESTIONS, roleLabel } from "@/admin/lib/roles";
import { dayWithWeekday as day, daysUntil } from "@/admin/lib/dates";
import { bandMetrics, isBare } from "@/admin/lib/projectOverview";
import { dayAndMonth } from "@/admin/lib/shoots";
import { useLiveProps } from "@/admin/lib/liveReload";

const props = defineProps<{
    queue: { key: string; rows: App.Crm.Data.LeadQueueRowData[] }[];
    lead: {
        id: string;
        projectId: string;
        name: string;
        type: string;
        typeOther: string | null;
        typeLabel: string;
        source: string | null;
        sourceOther: string | null;
        sourceLabel: string | null;
        stage: string;
        stageLabel: string;
        inquiryText: string | null;
        capturedVia: string | null;
        capturedAt: string;
        lostReason: string | null;
        outcomeLabel: string | null;
    };
    people: App.Crm.Data.CasePersonData[];
    shoots: App.Crm.Data.ShootData[];
    quote: App.Quotes.Data.QuoteData | null;
    supersededQuotes: { id: string; number: string | null; net: number }[];
    tasks: App.Crm.Data.TaskRowData[];
    thread: App.Crm.Data.ThreadEntryData[];
    types: { value: string; label: string }[];
    sources: { value: string; label: string }[];
    presets: { type: string[]; source: string[] };
    week: { date: string; what: string; projectId: string }[];
    conflicts: App.Crm.Data.DateConflictData[];
    stages: { value: string; label: string; anchor: string | null }[];
    tab: "overview" | "quotes" | "messages" | "info";
    header: App.Crm.Screens.CaseHeaderData;
    conversation: App.Engagement.Data.ThreadData | null;
    quotes: App.Quotes.Data.QuoteData[];
    payments: { number: string | null; rows: { label: string; amount: number }[] } | null;
}>();
useLiveProps(["header", "conversation", "quotes", "payments", "queue", "lead", "people", "shoots", "quote", "supersededQuotes", "tasks", "thread", "week", "conflicts", "stages", "types"]);

const quoteTab = computed(() => `/anfragen/${props.lead.id}/angebot`);
const newQuote = computed(() => `/angebote/neu?projekt=${props.lead.projectId}`);

// The inquiry is the conversation's first line, written before there was a
// thread to hold it.
const inquiry = computed(() =>
    props.lead.inquiryText === null
        ? null
        : {
              author: (props.people.find((person) => person.role === "primary") ?? props.people[0])?.name ?? "",
              at: props.lead.capturedAt,
              body: props.lead.inquiryText,
          },
);

const reachable = ref<App.Crm.Data.LeadQueueRowData[]>([]);

function onKey(event: KeyboardEvent) {
    stepOnArrow(event, reachable.value, "/anfragen");
}

onMounted(() => addEventListener("keydown", onKey));
onUnmounted(() => removeEventListener("keydown", onKey));

/*
 * Kein Bearbeiten-Modus über den ganzen Bildschirm mehr: jede Karte hält ihr
 * eigenes Formular und schickt ihre eigene Teilmenge. Wer die Kontakte
 * speichert, hat den Anfragetext nicht in der Hand.
 */
const textEditing = ref(false);
const kontakteEditing = ref(false);
const termineEditing = ref(false);

const anfrage = useForm({
    inquiry_text: props.lead.inquiryText ?? "",
});

// Typ und Quelle stehen in der Seitenspalte und speichern bei jeder Änderung:
// zwei Auswahlen brauchen keinen Bearbeiten-Modus.
const details = useForm({
    type: props.lead.type,
    type_other: props.lead.typeOther ?? "",
    source: props.lead.source,
    source_other: props.lead.sourceOther ?? "",
});

function saveDetail(field: "type" | "source") {
    details
        .transform((data) => ({
            [field]: data[field],
            [`${field}_other`]: data[field] === "other" ? blank(data[`${field}_other`]) : null,
        }))
        .patch(`/anfragen/${props.lead.id}`, { preserveScroll: true });
}

const kontakte = useForm({
    people: props.people.map((person) => ({
        id: person.id,
        name: person.name,
        role: person.role,
        email: person.email ?? "",
        phone: person.phone ?? "",
    })),
});

const termine = useForm({
    shoots: props.shoots.map((shoot) => ({
        id: shoot.id,
        date: shoot.date ?? "",
        venue_name: shoot.venueName ?? "",
        venue_city: shoot.venueCity ?? "",
        call_time: shoot.callTime ?? "",
        end_time: shoot.endTime ?? "",
    })),
});

const blank = (value: string) => (value === "" ? null : value);

function saveAnfrage() {
    anfrage
        .transform((data) => ({ ...data, inquiry_text: blank(data.inquiry_text) }))
        .patch(`/anfragen/${props.lead.id}`, {
            preserveScroll: true,
            onSuccess: () => (textEditing.value = false),
        });
}

function saveKontakte() {
    kontakte
        .transform((data) => ({
            people: data.people.map((person) => ({
                ...person,
                email: blank(person.email),
                phone: blank(person.phone),
            })),
        }))
        .patch(`/anfragen/${props.lead.id}`, {
            preserveScroll: true,
            onSuccess: () => (kontakteEditing.value = false),
        });
}

function saveTermine() {
    termine
        .transform((data) => ({
            shoots: data.shoots.map((shoot) => ({
                ...shoot,
                date: blank(shoot.date),
                call_time: blank(shoot.call_time),
                end_time: blank(shoot.end_time),
            })),
        }))
        .patch(`/anfragen/${props.lead.id}`, {
            preserveScroll: true,
            onSuccess: () => (termineEditing.value = false),
        });
}

// Wer sonst noch am Fall hängt. Der Name sucht bestehende Kontakte, bevor er
// einen neuen anlegt — die Reihenfolge ist der Duplikatschutz.
const adding = ref(false);
const picked = ref<App.Crm.Data.ContactSuggestionData | null>(null);
const newContact = useForm({ contact_id: "", name: "", email: "", phone: "", role: "" });

function pick(contact: App.Crm.Data.ContactSuggestionData | null) {
    picked.value = contact;
    newContact.contact_id = contact?.id ?? "";
    newContact.name = contact?.name ?? "";
}

function addContact() {
    newContact.transform((data) => ({
        ...data,
        contact_id: blank(data.contact_id),
        email: blank(data.email),
        phone: blank(data.phone),
        role: blank(data.role),
    })).post(`/anfragen/${props.lead.id}/kontakte`, {
        preserveScroll: true,
        onSuccess: () => {
            newContact.reset();
            picked.value = null;
            adding.value = false;
        },
    });
}

function removeContact(id: string) {
    router.delete(`/anfragen/${props.lead.id}/kontakte/${id}`, { preserveScroll: true });
}

function addShoot() {
    router.post(`/auftraege/${props.lead.projectId}/shootings`, {}, { preserveScroll: true });
}

function removeShoot(id: string) {
    router.delete(`/shootings/${id}`, { preserveScroll: true });
}

const notes = computed(() => props.thread.filter((entry) => !entry.system).reverse());
const events = computed(() => groupEntries(props.thread.filter((entry) => entry.system).reverse()));

// Same rule as the board: "booked" writes the booking, so it asks first.
const booking = ref(false);

function move(stage: string) {
    if (stage === "booked") {
        booking.value = true;

        return;
    }

    router.post(`/anfragen/${props.lead.id}/stufe`, { stage }, { preserveScroll: true });
}

function book() {
    router.post(`/anfragen/${props.lead.id}/stufe`, { stage: "booked" }, { preserveScroll: true });
    booking.value = false;
}

const task = useForm({ title: "", audience: "internal", due_on: "" });

function addTask() {
    task.post(`/auftraege/${props.lead.projectId}/aufgaben`, {
        preserveScroll: true,
        onSuccess: () => task.reset(),
    });
}

const { t } = useI18n();
const page = usePage();
const byline = (author: string | null) =>
    page.props.soloStudio && author === page.props.auth.user?.name ? null : author;
const base = computed(() => `/auftraege/${props.lead.id}`);

const euro = (minor: string | number) => formatEurosRounded(Number(minor));
const open = computed(() => props.tasks.filter((row) => !row.isDone));
const done = computed(() => props.tasks.filter((row) => row.isDone));
const showDone = ref(false);
const taskRows = computed(() => (showDone.value ? done.value : open.value));
const phaseItems = computed(() => props.stages.filter((option) => option.value !== props.lead.stage));
const createQuote = computed(() => (!props.quote && props.header.canManageQuotes ? newQuote.value : null));
const addingTask = ref(false);

function complete(row: App.Crm.Data.TaskRowData) {
    completeTask(row, { done: t('common.done'), undo: t('common.undo') });
}

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
        label: t("quotes.validUntil"),
        value: props.quote?.validUntil ? (dayAndMonth(props.quote.validUntil) ?? null) : null,
    },
    {
        label: t("project.openTasks"),
        value: open.value.length ? String(open.value.length) : null,
    },
]);
const hasBand = computed(() => bandMetrics(metrics.value).length > 0);

const chain = computed<ChainRow[]>(() => [
    {
        key: "inquiry",
        label: t("project.lead"),
        title: props.lead.typeLabel,
        meta: [day(props.lead.capturedAt), props.lead.sourceLabel].filter(Boolean).join(" · "),
    },
    props.quote
        ? {
              key: "quote",
              label: t("lead.quote"),
              title: props.quote.number ?? t("lead.quote"),
              meta: [props.quote.statusLabel, props.quote.validUntil ? t("lead.validUntil", { date: day(props.quote.validUntil) }) : null].filter(Boolean).join(" · "),
              amount: euro(props.quote.net),
              href: `/angebote/${props.quote.id}`,
              action: t("project.view"),
          }
        : {
              key: "quote",
              label: t("lead.quote"),
              title: t("lead.quote"),
              href: createQuote.value,
              action: createQuote.value ? t("project.chainQuoteCreate") : null,
          },
]);

const bare = computed(() =>
    isBare({ shoots: props.shoots.length, quote: props.quote !== null, tasks: props.tasks.length + notes.value.length, payments: 0 }),
);

const tiles = computed(() => [
    { key: "shoot" as const, label: t("lead.tile.date"), href: `${base.value}/shootings` },
    { key: "quote" as const, label: t("lead.quote"), href: newQuote.value },
    { key: "task" as const, label: t("lead.tile.task"), href: `${base.value}/aufgaben` },
    { key: "note" as const, label: t("lead.tile.note"), href: `${base.value}/notizen` },
]);

const weekHint = (shoot: App.Crm.Data.ShootData) => {
    if (!shoot.isPrimary || !shoot.date) return null;
    if (props.conflicts.length) return { warn: true, text: `${t("lead.conflicts")}: ${props.conflicts.map((c) => c.label ?? c.projectName).join(", ")}` };

    return { warn: false, text: props.week.length ? t("lead.weekCount", props.week.length) : t("lead.weekClear") };
};
</script>

<template>
    <AdminLayout active="anfragen">
        <ProjectShell :header="header" :active="tab" :people="false">
            <template #left>
                <LeadQueue :groups="queue" @update:rows="reachable = $event" />
            </template>

            <template #rail><div /></template>

            <template v-if="!lead.outcomeLabel && phaseItems.length" #menu>
                <DropdownMenuItem v-for="option in phaseItems" :key="option.value" @select="move(option.value)">
                    {{ $t("project.switchTo", { stage: option.label }) }}
                </DropdownMenuItem>
            </template>

            <AlertDialog v-model:open="booking">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ $t("board.bookTitle") }}</AlertDialogTitle>
                    </AlertDialogHeader>
                    <AlertDialogBody>
                        <p class="m-0">{{ $t("board.bookConsequence") }}</p>
                    </AlertDialogBody>
                    <AlertDialogFooter>
                        <AlertDialogAction @click="book">{{ $t("board.book") }}</AlertDialogAction>
                        <AlertDialogCancel>{{ $t("common.cancel") }}</AlertDialogCancel>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <template v-if="tab === 'overview'">
                <MetricBand v-if="hasBand" :metrics="metrics" :columns="bandMetrics(metrics).length" />

                <EmptyTiles v-if="bare" class="mt-4" :tiles="tiles" />

                <div class="mt-4 grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] items-start gap-4 max-[900px]:grid-cols-1">
                    <div class="flex flex-col gap-4">
                        <Panel :title="$t('project.lead')">
                            <template v-if="header.canManageShoots && lead.inquiryText" #action>
                                <Button class="ml-auto" variant="secondary" type="button" @click="textEditing = !textEditing">
                                    {{ $t("lead.edit") }}
                                </Button>
                            </template>

                            <p v-if="lead.inquiryText && !textEditing" class="m-0 max-w-[68ch] whitespace-pre-line">{{ lead.inquiryText }}</p>
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

                            <div class="mt-4 grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
                                <Field v-slot="{ id }" dense :label="$t('leads.type')" :error="details.errors.type">
                                    <Select
                                        v-model="details.type"
                                        :disabled="!header.canManageShoots"
                                        @update:model-value="saveDetail('type')"
                                    >
                                        <SelectTrigger :id="id" class="min-h-10"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        v-if="details.type === 'other'"
                                        v-model="details.type_other"
                                        class="mt-2 min-h-10"
                                        type="text"
                                        list="type-presets"
                                        :disabled="!header.canManageShoots"
                                        @change="saveDetail('type')"
                                    />
                                    <datalist id="type-presets">
                                        <option v-for="value in presets.type" :key="value" :value="value" />
                                    </datalist>
                                </Field>

                                <Field v-slot="{ id }" dense :label="$t('leads.source')" :error="details.errors.source">
                                    <Select
                                        v-model="details.source"
                                        :disabled="!header.canManageShoots"
                                        @update:model-value="saveDetail('source')"
                                    >
                                        <SelectTrigger :id="id" class="min-h-10"><SelectValue placeholder="—" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="option in sources" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        v-if="details.source === 'other'"
                                        v-model="details.source_other"
                                        class="mt-2 min-h-10"
                                        type="text"
                                        list="source-presets"
                                        :disabled="!header.canManageShoots"
                                        @change="saveDetail('source')"
                                    />
                                    <datalist id="source-presets">
                                        <option v-for="value in presets.source" :key="value" :value="value" />
                                    </datalist>
                                </Field>
                            </div>
                        </Panel>

                        <Panel v-if="!bare" id="aufgaben" :title="$t('lead.tasks')">
                            <template v-if="header.canManageTasks" #action>
                                <Button class="ml-auto" variant="secondary" type="button" @click="addingTask = !addingTask">
                                    {{ $t("lead.taskTitle") }}
                                </Button>
                            </template>

                            <form
                                v-if="addingTask"
                                class="mb-3 flex flex-col rounded-md border border-rule focus-within:border-ring"
                                @submit.prevent="addTask"
                            >
                                <Input
                                    v-model="task.title"
                                    class="min-h-10 border-0 bg-transparent px-3.5 text-small"
                                    type="text"
                                    :placeholder="$t('lead.taskTitle')"
                                    :aria-label="$t('lead.taskTitle')"
                                    required
                                />
                                <div class="flex items-center gap-4 px-3 pb-2.5">
                                    <DatePicker v-model="task.due_on" class="w-[150px] [&_input]:h-10 [&_input]:border-0 [&_input]:bg-transparent [&_input]:px-0" />
                                    <Select v-model="task.audience">
                                        <SelectTrigger class="h-10 w-[110px] border-0 bg-transparent px-0 text-ink-2"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="internal">{{ $t("lead.audienceInternal") }}</SelectItem>
                                            <SelectItem value="client">{{ $t("lead.audienceClient") }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button class="ml-auto" variant="default" type="submit" :disabled="task.processing">
                                        {{ $t("lead.addTask") }}
                                    </Button>
                                </div>
                            </form>

                            <ChoiceGroup
                                class="mb-2 w-fit"
                                :model-value="showDone ? 'done' : 'open'"
                                :options="[
                                    { value: 'open', label: $t('project.tasksOpen', { count: open.length }) },
                                    { value: 'done', label: $t('project.tasksDone', { count: done.length }) },
                                ]"
                                @update:model-value="showDone = $event === 'done'"
                            />

                            <CappedList v-slot="{ item: row }" :items="taskRows" :limit="3" :more-href="`${base}/aufgaben`">
                                <div class="flex min-h-11 items-center gap-3 border-t border-rule first:border-t-0">
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

                        <Panel v-if="!bare" :title="$t('lead.dates')" :count="shoots.length > 3 ? shoots.length : null">
                            <template v-if="header.canManageShoots" #action>
                                <div class="ml-auto flex items-center gap-2">
                                    <Button
                                        v-if="shoots.length && !termineEditing"
                                        class="px-4"
                                        variant="ghost"
                                        type="button"
                                        @click="termineEditing = true"
                                    >{{ $t("lead.edit") }}</Button>
                                    <Button variant="secondary" type="button" @click="addShoot">
                                        {{ $t("lead.dateAdd") }}
                                    </Button>
                                </div>
                            </template>

                            <template v-if="termineEditing">
                                <div v-for="(shoot, index) in termine.shoots" :key="shoot.id" class="flex items-start gap-3">
                                    <div class="min-w-0 flex-1">
                                        <ShootFields :shoot="shoot" />
                                    </div>
                                    <Button v-if="index > 0" variant="ghost" class="mt-5 text-small" type="button" @click="removeShoot(shoot.id)">
                                        {{ $t("common.remove") }}
                                    </Button>
                                </div>
                                <div class="mt-3 flex gap-2">
                                    <Button variant="default" type="button" :disabled="termine.processing" @click="saveTermine">{{ $t("common.save") }}</Button>
                                    <Button variant="outline" type="button" @click="termine.reset(); termineEditing = false">{{ $t("common.cancel") }}</Button>
                                </div>
                            </template>
                            <CappedList v-else v-slot="{ item: shoot }" :items="shoots" :limit="3" :more-href="`${base}/shootings`">
                                <div class="grid grid-cols-[140px_minmax(0,1fr)] gap-4 py-3 border-t border-rule first:border-t-0 max-[640px]:grid-cols-1 max-[640px]:gap-1">
                                    <div>
                                        <b class="font-semibold">{{ day(shoot.date) }}</b>
                                        <div class="text-small text-ink-2">{{ shoot.statusLabel }}</div>
                                    </div>
                                    <div>
                                        <div>{{ shoot.label ?? shoot.kind }}</div>
                                        <div v-if="shoot.venue" class="text-small text-ink-2">{{ shoot.venue }}</div>
                                        <div
                                            v-if="weekHint(shoot)"
                                            class="text-small"
                                            :class="weekHint(shoot)?.warn ? 'text-due' : 'text-ok'"
                                        >{{ weekHint(shoot)?.text }}</div>
                                    </div>
                                </div>
                            </CappedList>
                        </Panel>

                        <ProjectChain v-if="chain.length" :title="$t('project.chain')" :rows="chain" />
                    </div>

                    <div class="flex flex-col gap-4">
                        <Panel :title="$t('contacts.title')">
                            <template v-if="header.canManageShoots && !kontakteEditing" #action>
                                <Button class="ml-auto px-4" variant="ghost" type="button" @click="kontakteEditing = true">
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
                                    class="flex items-end gap-3 py-2 border-t border-rule first:border-t-0 max-[640px]:flex-wrap"
                                >
                                    <Field v-slot="{ id }" dense class="min-w-[148px] flex-1" :label="$t('leads.name')">
                                        <Input :id="id" v-model="person.name" type="text" />
                                    </Field>
                                    <Field v-slot="{ id }" dense class="w-[148px] flex-none" :label="$t('leads.role')">
                                        <Input :id="id" v-model="person.role" type="text" list="rollen" />
                                    </Field>
                                    <Field v-slot="{ id }" dense class="min-w-[148px] flex-1" :label="$t('leads.email')">
                                        <Input :id="id" v-model="person.email" type="email" inputmode="email" />
                                    </Field>
                                    <Field v-slot="{ id }" dense class="w-[148px] flex-none" :label="$t('leads.phone')">
                                        <Input :id="id" v-model="person.phone" type="tel" inputmode="tel" />
                                    </Field>
                                    <Button v-if="index > 0" variant="ghost" class="text-small" type="button" @click="removeContact(person.id)">
                                        {{ $t("common.remove") }}
                                    </Button>
                                </div>

                                <div v-if="adding" class="mt-3 flex flex-col gap-3 pt-3 border-t border-rule">
                                    <ContactTypeahead :model-value="picked" @update:model-value="pick" @create-new="(term) => { pick(null); newContact.name = term }" />

                                    <div v-if="newContact.name && !picked" class="flex gap-3 max-[640px]:flex-wrap">
                                        <Field v-slot="{ id }" dense class="min-w-[148px] flex-1" :label="$t('leads.name')">
                                            <Input :id="id" v-model="newContact.name" type="text" />
                                        </Field>
                                        <Field v-slot="{ id }" dense class="min-w-[148px] flex-1" :label="$t('leads.email')">
                                            <Input :id="id" v-model="newContact.email" type="email" />
                                        </Field>
                                        <Field v-slot="{ id }" dense class="w-[148px] flex-none" :label="$t('leads.phone')">
                                            <Input :id="id" v-model="newContact.phone" type="tel" />
                                        </Field>
                                    </div>

                                    <div class="flex items-end gap-3">
                                        <Field v-slot="{ id }" dense class="w-[148px] flex-none" :label="$t('leads.role')">
                                            <Input :id="id" v-model="newContact.role" type="text" list="rollen" />
                                        </Field>
                                        <Button
                                            variant="secondary"
                                            type="button"
                                            :disabled="(!newContact.contact_id && !newContact.name) || newContact.processing"
                                            @click="addContact"
                                        >
                                            {{ $t("lead.contactAdd") }}
                                        </Button>
                                    </div>
                                </div>

                                <Button v-else class="mt-3" variant="outline" type="button" @click="adding = true">
                                    {{ $t("lead.contactAdd") }}
                                </Button>

                                <div class="mt-3 flex gap-2">
                                    <Button variant="default" type="button" :disabled="kontakte.processing" @click="saveKontakte">{{ $t("common.save") }}</Button>
                                    <Button variant="outline" type="button" @click="kontakte.reset(); kontakteEditing = false">{{ $t("common.cancel") }}</Button>
                                </div>
                            </template>

                            <template v-else>
                                <div
                                    v-for="person in people"
                                    :key="person.id"
                                    class="flex min-h-12 flex-wrap items-center gap-x-3 gap-y-1 py-2 border-t border-rule first:border-t-0"
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
                                    <Button v-else variant="secondary" class="ml-auto" as-child>
                                        <Link :href="`/kontakte/${person.id}`">{{ $t("project.contactDetails") }}</Link>
                                    </Button>
                                </div>
                            </template>
                        </Panel>

                        <Panel v-if="!bare" :title="$t('lead.notes')" :count="notes.length > 3 ? notes.length : null">
                            <NoteThread
                                compact
                                :entries="notes"
                                :action="`${base}/notizen`"
                                :limit="3"
                                :more-href="`${base}/notizen`"
                                :can-manage="header.canManageNotes"
                            />
                        </Panel>

                        <Panel v-if="events.length" :title="$t('project.history')" :count="events.length > 5 ? events.length : null">
                            <CappedList v-slot="{ item: entry, index }" :items="events" :limit="5" :more-href="`${base}/verlauf`">
                                <div class="py-1.5" :class="index === 0 && 'text-foreground'">
                                    <b class="block font-semibold">{{ entry.count > 1 ? $t("project.historyCount", { body: entry.body, count: entry.count }) : entry.body }}</b>
                                    <span class="text-small text-ink-2">
                                        {{ [entry.at ? day(entry.at) : null, byline(entry.author)].filter(Boolean).join(" · ") }}
                                    </span>
                                </div>
                            </CappedList>
                        </Panel>
                    </div>
                </div>
            </template>

            <QuoteList
                v-else-if="tab === 'quotes'"
                :project-id="lead.projectId"
                :can-manage-quotes="header.canManageQuotes"
                :quotes="quotes"
                :payments="payments"
            />

            <LeadRail
                v-else-if="tab === 'info'"
                :type-label="lead.typeLabel"
                :source-label="lead.sourceLabel"
                :dated="header.primaryDate !== null"
                :week="week"
                :conflicts="conflicts"
                :thread="thread"
            />

            <Card v-else :title="$t('project.messages')">
                <MessageThread
                    :project-id="lead.projectId"
                    :thread="conversation"
                    :can-manage="header.canManageMessages"
                    :inquiry="inquiry"
                />
            </Card>
        </ProjectShell>
    </AdminLayout>
</template>
