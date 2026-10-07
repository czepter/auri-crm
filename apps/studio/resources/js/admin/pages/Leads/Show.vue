<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import Card from "@/ui/page/Card.vue";
import ContactTypeahead from "@/admin/components/ContactTypeahead.vue";
import LeadQueue from "@/admin/components/LeadQueue.vue";
import NoteThread from "@/admin/components/NoteThread.vue";
import QuoteSummary from "@/admin/components/QuoteSummary.vue";
import QuoteList from "@/admin/components/quote/QuoteList.vue";
import MessageThread from "@/admin/components/MessageThread.vue";
import ProjectShell from "@/admin/components/ProjectShell.vue";
import RailHistory from "@/admin/components/RailHistory.vue";
import ShootFields from "@/admin/components/ShootFields.vue";
import ShootRow from "@/admin/components/ShootRow.vue";
import { Button } from "@/ui/button";
import { DatePicker } from "@/ui/date-picker";
import { Field } from "@/ui/field";
import { Input } from "@/ui/input";
import Rail from "@/ui/page/Rail.vue";
import RailSection from "@/ui/page/RailSection.vue";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/ui/select";
import { Textarea } from "@/ui/textarea";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/ui/dropdown-menu";
import { ChevronDown } from "lucide-vue-next";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from "@/ui/alert-dialog";
import { formatEurosRounded } from "@/admin/lib/money";
import { quoteChipClass } from "@/admin/lib/quoteDisplay";
import { ROLE_SUGGESTIONS, roleLabel } from "@/admin/lib/roles";
import { dayWithWeekday as day } from "@/admin/lib/dates";

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
    packages: { id: string; name: string; itemCount: number; total: number }[];
    tasks: App.Crm.Data.TaskRowData[];
    thread: App.Crm.Data.ThreadEntryData[];
    types: { value: string; label: string }[];
    sources: { value: string; label: string }[];
    presets: { type: string[]; source: string[] };
    week: { date: string; what: string; projectId: string }[];
    conflicts: App.Crm.Data.DateConflictData[];
    stages: { value: string; label: string; anchor: string | null }[];
    tab: "overview" | "quotes" | "messages";
    header: App.Crm.Data.CaseHeaderData;
    conversation: App.Engagement.Data.ThreadData | null;
    quotes: App.Quotes.Data.QuoteData[];
    payments: { number: string | null; rows: { label: string; amount: number }[] } | null;
}>();

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

// ↑↓ walks the queue without leaving the screen. Response time is the lever:
// eight days of silence happen when every step costs a page load. The pane
// hands over what its filter left standing, so the arrows reach what is on
// screen and nothing else.
const reachable = ref<App.Crm.Data.LeadQueueRowData[]>([]);

function step(by: number) {
    const at = reachable.value.findIndex((row) => row.open);
    const next = reachable.value[at + by];

    if (next) {
        router.get(
            // The pane keeps q and wartet in the URL; carrying them means a
            // step does not land on an unfiltered pane.
            `/anfragen/${next.id}${window.location.search}`,
            {},
            { preserveScroll: true, preserveState: true },
        );
    }
}

function onKey(event: KeyboardEvent) {
    if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
        return;
    }

    // Not every keydown target is an element — one dispatched on the window
    // has no matches() — and an arrow key inside a field belongs to the field.
    const target = event.target as Element | null;

    if (
        typeof target?.matches === "function" &&
        target.matches("input, textarea, select")
    ) {
        return;
    }

    event.preventDefault();
    step(event.key === "ArrowDown" ? 1 : -1);
}

onMounted(() => addEventListener("keydown", onKey));
onUnmounted(() => removeEventListener("keydown", onKey));

/*
 * Kein Bearbeiten-Modus über den ganzen Bildschirm mehr: jede Karte hält ihr
 * eigenes Formular und schickt ihre eigene Teilmenge. Wer die Kontakte
 * speichert, hat den Anfragetext nicht in der Hand.
 */
const anfrageEditing = ref(false);
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
            onSuccess: () => (anfrageEditing.value = false),
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

// Was eine Karte sagt, wenn sie zu ist.
const anfrageSummary = computed(() =>
    [props.lead.capturedVia, day(props.lead.capturedAt)].filter(Boolean).join(" · "),
);

const kontakteSummary = computed(() => props.people.map((person) => person.name).join(", "));

const termineSummary = computed(() =>
    props.shoots.length === 0 ? "" : props.shoots.map((shoot) => day(shoot.date)).join(", "),
);

