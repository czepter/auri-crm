<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { ChevronDown } from 'lucide-vue-next'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/crm/ProjectShell.vue'
import { chipClass } from '@/admin/lib/shoots'
import { dayWithWeekday } from '@/admin/lib/dates'
import { Alert } from '@/ui/alert'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import Card from '@/ui/page/Card.vue'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { TimePicker } from '@/ui/time-picker'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { useLiveProps } from '@/admin/lib/liveReload'

const { t } = useI18n()

const props = defineProps<{
  header: App.Crm.Screens.CaseHeaderData
  shoots: App.Crm.Data.ShootData[]
  crewAvailability: Record<string, App.Crm.Data.CrewCandidateData[]>
  soloStudio: boolean
  shootKinds: string[]
}>()
useLiveProps(['header', 'shoots', 'crewAvailability', 'soloStudio'])

const adding = ref(false)
const form = useForm({
  kind: '',
  entered_at: '',
  label: '',
  date: '',
  call_time: '',
  end_time: '',
  venue_name: '',
  venue_city: '',
})

// Der Ablauf wächst zeilenweise: Uhrzeit und Titel reichen für den Regelfall,
// Ort/Hinweis/Fahrt stehen hinter "…ergänzen" statt bei jeder Zeile mit.
const addingTimelineFor = ref<string | null>(null)
const timelineDetails = ref(false)
const timelineForm = useForm({
  time: '',
  title: '',
  location: '',
  note: '',
  is_transit: false,
})

function startTimelineEntry(shoot: App.Crm.Data.ShootData) {
  addingTimelineFor.value = shoot.id
  timelineDetails.value = false
  timelineForm.reset()
}

function addTimelineEntry(shoot: App.Crm.Data.ShootData) {
  timelineForm.post(`/shootings/${shoot.id}/ablauf`, {
    preserveScroll: true,
    onSuccess: () => {
      timelineForm.reset()
      timelineDetails.value = false
    },
  })
}

// Eine Zeile ändern heißt: an Ort und Stelle zum Formular werden, nicht
// löschen und neu anlegen.
const editingTimelineEntry = ref<string | null>(null)
const editTimelineForm = useForm({
  time: '',
  title: '',
  location: '',
  note: '',
  is_transit: false,
})

function startEditTimelineEntry(entry: App.Crm.Data.TimelineEntryData) {
  editingTimelineEntry.value = entry.id
  editTimelineForm.time = entry.time
  editTimelineForm.title = entry.title
  editTimelineForm.location = entry.location ?? ''
  editTimelineForm.note = entry.note ?? ''
  editTimelineForm.is_transit = entry.isTransit
}

function saveTimelineEntry(entry: App.Crm.Data.TimelineEntryData) {
  editTimelineForm.patch(`/ablauf/${entry.id}`, {
    preserveScroll: true,
    onSuccess: () => (editingTimelineEntry.value = null),
  })
}

function removeTimelineEntry(entry: App.Crm.Data.TimelineEntryData) {
  router.delete(`/ablauf/${entry.id}`, { preserveScroll: true })
}

const today = new Date().toISOString().slice(0, 10)

function metrics(shoot: App.Crm.Data.ShootData) {
  return [
    { label: 'project.shootKind', value: shoot.kind },
    { label: 'project.callTime', value: shoot.callTime ?? '—' },
    { label: 'project.endTime', value: shoot.endTime ?? '—' },
    { label: 'project.venueName', value: shoot.venue ?? '—' },
  ]
}

// Ort und Stand stehen schon in der Kennzahlenreihe und im Chip daneben;
// zugeklappt fehlt nur, wann und mit wem.
function summary(shoot: App.Crm.Data.ShootData) {
  return [
    dayWithWeekday(shoot.date, null) ?? t('board.openDate'),
    shoot.crew.map((member) => member.name).join(', ') || null,
  ]
    .filter(Boolean)
    .join(' · ')
}

const filter = ref<'all' | 'upcoming' | 'cancelled'>('all')

const upcoming = computed(() =>
  props.shoots.filter((shoot) => !shoot.isCancelled && (shoot.date === null || shoot.date >= today))
)
const cancelled = computed(() => props.shoots.filter((shoot) => shoot.isCancelled))

const filters = computed(() => [
  { key: 'all' as const, label: 'project.filterAll', count: props.shoots.length },
  { key: 'upcoming' as const, label: 'project.filterUpcoming', count: upcoming.value.length },
  { key: 'cancelled' as const, label: 'project.filterCancelled', count: cancelled.value.length },
])

