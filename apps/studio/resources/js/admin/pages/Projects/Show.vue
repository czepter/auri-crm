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
import { DatePicker } from "@/ui/date-picker";
import { Field } from "@/ui/field";
import { Input } from "@/ui/input";
import { NativeSelect } from "@/ui/native-select";
import ProjectShell from "@/admin/components/ProjectShell.vue";
import Rail from "@/ui/page/Rail.vue";
import RailItem from "@/ui/page/RailItem.vue";
import RailSection from "@/ui/page/RailSection.vue";
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

function complete(task: App.Crm.Data.TaskRowData) {
    router.post(`/aufgaben/${task.id}/erledigt`, {}, { preserveScroll: true });
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
                            <NativeSelect
                                v-if="editing"
                                v-model="edit.people[index].role"
                                class="max-w-[820px]"
                            >
                                <option
                                    v-for="option in roles"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ $t(`contacts.role.${option.value}`) }}
                                </option>
                            </NativeSelect>
                            <span
                                v-else
                                class="ml-auto text-[11.5px] leading-[1.47] text-[#3F6B4A]"
                            >{{ $t(`contacts.role.${person.role}`) }}</span>
                        </div>
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
                    <!-- Master data across, six values on one line, each with its proof
                         underneath — not a phase tile that a seventh phase would break. -->
                    <div class="mt-5 mb-5 flex">
                        <BandCell :label="$t('project.position')" :sub="figures.phase.proof">
                            <NativeSelect
                                v-if="editing && stage"
                                v-model="edit.stage"
                                variant="filled"
                                class="mt-[5px] max-w-[820px]"
                            >
                                <option
                                    v-for="option in stages"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </NativeSelect>
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

                        <BandCell v-if="sourceLabel" :label="$t('leads.source')">
                            <NativeSelect
                                v-if="editing"
                                v-model="edit.source"
                                variant="filled"
                                class="mt-[5px] max-w-[820px]"
                            >
                                <option
                                    v-for="option in sources"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </NativeSelect>
                            <BandValue v-else plain>{{ sourceLabel }}</BandValue>
                            <Input
                                v-if="editing && edit.source === 'other'"
                                v-model="edit.source_other"
                                variant="filled"
                                class="mt-1.5 max-w-[820px]"
                                type="text"
                                list="source-presets"
                            />
                            <datalist id="source-presets">
                                <option v-for="value in presets.source" :key="value" :value="value" />
                            </datalist>
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

                    <section v-if="editing || shoots.length" class="mb-5">
                        <h2>{{ $t("lead.dates") }}</h2>
                        <div v-if="editing" class="max-w-[820px]">
                            <ShootFields
                                v-for="shoot in edit.shoots"
                                :key="shoot.id"
                                :shoot="shoot"
                            />
                        </div>

                        <div v-else-if="shoots.length">
                            <ShootRow v-for="shoot in shoots" :key="shoot.id" :shoot="shoot" />
                        </div>
                    </section>

                    <!-- The scope as it stands, not a copy of the quote. Nothing is
                         locked: the order changes in conversation and the document lags
                         behind. Forbid the change and the scope is simply wrong while the
                         work happens anyway. -->
                    <section v-if="scope.items.length" class="mb-5">
                        <h2>{{ $t("project.scope") }}</h2>
                        <LineItems :items="scope.items">
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
                    </section>

                    <section v-if="open.length" class="mb-5">
                        <h2>{{ $t("project.openItems") }}</h2>
                        <div>
                            <div
                                v-for="task in open"
                                :key="task.id"
                                class="flex items-center gap-3 py-[7px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
                            >
                                <button
                                    class="size-[15px] flex-none rounded-[5px] bg-sunk-2"
                                    type="button"
                                    @click="complete(task)"
                                />
                                <span class="text-[13.5px] leading-[1.47]">{{ task.title }}</span>
                                <span
                                    class="text-[10.5px] leading-[1.47] tracking-[0.05em] uppercase text-ink-2"
                                >{{ task.audience }}</span>
                                <span
                                    class="ml-auto text-[12px] leading-[1.47] tabular-nums"
                                    :class="task.isOverdue ? 'text-action' : 'text-ink-2'"
                                >{{ task.dueOn ? day(task.dueOn) : "" }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="mb-5">
                        <h2>{{ $t("lead.notes") }}</h2>

                        <NoteThread
                            class="mt-4"
                            :entries="thread"
                            :action="`/auftraege/${header.id}/notizen`"
                        />
                    </section>
        </ProjectShell>
    </AdminLayout>
</template>