const quoteSummary = computed(() =>
    props.quote === null ? "" : formatEurosRounded(Number(props.quote.net)),
);

const taskSummary = computed(() => {
    const open = props.tasks.filter((row) => !row.isDone);
    const late = open.filter((row) => row.isOverdue).length;

    return [`${open.length}/${props.tasks.length}`, late === 0 ? null : `${late} überfällig`]
        .filter(Boolean)
        .join(" · ");
});

const notes = computed(() => props.thread.filter((entry) => !entry.system));

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

const nextStage = computed(
    () => props.stages[props.stages.findIndex((stage) => stage.value === props.lead.stage) + 1] ?? null,
);
</script>

<template>
    <AdminLayout active="anfragen">
        <ProjectShell :header="header" :active="tab">
            <!-- Grouped by working state, not by the six funnel stages: six stages
                 are the view of the funnel, three groups the view of the day. -->
            <template #left>
                <LeadQueue :groups="queue" @update:rows="reachable = $event" />
            </template>

            <!-- The trace: what lies around the requested day. A date is answered
                 by what is already on it. -->
            <template #rail>
                <Rail>
                    <RailSection :title="$t('project.lead')">
                        <div class="flex flex-col gap-3">
                            <Field v-slot="{ id }" :label="$t('leads.type')" :error="details.errors.type">
                                <Select
                                    v-model="details.type"
                                    :disabled="!header.canManageShoots"
                                    @update:model-value="saveDetail('type')"
                                >
                                    <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    v-if="details.type === 'other'"
                                    v-model="details.type_other"
                                    class="mt-2"
                                    type="text"
                                    list="type-presets"
                                    :disabled="!header.canManageShoots"
                                    @change="saveDetail('type')"
                                />
                                <datalist id="type-presets">
                                    <option v-for="value in presets.type" :key="value" :value="value" />
                                </datalist>
                            </Field>

                            <Field v-slot="{ id }" :label="$t('leads.source')" :error="details.errors.source">
                                <Select
                                    v-model="details.source"
                                    :disabled="!header.canManageShoots"
                                    @update:model-value="saveDetail('source')"
                                >
                                    <SelectTrigger :id="id"><SelectValue placeholder="—" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in sources" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    v-if="details.source === 'other'"
                                    v-model="details.source_other"
                                    class="mt-2"
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
                    </RailSection>

                    <RailSection :title="$t('lead.week')">
                        <template v-if="week.length">
                            <Link
                                v-for="entry in week"
                                :key="`${entry.date}${entry.what}`"
                                :href="`/auftraege/${entry.projectId}`"
                                class="group -mx-2 flex gap-2.5 rounded-lg px-2 py-1.5 text-small leading-[1.47] shadow-[inset_0_1px_0_#ECECEE] transition-colors first:shadow-none hover:bg-paper"
                            >
                                <span class="w-[62px] flex-none font-semibold tabular-nums">{{ day(entry.date) }}</span>
                                <span class="text-ink-2 transition-colors group-hover:text-foreground">{{
                                    entry.what
                                }}</span>
                            </Link>
                        </template>
                        <div
                            v-else
                            class="-mx-2 flex gap-2.5 rounded-lg bg-paper px-2 py-1.5 text-small leading-[1.47]"
                        >
                            <span class="font-medium text-[#3F6B4A]">{{ $t("lead.weekFree") }}</span>
                        </div>
                    </RailSection>

                    <RailSection v-if="conflicts.length" :title="$t('lead.conflicts')">
                        <div
                            v-for="(conflict, index) in conflicts"
                            :key="index"
                            class="-mx-2 flex gap-2.5 rounded-lg bg-paper px-2 py-1.5 text-small leading-[1.47]"
                        >
                            <span class="font-medium text-action">{{
                                conflict.label ?? conflict.projectName
                            }}</span>
                        </div>
                    </RailSection>

                    <RailHistory :entries="thread" />
                </Rail>
            </template>

            <template v-if="!lead.outcomeLabel" #stage>
                <div class="flex">
                    <Button
                        v-if="nextStage"
                        variant="pill"
                        type="button"
                        class="rounded-r-none"
                        @click="move(nextStage.value)"
                        >{{ nextStage.label }}</Button
                    >
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="pill"
                                type="button"
                                :class="nextStage ? 'rounded-l-none border-l border-white/30 px-2' : ''"
                                :aria-label="$t('board.colStage')"
                            >
                                <ChevronDown class="size-3.5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-[208px]">
                            <DropdownMenuItem
                                v-for="stage in stages"
                                :key="stage.value"
                                :disabled="stage.value === lead.stage"
                                @select="move(stage.value)"
                            >
                                {{ stage.label }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </template>

            <AlertDialog v-model:open="booking">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ $t("board.bookTitle") }}</AlertDialogTitle>
                    </AlertDialogHeader>
                    <p>{{ $t("board.bookConsequence") }}</p>
                    <AlertDialogFooter>
                        <AlertDialogAction @click="book">{{ $t("board.book") }}</AlertDialogAction>
                        <AlertDialogCancel>{{ $t("common.cancel") }}</AlertDialogCancel>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

                <template v-if="tab === 'overview'">
                <div class="flex flex-col gap-3">
                    <Card
                        v-model:editing="anfrageEditing"
                        :title="$t('project.lead')"
                        :summary="anfrageSummary"
                        editable
                        @cancel="anfrage.reset()"
                        @save="saveAnfrage"
                    >
                        <!-- Der Anfragetext. Die Personen stehen in ihrer
                             eigenen Karte: eine Seitenspalte trägt keinen
                             Zeileneditor. -->
                        <p class="m-0 max-w-[58ch] text-ui leading-[1.5] tracking-[-0.008em] whitespace-pre-line">
                            {{ lead.inquiryText ?? $t("lead.noText") }}
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
                            class="flex items-center gap-3 py-[9px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none max-[640px]:flex-wrap"
                        >
                            <Link
                                class="w-[168px] flex-none text-small leading-[1.35] font-semibold tracking-[-0.012em] text-foreground no-underline underline-offset-[3px] hover:underline"
                                :href="`/kontakte/${person.id}`"
                            >
                                {{ person.name }}
                            </Link>
                            <span class="rounded-[var(--r-pill)] bg-sunk-2 px-[9px] py-0.5 text-small leading-[1.4] tracking-[0.05em] uppercase text-ink-2">
                                {{ roleLabel(person.role) }}
                            </span>
                            <a v-if="person.email" class="text-small text-action no-underline hover:underline" :href="`mailto:${person.email}`">{{ person.email }}</a>
                            <a v-if="person.phone" class="ml-auto text-small tabular-nums text-action no-underline hover:underline" :href="`tel:${person.phone}`">{{ person.phone }}</a>
                            <span v-if="!person.email && !person.phone" class="ml-auto text-small text-mark">{{ $t("lead.noReach") }}</span>
                        </div>

                        <template #edit>
                            <!-- Die fünf bekannten Rollen als Vorschlag, jede
                                 andere steht so da, wie sie getippt wurde. -->
                            <datalist id="rollen">
                                <option v-for="value in ROLE_SUGGESTIONS" :key="value" :value="value" />
                            </datalist>

                            <div
                                v-for="(person, index) in kontakte.people"
                                :key="person.id"
                                class="flex items-end gap-3 py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none max-[640px]:flex-wrap"
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
                                <Button
                                    v-if="index > 0"
                                    variant="ghost"
                                    class="pb-2 text-small"
                                    type="button"
                                    @click="removeContact(person.id)"
                                >
                                    {{ $t("common.remove") }}
                                </Button>
                            </div>

                            <div v-if="adding" class="mt-3 flex flex-col gap-3 pt-3 shadow-[inset_0_1px_0_var(--rule)]">
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
                                        variant="pill-muted"
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
                        </template>
                    </Card>

                    <Card
                        v-model:editing="termineEditing"
                        :title="$t('lead.dates')"
                        :summary="termineSummary"
                        editable
                        @cancel="termine.reset()"
                        @save="saveTermine"
                    >
                        <!-- Ein Termin verschiebt sich auch nach dem Buchen: das
                             ist der häufigste Vorgang überhaupt. -->
                        <ShootRow v-for="shoot in shoots" :key="shoot.id" :shoot="shoot" />

                        <template #edit>
                            <div v-for="(shoot, index) in termine.shoots" :key="shoot.id" class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <ShootFields :shoot="shoot" />
                                </div>
                                <Button
                                    v-if="index > 0"
                                    variant="ghost"
                                    class="mt-5 text-small"
                                    type="button"
                                    @click="removeShoot(shoot.id)"
                                >
                                    {{ $t("common.remove") }}
                                </Button>
                            </div>

                            <Button class="mt-3" variant="outline" type="button" @click="addShoot">
                                {{ $t("lead.dateAdd") }}
                            </Button>
                        </template>
                    </Card>

                    <Card
                        :title="$t('lead.quote')"
                        :summary="quoteSummary"
                        :default-open="quote !== null"
                    >
                        <template v-if="quote" #meta>
                            <span v-if="quote.number" class="text-ui leading-[1.2] tracking-[-0.015em] text-ink-2 tabular-nums">{{ quote.number }}</span>
                            <span :class="['chip', quoteChipClass(quote.status)]">{{ quote.statusLabel }}</span>
                        </template>
                        <template v-if="header.canManageQuotes" #action>
                            <Link
                                class="text-small tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
                                :href="quote ? `/angebote/${quote.id}` : newQuote"
                            >{{ quote ? $t("common.edit") : $t("quotes.create") }}</Link>
                        </template>

                        <QuoteSummary
                            v-if="quote"
                            :quote="quote"
                            :superseded="supersededQuotes"
                            :valid-until-label="
                                quote.validUntil
                                    ? $t('lead.validUntil', { date: day(quote.validUntil) })
                                    : null
                            "
                        />

                        <!-- Ohne Angebot die aktiven Pakete: der nächste Zug ist,
                             eines zu wählen. -->
                        <div v-else class="flex flex-wrap gap-2">
                            <Link
                                v-for="pkg in packages"
                                :key="pkg.id"
                                class="cursor-pointer rounded-[var(--r-pill)] border-0 bg-sunk px-[11px] py-[5px] text-small leading-[1.4] font-normal text-ink-2 hover:bg-sunk-2 [&_b]:font-semibold [&_b]:text-foreground"
                                :href="`/auftraege/${lead.projectId}/angebote`"
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

                    <Card :title="$t('lead.tasks')" :summary="taskSummary" :default-open="false">
                        <!-- Eine Box: Titel oben, Fälligkeit und Zielgruppe in der
                             Fußzeile darin, der Knopf unten rechts. -->
                        <form
                            class="flex flex-col rounded-md border border-rule focus-within:border-ring"
                            @submit.prevent="addTask"
                        >
                            <input
                                v-model="task.title"
                                class="border-0 bg-transparent px-3.5 pt-3 pb-1.5 text-small outline-none"
                                type="text"
                                :placeholder="$t('lead.taskTitle')"
                            >
                            <div class="flex items-center gap-4 px-3 pt-1 pb-2.5">
                                <DatePicker v-model="task.due_on" class="w-[150px] [&_input]:h-8 [&_input]:border-0 [&_input]:bg-transparent [&_input]:px-0" />
                                <Select v-model="task.audience">
                                    <SelectTrigger class="h-8 w-[110px] border-0 bg-transparent px-0 text-ink-2"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="internal">{{ $t("lead.audienceInternal") }}</SelectItem>
                                        <SelectItem value="client">{{ $t("lead.audienceClient") }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    class="ml-auto"
                                    variant="pill"
                                    type="submit"
                                    :disabled="!task.title || task.processing"
                                >
                                    {{ $t("lead.addTask") }}
                                </Button>
                            </div>
                        </form>

                        <div class="mt-3">
                            <div
                                v-for="row in tasks"
                                :key="row.id"
                                class="flex items-center gap-3 py-[7px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
                            >
                                <span
                                    class="size-[15px] flex-none rounded-[5px]"
                                    :class="row.isDone ? 'bg-foreground' : 'bg-sunk-2'"
                                />
                                <span class="text-ui leading-[1.47]" :class="row.isDone && 'text-ink-2'">{{ row.title }}</span>
                                <span class="text-small leading-[1.47] tracking-[0.05em] uppercase text-ink-2">{{ row.audience }}</span>
                                <span
                                    class="ml-auto text-small leading-[1.47] tabular-nums"
                                    :class="row.isOverdue ? 'text-action' : 'text-ink-2'"
                                >{{ row.dueOn ? day(row.dueOn) : "" }}</span>
                            </div>
                        </div>
                    </Card>

                    <Card :title="$t('lead.notes')" :summary="`${notes.length}`" :default-open="false">
                        <NoteThread
                            :entries="notes"
                            :action="`/auftraege/${lead.projectId}/notizen`"
                        />
                    </Card>
                </div>
                </template>

                <QuoteList
                    v-else-if="tab === 'quotes'"
                    :project-id="lead.projectId"
                    :can-manage-quotes="header.canManageQuotes"
                    :quotes="quotes"
                    :payments="payments"
                />

                <Card v-else :title="$t('project.history')">
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