const visible = computed(() => {
  if (filter.value === 'upcoming') return upcoming.value
  if (filter.value === 'cancelled') return cancelled.value

  return props.shoots
})

function create() {
  form.post(`/auftraege/${props.header.id}/shootings`, {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      adding.value = false
    },
  })
}

const archive = (shoot: App.Crm.Data.ShootData) => shoot.forwardStatuses.find((s) => s.value === 'archived')
const onward = (shoot: App.Crm.Data.ShootData) => shoot.forwardStatuses.filter((s) => s.value !== 'archived')

function advance(shoot: App.Crm.Data.ShootData, status: string) {
  if (status) {
    router.post(`/shootings/${shoot.id}/status`, { status }, { preserveScroll: true })
  }
}

// A cancellation is the one action here that writes a sentence into the
// record — Shoot.cancelled_reason is shown back on the row afterwards and in
// the client's portal. window.prompt() gave that sentence a single-line
// unstyled box in a language the browser picked; it gets the same field as
// every other note the studio writes.
const cancelling = ref<App.Crm.Data.ShootData | null>(null)
const cancelReason = ref('')

function openCancel(shoot: App.Crm.Data.ShootData) {
  cancelling.value = shoot
  cancelReason.value = ''
}

function confirmCancel() {
  const shoot = cancelling.value
  if (!shoot || !cancelReason.value.trim()) return

  router.post(`/shootings/${shoot.id}/absage`, { reason: cancelReason.value }, {
    preserveScroll: true,
    onSuccess: () => (cancelling.value = null),
  })
}

function uncancel(shoot: App.Crm.Data.ShootData) {
  router.post(`/shootings/${shoot.id}/absage-zuruecknehmen`, {}, { preserveScroll: true })
}

function designatePrimary(shoot: App.Crm.Data.ShootData) {
  router.post(`/shootings/${shoot.id}/haupttermin`, {}, { preserveScroll: true })
}

// Die ID, nicht das Objekt: nach jedem Zuweisen liefert Inertia die Shoots
// neu, und der Dialog soll die frische Liste zeigen, nicht die vom Öffnen.
const assigningId = ref<string | null>(null)
const assigningShoot = computed(() => props.shoots.find((shoot) => shoot.id === assigningId.value) ?? null)

function openAssign(shoot: App.Crm.Data.ShootData) {
  assigningId.value = shoot.id
}

const assignedIds = computed(() => new Set(assigningShoot.value?.crew.map((member) => member.userId) ?? []))
// CrewAvailability answered this for the shoot's own day, for everybody —
// not only for those already on it.
const candidates = computed(() => (assigningShoot.value ? props.crewAvailability[assigningShoot.value.id] ?? [] : []))

// Ein Tap weist zu, der Dialog bleibt offen: die nächste Person braucht
// keinen neuen Dialog-Aufruf.
function assignCandidate(user: string) {
  const shoot = assigningShoot.value
  if (!shoot) return

  router.post(`/shootings/${shoot.id}/crew`, { user }, { preserveScroll: true })
}

function unassign(member: App.Crm.Data.CrewMemberData) {
  router.delete(`/crew/${member.id}`, { preserveScroll: true })
}

