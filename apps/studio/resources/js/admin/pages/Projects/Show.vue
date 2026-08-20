<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/admin/layouts/AdminLayout.vue";
import { DatePicker } from "@/ui/date-picker";
import Page from "@/ui/page/Page.vue";

type Figure = { value: string | null; proof: string | null };

const props = defineProps<{
    header: App.Crm.Data.ProjectHeaderData;
    shoots: App.Crm.Data.ShootData[];
    paymentPlan: App.Payments.Data.PaymentPlanData | null;
    notes: App.Crm.Data.InternalNoteData[];
    tasks: App.Crm.Data.TaskRowData[];
    shootOptions: { value: string; label: string }[];
    activity: {
        event: string;
        label: string;
        at: string;
        actorName: string | null;
    }[];
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
    people: {
        id: string;
        name: string;
        role: string;
        email: string | null;
        phone: string | null;
    }[];
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

const money = new Intl.NumberFormat("de-DE", {
    style: "currency",
    currency: "EUR",
    maximumFractionDigits: 0,
});

function day(iso: string | null) {
    return iso
        ? new Date(iso).toLocaleDateString("de-DE", {
              weekday: "short",
              day: "2-digit",
              month: "2-digit",
          })
        : "—";
}

function euro(minor: string | number | null) {
    return minor === null ? "—" : money.format(Number(minor) / 100);
}

// C-11 (#247): the same build in every phase. Before the first shoot the
// history is short and the open list long, after delivery the other way
// round, three years later "Offen" holds one row — the follow-up — and the
// trace holds the repeat orders instead of dates. Building a head per phase
// means widening the screen with every phase that turns up.
const open = computed(() => props.tasks.filter((task) => !task.isDone));
const done = computed(() => props.tasks.filter((task) => task.isDone));

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
        venue_name: shoot.venue?.split(", ")[0] ?? "",
        venue_city: shoot.venue?.split(", ")[1] ?? "",
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

const note = useForm({ body: "" });

function addNote() {
    note.post(`/auftraege/${props.header.id}/notizen`, {
        preserveScroll: true,
        onSuccess: () => note.reset(),
    });
}

function togglePortal() {
    router.post(
        `/auftraege/${props.header.id}/portal-sichtbarkeit`,
        {},
        { preserveScroll: true },
    );
}

function complete(task: App.Crm.Data.TaskRowData) {
    router.post(`/aufgaben/${task.id}/erledigt`, {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout active="projekte">
        <Page>
            <template #right>
                <h2>{{ $t("lead.people") }}</h2>
                <div
                    v-for="(person, index) in people"
                    :key="person.id"
                    class="p"
                >
                    <Link class="n" :href="`/kontakte/${person.id}`">{{
                        person.name
                    }}</Link>
                    <select
                        v-if="editing"
                        v-model="edit.people[index].role"
                        class="form"
                    >
                        <option
                            v-for="option in roles"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ $t(`contacts.role.${option.value}`) }}
                        </option>
                    </select>
                    <span v-else class="st">{{
                        $t(`contacts.role.${person.role}`)
                    }}</span>
                </div>

                <template v-if="paymentPlan">
                    <h2>{{ $t("dashboard.payments") }}</h2>
                    <div
                        v-for="instalment in paymentPlan.installments"
                        :key="instalment.id"
                        class="k"
                    >
                        <span>{{ day(instalment.dueOn) }}</span>
                        <span>{{ euro(instalment.amount) }}</span>
                    </div>
                </template>

                <template v-if="galleries.length">
                    <h2>{{ $t("contacts.galleries") }}</h2>
                    <div
                        v-for="gallery in galleries"
                        :key="gallery.id"
                        class="item"
                    >
                        <Link
                            class="n"
                            :href="`/galerien/${gallery.id}/medien`"
                            >{{ gallery.title ?? "—" }}</Link
                        >
                        <div v-if="gallery.expiresAt" class="m">
                            {{
                                $t("contacts.expiresOn", {
                                    date: day(gallery.expiresAt),
                                })
                            }}
                        </div>
                    </div>
                </template>

                <h2>{{ $t("project.documents") }}</h2>
                <div class="item">
                    <Link class="n" :href="`/auftraege/${header.id}/vertrag`">{{
                        $t("project.contract")
                    }}</Link>
                </div>
                <div class="item">
                    <Link
                        class="n"
                        :href="`/auftraege/${header.id}/frageboegen`"
                        >{{ $t("project.forms") }}</Link
                    >
                </div>
                <div class="item">
                    <Link
                        class="n"
                        :href="`/auftraege/${header.id}/nachrichten`"
                        >{{ $t("project.messages") }}</Link
                    >
                </div>
            </template>

            <div data-theme="crm" style="display: flex; align-items: stretch">
                <div class="work">
                    <div class="crumb">
                        <Link href="/projekte">{{ $t("projects.title") }}</Link>
                    </div>

                    <div
                        class="title"
                        style="
                            position: sticky;
                            top: 0;
                            z-index: 5;
                            background: var(--paper);
                        "
                    >
                        <div>
                            <input
                                v-if="editing"
                                v-model="edit.name"
                                class="form"
                                type="text"
                                style="font-size: 22px"
                            />
                            <h1
                               
                                v-else
                            >
                                {{ header.names }}
                            </h1>
                        </div>

                        <div v-if="editing" class="acts">
                            <a
                                class="link"
                                href="#"
                                @click.prevent="editing = false"
                                >{{ $t("common.cancel") }}</a
                            >
                            <button
                                class="btn"
                                type="button"
                                :disabled="edit.processing"
                                @click="save"
                            >
                                {{ $t("common.save") }}
                            </button>
                        </div>

                        <div v-else class="acts">
                            <a
                                class="link"
                                href="#"
                                @click.prevent="editing = true"
                                >{{ $t("lead.edit") }}</a
                            >
                            <Link
                                v-if="header.canManageQuotes"
                                class="link"
                                :href="`/auftraege/${header.id}/angebote`"
                            >
                                {{ $t("project.quotes") }}
                            </Link>
                            <Link
                                class="btn"
                                :href="`/auftraege/${header.id}/shootings`"
                                >{{ $t("project.shoots") }}</Link
                            >
                        </div>
                    </div>

                    <!-- Master data across, six values on one line, each with its proof
             underneath — not a phase tile that a seventh phase would break. -->
                    <div class="band" style="margin-top: var(--s5)">
                        <div class="cell">
                            <div class="lab">{{ $t("project.position") }}</div>
                            <select
                                v-if="editing && stage"
                                v-model="edit.stage"
                                class="form"
                            >
                                <option
                                    v-for="option in stages"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <div v-else class="val plain">
                                {{ figures.phase.value ?? "—" }}
                            </div>
                            <div v-if="figures.phase.proof" class="sub">
                                {{ figures.phase.proof }}
                            </div>
                        </div>
                        <div class="cell">
                            <div class="lab">
                                {{ $t("project.mainDateLabel") }}
                                <span v-if="editing" class="derived">
                                    · {{ $t("project.fromShoots") }}</span
                                >
                            </div>
                            <div class="val plain">
                                {{ day(figures.nextDate.value) }}
                            </div>
                            <div class="sub">
                                {{ $t("board.shootCount", shoots.length) }}
                            </div>
                        </div>
                        <div class="cell">
                            <div class="lab">
                                {{ $t("board.colValue") }}
                                <span v-if="editing" class="derived">
                                    · {{ $t("project.fromScope") }}</span
                                >
                            </div>
                            <div class="val">
                                {{ euro(figures.value.value) }}
                            </div>
                            <div v-if="figures.value.proof" class="sub">
                                {{ figures.value.proof }}
                            </div>
                        </div>
                        <div class="cell">
                            <div class="lab">
                                {{ $t("project.openAmount") }}
                                <span v-if="editing" class="derived">
                                    · {{ $t("project.fromPayments") }}</span
                                >
                            </div>
                            <div
                                class="val"
                                :class="{
                                    warn: Number(figures.open.value ?? 0) > 0,
                                }"
                            >
                                {{ euro(figures.open.value) }}
                            </div>
                            <div v-if="figures.open.proof" class="sub">
                                {{
                                    $t(
                                        "project.instalments",
                                        Number(figures.open.proof),
                                    )
                                }}
                            </div>
                        </div>
                        <div v-if="sourceLabel" class="cell">
                            <div class="lab">{{ $t("leads.source") }}</div>
                            <select
                                v-if="editing"
                                v-model="edit.source"
                                class="form"
                            >
                                <option
                                    v-for="option in sources"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <div v-else class="val plain">
                                {{ sourceLabel }}
                            </div>
                            <input
                                v-if="editing && edit.source === 'other'"
                                v-model="edit.source_other"
                                type="text"
                                class="form"
                                list="source-presets"
                                style="margin-top: 6px"
                            />
                            <datalist id="source-presets">
                                <option
                                    v-for="value in presets.source"
                                    :key="value"
                                    :value="value"
                                />
                            </datalist>
                        </div>
                        <div class="cell">
                            <div class="lab">{{ $t("project.portal") }}</div>
                            <!-- The figure and the switch that changes it, in one cell: this
                 is the one key value that is a decision rather than a
                 derivation, so it is the one with a control. -->
                            <div
                                class="val plain"
                                style="
                                    display: flex;
                                    align-items: center;
                                    gap: var(--s3);
                                "
                            >
                                {{
                                    figures.portal.value === "on"
                                        ? $t("contacts.portalOn")
                                        : $t("contacts.portalOff")
                                }}
                                <span
                                    v-if="header.canManagePortalVisibility"
                                    class="sw"
                                    :class="{
                                        on: figures.portal.value === 'on',
                                    }"
                                    role="button"
                                    :aria-label="$t('project.portalToggle')"
                                    @click="togglePortal"
                                />
                            </div>
                        </div>
                    </div>

                    <section v-if="editing || shoots.length" class="sec">
                        <h2>{{ $t("lead.dates") }}</h2>
                        <div v-if="editing" class="form">
                            <div
                                v-for="shoot in edit.shoots"
                                :key="shoot.id"
                                class="shootblock"
                            >
                                <div class="line">
                                    <label class="field"
                                        ><span>{{
                                            $t("project.shootDate")
                                        }}</span
                                        ><DatePicker v-model="shoot.date"
                                    /></label>
                                    <label class="field"
                                        ><span>{{
                                            $t("project.callTime")
                                        }}</span
                                        ><input
                                            v-model="shoot.call_time"
                                            type="time"
                                    /></label>
                                    <label class="field"
                                        ><span>{{ $t("project.endTime") }}</span
                                        ><input
                                            v-model="shoot.end_time"
                                            type="time"
                                    /></label>
                                </div>
                                <div class="line">
                                    <label class="field grow"
                                        ><span>{{
                                            $t("project.venueName")
                                        }}</span
                                        ><input
                                            v-model="shoot.venue_name"
                                            type="text"
                                    /></label>
                                    <label class="field grow"
                                        ><span>{{
                                            $t("project.venueCity")
                                        }}</span
                                        ><input
                                            v-model="shoot.venue_city"
                                            type="text"
                                    /></label>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="shoots.length" class="rowset">
                            <div
                                v-for="shoot in shoots"
                                :key="shoot.id"
                                class="r"
                            >
                                <span class="when">
                                    {{ day(shoot.date) }}
                                    <span class="st">{{
                                        shoot.statusLabel
                                    }}</span>
                                </span>
                                <span class="what">
                                    {{ shoot.label ?? shoot.kind }}
                                    <span v-if="shoot.venue" class="s">
                                        · {{ shoot.venue }}</span
                                    >
                                </span>
                                <span class="right">{{
                                    shoot.crew
                                        .map((member) => member.name)
                                        .join(", ") || "—"
                                }}</span>
                            </div>
                        </div>
                    </section>

                    <!-- The scope as it stands, not a copy of the quote. Nothing is
             locked: the order changes in conversation and the document lags
             behind. Forbid the change and the scope is simply wrong while the
             work happens anyway. -->
                    <section v-if="scope.items.length" class="sec">
                        <h2>{{ $t("project.scope") }}</h2>
                        <div class="quote">
                            <div
                                v-for="item in scope.items"
                                :key="item.id"
                                class="qr"
                            >
                                <span class="ti">
                                    {{ item.title }}
                                    <span v-if="item.state" class="tag">{{
                                        $t(`project.scope_${item.state}`)
                                    }}</span>
                                </span>
                                <span class="qty">{{ item.quantity }}×</span>
                                <span class="amt">
                                    {{
                                        item.unitPrice === null
                                            ? $t("packages.included")
                                            : euro(item.unitPrice)
                                    }}
                                    <span
                                        v-if="item.state === 'changed'"
                                        class="was"
                                        >{{ euro(item.wasUnitPrice) }}</span
                                    >
                                </span>
                            </div>
                        </div>

                        <p v-if="scope.delta" class="meta">
                            <em>{{
                                $t("project.scopeDelta", {
                                    amount: euro(Math.abs(scope.delta)),
                                })
                            }}</em>
                            <Link
                                class="link"
                                :href="`/auftraege/${header.id}/angebote`"
                                style="margin-left: var(--s3)"
                            >
                                {{ $t("project.sendAddendum") }}
                            </Link>
                        </p>
                    </section>

                    <section v-if="open.length" class="sec">
                        <h2>{{ $t("project.openItems") }}</h2>
                        <div class="todo">
                            <div v-for="task in open" :key="task.id" class="r">
                                <button
                                    class="b"
                                    type="button"
                                    @click="complete(task)"
                                />
                                <span class="t">{{ task.title }}</span>
                                <span class="aud">{{ task.audience }}</span>
                                <span
                                    class="due"
                                    :class="{ warn: task.isOverdue }"
                                    >{{
                                        task.dueOn ? day(task.dueOn) : ""
                                    }}</span
                                >
                            </div>
                        </div>
                    </section>

                    <section class="sec">
                        <h2>{{ $t("lead.notes") }}</h2>

                        <form class="composer form" @submit.prevent="addNote">
                            <textarea
                                v-model="note.body"
                                rows="2"
                                :placeholder="$t('lead.notePlaceholder')"
                            />
                            <div class="foot">
                                <button
                                    class="btn-2"
                                    type="submit"
                                    :disabled="!note.body || note.processing"
                                >
                                    {{ $t("lead.addNote") }}
                                </button>
                            </div>
                        </form>

                        <!-- Notes and the audit trail in one trace: what happened and what
               somebody wrote about it are read together or not at all. -->
                        <div class="stream">
                            <div
                                v-for="note in notes"
                                :key="note.id"
                                class="ev"
                            >
                                <span class="t">{{ day(note.writtenAt) }}</span>
                                <span class="c"
                                    >{{ note.body }}
                                    <span class="by"
                                        >— {{ note.author }}</span
                                    ></span
                                >
                            </div>
                            <div
                                v-for="(entry, index) in activity"
                                :key="index"
                                class="ev sys"
                            >
                                <span class="t">{{ day(entry.at) }}</span>
                                <span class="c">
                                    {{ entry.label }}
                                    <span v-if="entry.actorName" class="by">
                                        — {{ entry.actorName }}</span
                                    >
                                </span>
                            </div>
                            <div
                                v-for="task in done"
                                :key="task.id"
                                class="ev sys"
                            >
                                <span class="t">{{
                                    task.dueOn ? day(task.dueOn) : ""
                                }}</span>
                                <span class="c">{{ task.title }}</span>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </Page>
    </AdminLayout>
</template>