/* tokens.css .chip's four tones. */
const TONE: Record<string, string> = {
  done: 'bg-ok-bg text-ok',
  now: 'bg-due-bg text-due',
  next: 'bg-muted text-muted-foreground',
  off: 'bg-muted text-faint',
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectShell :header="header" active="shoots">
        <!-- Adding an occasion is a discrete act against a list you are reading:
             inline, the form pushed every existing shoot down the page and the
             list you were comparing against scrolled out of sight. -->
        <template v-if="header.canManageShoots" #actions>
          <Dialog v-model:open="adding">
            <DialogTrigger as-child>
              <Button variant="pill" type="button">
                {{ $t('project.addShoot') }}
              </Button>
            </DialogTrigger>

            <DialogContent>
              <DialogHeader>
                <DialogTitle>{{ $t('project.addShoot') }}</DialogTitle>
              </DialogHeader>

              <DialogBody>
                <!-- the submit lives in the footer and reaches back in by form id,
                     the same way the settings screens already submit from a header -->
                <form id="shoot-create-form" class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4" @submit.prevent="create">
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="kind">{{ $t('project.shootKind') }}</Label>
                    <Input id="kind" v-model="form.kind" type="text" maxlength="255" list="shoot-kinds" />
                    <datalist id="shoot-kinds">
                      <option v-for="kind in shootKinds" :key="kind" :value="kind" />
                    </datalist>
                  </div>

                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="label">{{ $t('project.shootLabel') }}</Label>
                    <Input id="label" v-model="form.label" type="text" />
                  </div>

                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="date">
                      {{ $t('project.shootDate') }}<em>{{ $t('project.optional') }}</em>
                    </Label>
                    <DatePicker id="date" v-model="form.date" />
                  </div>

                  <div class="grid grid-cols-2 gap-4">
                    <div class="flex min-w-0 flex-col gap-1.5">
                      <Label for="call_time">{{ $t('project.callTime') }}</Label>
                      <TimePicker id="call_time" v-model="form.call_time" />
                    </div>
                    <div class="flex min-w-0 flex-col gap-1.5">
                      <Label for="end_time">{{ $t('project.endTime') }}</Label>
                      <TimePicker id="end_time" v-model="form.end_time" />
                      <p v-if="form.errors.end_time" class="text-small leading-[1.45] text-destructive">{{ form.errors.end_time }}</p>
                    </div>
                  </div>

                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="venue_name">{{ $t('project.venueName') }}</Label>
                    <Input id="venue_name" v-model="form.venue_name" type="text" />
                  </div>

                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="venue_city">{{ $t('project.venueCity') }}</Label>
                    <Input id="venue_city" v-model="form.venue_city" type="text" />
                  </div>

                  <details class="col-span-2">
                    <summary class="w-fit cursor-pointer text-small text-ink-2 marker:text-ink-2">{{ $t('project.advanced') }}</summary>
                    <div class="mt-1.5 flex min-w-0 flex-col gap-1.5">
                      <Label for="entered_at">{{ $t('project.shootEnteredAt') }}</Label>
                      <DatePicker id="entered_at" v-model="form.entered_at" />
                      <p v-if="form.errors.entered_at" class="text-small leading-[1.45] text-destructive">{{ form.errors.entered_at }}</p>
                    </div>
                  </details>

                </form>
              </DialogBody>

              <DialogFooter>
                <Button type="submit" form="shoot-create-form" :disabled="form.processing">
                  {{ $t('common.save') }}
                </Button>
                <DialogClose as-child>
                  <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
                </DialogClose>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </template>

        <div class="mb-3 inline-flex gap-0.5 rounded-lg bg-sunk p-[3px]">
          <button
            v-for="tab in filters"
            :key="tab.key"
            type="button"
            class="rounded-[var(--r-sm)] px-2.5 py-1 text-small leading-none text-ink-2"
            :class="filter === tab.key && 'bg-paper font-semibold text-foreground'"
            @click="filter = tab.key"
          >
            {{ $t(tab.label) }} {{ tab.count }}
          </button>
        </div>

        <!-- Depth behind the Shootings tab: every occasion in full. Only open
             shoots carry editable weight; a cancelled one keeps the status it died
             in and gains no controls back. -->
        <div class="flex flex-col gap-1.5">
          <Card
            v-for="shoot in visible"
            :key="shoot.id"
            :title="shoot.label ?? shoot.kind"
            :summary="summary(shoot)"
            :default-open="shoot.date === null || shoot.date >= today"
          >
            <template #action>
              <span v-if="shoot.isPrimary" class="text-small leading-[1.5] font-semibold text-foreground">
                {{ $t('project.primary') }}
              </span>
              <span :class="['rounded-[var(--r-sm)] px-2 py-[3px] text-small leading-[1.5] font-semibold', TONE[chipClass(shoot, header.position.value)]]">
                {{ shoot.statusLabel }}
              </span>

              <div v-if="!shoot.isCancelled && header.canManageShoots" class="flex items-center gap-2">
                <div v-if="onward(shoot).length" class="flex">
                  <Button
                    variant="pill"
                    type="button"
                    class="rounded-r-none"
                    @click="advance(shoot, onward(shoot)[0].value)"
                  >{{ onward(shoot)[0].label }}</Button>
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="pill" type="button" class="rounded-l-none border-l border-white/30 px-2" :aria-label="$t('project.advance')">
                        <ChevronDown class="size-3.5" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[208px]">
                      <DropdownMenuItem v-for="status in onward(shoot)" :key="status.value" @select="advance(shoot, status.value)">
                        {{ status.label }}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>

                <DropdownMenu>
                  <DropdownMenuTrigger
                    class="flex size-8 flex-none cursor-pointer items-center justify-center rounded-[var(--r)] bg-sunk-2"
                    :aria-label="$t('project.more')"
                  >
                    <svg class="size-4 fill-current" viewBox="0 0 24 24">
                      <circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" />
                    </svg>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" class="w-[208px]">
                    <DropdownMenuItem v-if="!shoot.isPrimary" @select="designatePrimary(shoot)">
                      {{ $t('project.makePrimary') }}
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="archive(shoot)" @select="advance(shoot, 'archived')">
                      {{ archive(shoot)!.label }}
                    </DropdownMenuItem>
                    <DropdownMenuItem class="text-destructive" @select="openCancel(shoot)">
                      {{ $t('project.cancel') }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </template>

            <Alert v-if="shoot.isCancelled" variant="destructive">
              {{ $t('project.cancelled', { reason: shoot.cancelledReason }) }}
              <Button v-if="header.canManageShoots" class="ml-auto" variant="outline" type="button" @click="uncancel(shoot)">
                {{ $t('project.uncancel') }}
              </Button>
            </Alert>

            <template v-else>
              <div class="grid grid-cols-4 gap-[18px] max-[640px]:grid-cols-2">
                <div v-for="metric in metrics(shoot)" :key="metric.label">
                  <div class="text-small leading-none font-semibold text-faint">{{ $t(metric.label) }}</div>
                  <div class="mt-1.5 text-ui leading-[1.35]">{{ metric.value }}</div>
                </div>
              </div>

              <div v-if="!soloStudio" class="mt-4 pt-3 shadow-[inset_0_1px_0_var(--rule)]">
                <div class="text-small leading-none font-semibold text-faint">{{ $t('project.crew') }}</div>

                <div v-for="member in shoot.crew" :key="member.id" class="mt-1.5 flex items-center gap-2.5">
                  <span class="flex size-[22px] flex-none items-center justify-center rounded-full bg-sunk-2 text-[9.5px] leading-none font-semibold text-ink-2">{{ member.initials }}</span>
                  <span class="min-w-0 flex-1 text-small leading-[1.35]">{{ member.name }}</span>
                  <Button v-if="header.canManageCrew" type="button" variant="ghost" @click="unassign(member)">
                    {{ $t('project.unassign') }}
                  </Button>
                </div>

                <!-- T-10: CheckDateAvailability's third source, already narrowed by
                     CaseController::shootsOf() to this shoot's date and this
                     shoot's own crew. Warns, same as every other conflict that
                     action produces — the assignment above stays exactly as
                     possible whether or not this shows. -->
                <div
                  v-for="conflict in shoot.crewConflicts"
                  :key="conflict.personId ?? undefined"
                  class="mt-2 text-small leading-[1.35] font-medium text-due"
                >
                  {{ $t('project.crewAbsence', { label: conflict.label }) }}
                </div>

                <Button v-if="header.canManageCrew" class="mt-2.5" type="button" variant="outline" @click="openAssign(shoot)">
                  <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
                  {{ $t('project.assignCrew') }}
                </Button>
              </div>

              <div v-if="shoot.date || shoot.timeline.length" class="mt-4 pt-3 shadow-[inset_0_1px_0_var(--rule)]">
                <div class="text-small leading-none font-semibold text-faint">{{ $t('project.timeline') }}</div>

                <!-- Der Ablauf als Zeitstrahl: eine Linie verbindet die
                     Punkte, ein Fahrt/Übergang-Eintrag steht gedimmt und
                     kursiv dazwischen statt wie ein echter Programmpunkt. -->
                <div class="mt-1.5 ml-[3px] flex flex-col border-l border-rule pl-3.5">
                  <div v-for="entry in shoot.timeline" :key="entry.id" class="py-1.5">
                    <form
                      v-if="editingTimelineEntry === entry.id"
                      class="flex flex-col gap-2 rounded-md bg-sunk p-2.5"
                      @submit.prevent="saveTimelineEntry(entry)"
                    >
                      <div class="flex gap-2">
                        <Label :for="`tl-edit-time-${entry.id}`" class="sr-only">{{ $t('project.timelineTime') }}</Label>
                        <TimePicker :id="`tl-edit-time-${entry.id}`" v-model="editTimelineForm.time" class="w-[104px]" required />
                        <Label :for="`tl-edit-title-${entry.id}`" class="sr-only">{{ $t('project.timelineTitle') }}</Label>
                        <Input :id="`tl-edit-title-${entry.id}`" v-model="editTimelineForm.title" class="min-w-0 flex-1" type="text" required />
                      </div>
                      <div class="flex gap-2">
                        <Label :for="`tl-edit-location-${entry.id}`" class="sr-only">{{ $t('project.timelineLocation') }}</Label>
                        <Input :id="`tl-edit-location-${entry.id}`" v-model="editTimelineForm.location" class="min-w-0 flex-1" type="text" :placeholder="$t('project.timelineLocation')" />
                        <Label :for="`tl-edit-note-${entry.id}`" class="sr-only">{{ $t('project.timelineNote') }}</Label>
                        <Input :id="`tl-edit-note-${entry.id}`" v-model="editTimelineForm.note" class="min-w-0 flex-1" type="text" :placeholder="$t('project.timelineNote')" />
                      </div>
                      <div class="flex items-center gap-1.5">
                        <Checkbox id="tl-edit-transit" v-model="editTimelineForm.is_transit" />
                        <Label variant="inline" for="tl-edit-transit">{{ $t('project.timelineTransit') }}</Label>
                      </div>
                      <div class="flex items-center gap-3 [&_[data-slot=button][data-variant=default]]:ml-0">
                        <Button type="submit" :disabled="editTimelineForm.processing">{{ $t('common.save') }}</Button>
                        <Button type="button" variant="ghost" @click="editingTimelineEntry = null">{{ $t('common.cancel') }}</Button>
                      </div>
                    </form>

                    <div v-else class="group flex items-center gap-3 text-small leading-[1.35]" :class="entry.isTransit && 'text-ink-2 italic'">
                      <span class="w-[44px] flex-none tabular-nums">{{ entry.time }}</span>
                      <span class="min-w-0 flex-1">
                        {{ entry.title }}
                        <span v-if="entry.location" class="ml-1.5 text-ink-2">{{ entry.location }}</span>
                        <span v-if="entry.note" class="ml-1.5 text-faint">{{ entry.note }}</span>
                      </span>
                      <span v-if="header.canManageShoots" class="hidden flex-none items-center gap-2.5 group-hover:flex">
                        <button type="button" class="cursor-pointer text-small text-ring" @click="startEditTimelineEntry(entry)">{{ $t('common.edit') }}</button>
                        <button type="button" class="cursor-pointer text-small text-ink-2" @click="removeTimelineEntry(entry)">{{ $t('project.timelineRemove') }}</button>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Uhrzeit und Titel reichen für die meisten Zeilen; Ort,
                     Hinweis und Fahrt/Übergang stehen erst hinter dem Link,
                     damit ein ganzer Ablauf nicht ein Formular pro Zeile ist. -->
                <form
                  v-if="shoot.date && addingTimelineFor === shoot.id"
                  class="mt-2.5 flex flex-col gap-2 rounded-md bg-sunk p-2.5"
                  @submit.prevent="addTimelineEntry(shoot)"
                >
                  <div class="flex gap-2">
                    <Label :for="`tl-time-${shoot.id}`" class="sr-only">{{ $t('project.timelineTime') }}</Label>
                    <TimePicker :id="`tl-time-${shoot.id}`" v-model="timelineForm.time" class="w-[104px]" required />
                    <Label :for="`tl-title-${shoot.id}`" class="sr-only">{{ $t('project.timelineTitle') }}</Label>
                    <Input :id="`tl-title-${shoot.id}`" v-model="timelineForm.title" class="min-w-0 flex-1" type="text" :placeholder="$t('project.timelineTitle')" required />
                    <Button type="submit" variant="outline" class="px-2.5" :disabled="timelineForm.processing" :aria-label="$t('common.save')">
                      <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
                    </Button>
                  </div>
                  <div v-if="timelineDetails" class="flex gap-2">
                    <Label :for="`tl-location-${shoot.id}`" class="sr-only">{{ $t('project.timelineLocation') }}</Label>
                    <Input :id="`tl-location-${shoot.id}`" v-model="timelineForm.location" class="min-w-0 flex-1" type="text" :placeholder="$t('project.timelineLocation')" />
                    <Label :for="`tl-note-${shoot.id}`" class="sr-only">{{ $t('project.timelineNote') }}</Label>
                    <Input :id="`tl-note-${shoot.id}`" v-model="timelineForm.note" class="min-w-0 flex-1" type="text" :placeholder="$t('project.timelineNote')" />
                  </div>
                  <div v-if="timelineDetails" class="flex items-center gap-1.5">
                    <Checkbox id="tl-transit" v-model="timelineForm.is_transit" />
                    <Label variant="inline" for="tl-transit">{{ $t('project.timelineTransit') }}</Label>
                  </div>
                  <button v-else type="button" class="w-fit cursor-pointer text-left text-small text-ink-2" @click="timelineDetails = true">
                    {{ $t('project.timelineMore') }}
                  </button>
                </form>

                <div class="mt-2.5 flex items-center gap-2">
                  <Button v-if="shoot.date && header.canManageShoots" type="button" variant="outline" @click="addingTimelineFor === shoot.id ? (addingTimelineFor = null) : startTimelineEntry(shoot)">
                    {{ $t(addingTimelineFor === shoot.id ? 'project.timelineDone' : 'project.timelineEdit') }}
                  </Button>
                  <Button as="a" variant="outline" :href="shoot.pdfUrl" download>{{ $t('project.timelinePdf') }}</Button>
                  <Button as="a" variant="outline" :href="shoot.icsUrl" download>{{ $t('project.timelineIcs') }}</Button>
                </div>
              </div>
            </template>
          </Card>
        </div>

        <!-- One dialog for the whole list rather than one per row: only one shoot
             can be being cancelled at a time, and N hidden dialogs in a v-for is N
             portals mounted for nothing. -->
        <Dialog
          :open="cancelling !== null"
          @update:open="(open) => { if (!open) cancelling = null }"
        >
          <DialogContent class="max-w-[440px]">
            <DialogHeader>
              <DialogTitle>{{ $t('project.cancel') }}</DialogTitle>
              <DialogDescription v-if="cancelling">
                {{ cancelling.label ?? cancelling.kind }}
              </DialogDescription>
            </DialogHeader>

            <DialogBody>
              <form id="shoot-cancel-form" class="flex min-w-0 flex-col gap-1.5" @submit.prevent="confirmCancel">
                <Label for="cancel-reason">{{ $t('project.cancelReason') }}</Label>
                <Textarea id="cancel-reason" v-model="cancelReason" rows="3" required />
              </form>
            </DialogBody>

            <DialogFooter>
              <!-- the reason is not optional: it is what the row and the client's
                   portal show in place of the shoot afterwards -->
              <Button type="submit" form="shoot-cancel-form" tone="destructive" :disabled="!cancelReason.trim()">
                {{ $t('project.cancel') }}
              </Button>
              <DialogClose as-child>
                <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
              </DialogClose>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- Bleibt offen, bis jemand Fertig tippt: jede Zeile weist für sich
             zu, sodass eine zweite Person keinen neuen Dialog braucht. -->
        <Dialog
          :open="assigningId !== null"
          @update:open="(open) => { if (!open) assigningId = null }"
        >
          <DialogContent class="max-w-[440px]">
            <DialogHeader>
              <DialogTitle>{{ $t('project.assignCrew') }}</DialogTitle>
              <DialogDescription v-if="assigningShoot">
                {{ assigningShoot.label ?? assigningShoot.kind }}
              </DialogDescription>
            </DialogHeader>

            <DialogBody>
              <div class="flex flex-col gap-0.5">
                <div
                  v-for="member in candidates"
                  :key="member.id"
                  class="flex items-center gap-2.5 rounded-md px-2 py-1.5 text-small leading-[1.35]"
                  :class="(assignedIds.has(member.id) || member.status !== 'free') && 'opacity-55'"
                >
                  <span class="min-w-0 flex-1">{{ member.name }}</span>
                  <span v-if="assignedIds.has(member.id)" class="flex items-center gap-1.5 text-small font-medium text-ok">
                    <svg viewBox="0 0 24 24"><path d="M4 12l5 5L20 6" /></svg>
                    {{ $t('project.crewAssigned') }}
                  </span>
                  <span v-else-if="member.status === 'absent'" class="text-small text-due">
                    {{ $t('project.crewAway') }} · {{ member.note }}
                  </span>
                  <span v-else-if="member.status === 'busy'" class="text-small text-ink-2">
                    {{ $t('project.crewBusy') }} · {{ member.note }}
                  </span>
                  <Button v-else type="button" variant="outline" @click="assignCandidate(member.id)">
                    {{ $t('project.crewAdd') }}
                  </Button>
                </div>
              </div>
            </DialogBody>

            <DialogFooter>
              <DialogClose as-child>
                <Button type="button">{{ $t('common.close') }}</Button>
              </DialogClose>
            </DialogFooter>
          </DialogContent>
        </Dialog>
    </ProjectShell>
  </AdminLayout>
</template>
