<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import CrewStack from '@/admin/components/CrewStack.vue'
import { chipClass, dayAndMonth, weekday } from '@/admin/lib/shoots'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import Section from '@/ui/page/Section.vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { TimePicker } from '@/ui/time-picker'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  shoots: App.Crm.Data.ShootData[]
  crewAvailability: Record<string, App.Crm.Data.CrewCandidateData[]>
  crewRoles: { value: string; label: string }[]
}>()

const adding = ref(false)
const form = useForm({
  kind: 'portrait',
  entered_at: '',
  label: '',
  date: '',
  call_time: '',
  end_time: '',
  venue_name: '',
  venue_city: '',
})

// E-04: which shoot's Ablauf is open for editing, at most one at a time.
const editingTimeline = ref<string | null>(null)
const timelineForm = useForm({
  time: '',
  title: '',
  location: '',
  note: '',
  is_transit: false,
})

function toggleTimelineEdit(shoot: App.Crm.Data.ShootData) {
  editingTimeline.value = editingTimeline.value === shoot.id ? null : shoot.id
  timelineForm.reset()
}

function addTimelineEntry(shoot: App.Crm.Data.ShootData) {
  timelineForm.post(`/shootings/${shoot.id}/ablauf`, {
    preserveScroll: true,
    onSuccess: () => timelineForm.reset(),
  })
}

function removeTimelineEntry(entry: App.Crm.Data.TimelineEntryData) {
  router.delete(`/ablauf/${entry.id}`, { preserveScroll: true })
}

const kinds = [
  { value: 'engagement', label: 'Verlobung' },
  { value: 'wedding', label: 'Hochzeit' },
  { value: 'reception', label: 'Empfang' },
  { value: 'portrait', label: 'Portrait' },
  { value: 'other', label: 'Sonstiges' },
]

const today = new Date().toISOString().slice(0, 10)

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

const assigning = ref<App.Crm.Data.ShootData | null>(null)
const crewUser = ref('')
const crewRole = ref(props.crewRoles[0].value)

function openAssign(shoot: App.Crm.Data.ShootData) {
  assigning.value = shoot
  crewUser.value = ''
  crewRole.value = props.crewRoles[0].value
}

const assignedIds = computed(() => new Set(assigning.value?.crew.map((member) => member.userId) ?? []))
// CrewAvailability answered this for the shoot's own day, for everybody —
// not only for those already on it.
const candidates = computed(() => (assigning.value ? props.crewAvailability[assigning.value.id] ?? [] : []))

function confirmAssign() {
  const shoot = assigning.value
  if (!shoot || !crewUser.value) return

  router.post(`/shootings/${shoot.id}/crew`, { user: crewUser.value, role: crewRole.value }, {
    preserveScroll: true,
    onSuccess: () => (assigning.value = null),
  })
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
        <div class="mb-4 flex items-center gap-2.5">
          <div class="inline-flex gap-0.5 rounded-full bg-sunk-2 p-0.5">
            <button
              v-for="tab in filters"
              :key="tab.key"
              type="button"
              class="rounded-full px-2.5 py-1 text-[11.5px] leading-none text-ink-2"
              :class="filter === tab.key && 'bg-paper font-semibold text-foreground'"
              @click="filter = tab.key"
            >
              {{ $t(tab.label) }} {{ tab.count }}
            </button>
          </div>

          <!-- Adding an occasion is a discrete act against a list you are reading:
               inline, the form pushed every existing shoot down the page and the
               list you were comparing against scrolled out of sight. -->
          <Dialog v-if="header.canManageShoots" v-model:open="adding">
            <DialogTrigger as-child>
              <Button type="button" class="ml-auto">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
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
                    <Select :model-value="form.kind" @update:model-value="(value) => (form.kind = String(value))">
                      <SelectTrigger id="kind">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="kind in kinds" :key="kind.value" :value="kind.value">
                          {{ kind.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
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
                      <p v-if="form.errors.end_time" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.end_time }}</p>
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
                    <summary class="w-fit cursor-pointer text-[11.5px] text-ink-2 marker:text-ink-2">{{ $t('project.advanced') }}</summary>
                    <div class="mt-1.5 flex min-w-0 flex-col gap-1.5">
                      <Label for="entered_at">{{ $t('project.shootEnteredAt') }}</Label>
                      <DatePicker id="entered_at" v-model="form.entered_at" />
                      <p v-if="form.errors.entered_at" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.entered_at }}</p>
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
        </div>

        <!-- Depth behind the Shootings tab: every occasion in full. Only open
             shoots carry editable weight; a cancelled one keeps the status it died
             in and gains no controls back. -->
        <div class="flex flex-col gap-1.5">
          <section
            v-for="shoot in visible"
            :key="shoot.id"
            class="rounded-md border border-border p-2.5 [&>section]:mt-5"
            :class="{ open: !shoot.isCancelled && shoot.isPrimary, cancelled: shoot.isCancelled }"
          >
            <div class="flex items-center gap-2.5">
              <div class="w-[54px] flex-none text-[12px] leading-[1.2] font-semibold tabular-nums [&_em]:mt-0.5 [&_em]:block [&_em]:text-[10.5px] [&_em]:leading-[1.3] [&_em]:font-normal [&_em]:not-italic [&_em]:text-faint">
                {{ dayAndMonth(shoot.date) ?? '—' }}
                <em>{{ weekday(shoot.date) ?? $t('board.openDate') }}</em>
              </div>
              <div class="min-w-0 flex-1 text-[13px] leading-[1.3] font-semibold tracking-[-0.01em]">
                {{ shoot.label ?? shoot.kind }}
                <span v-if="shoot.isPrimary" class="ml-1.5 inline-block align-[1px] text-[9px] leading-[1.5] font-semibold tracking-[0.08em] uppercase text-foreground">{{ $t('project.primary') }}</span>
                <span class="mt-0.5 block text-[11.5px] leading-[1.35] font-normal tracking-normal text-muted-foreground">
                  {{ [shoot.venue, shoot.callTime && `${shoot.callTime}–${shoot.endTime ?? ''}`].filter(Boolean).join(' · ') || '—' }}
                </span>
              </div>
              <CrewStack :crew="shoot.crew" />
              <span :class="['flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase', TONE[chipClass(shoot, header.position.value)]]">
                {{ shoot.statusLabel }}
              </span>
            </div>

            <div v-if="shoot.isCancelled" class="mt-1.5 flex items-center gap-3 rounded-[7px] bg-late-bg bg-icon-alert bg-[length:13px_13px] bg-[position:9px_center] bg-no-repeat py-[7px] pr-[9px] pl-7 text-[12.5px] leading-[1.3] font-medium text-destructive">
              {{ $t('project.cancelled', { reason: shoot.cancelledReason }) }}
              <button
                v-if="header.canManageShoots"
                class="ml-auto cursor-pointer whitespace-nowrap text-[var(--accent)]"
                type="button"
                @click="uncancel(shoot)"
              >
                {{ $t('project.uncancel') }}
              </button>
            </div>

            <template v-else>
              <Section :title="$t('project.crew')">
                <template #actions>
                  <Button v-if="header.canManageCrew" type="button" variant="outline" @click="openAssign(shoot)">
                    <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
                    {{ $t('project.crew') }}
                  </Button>
                </template>

                <div class="flex flex-col gap-1">
                  <div v-for="member in shoot.crew" :key="member.id" class="flex items-center gap-2.5 py-1">
                    <span class="flex size-[22px] flex-none items-center justify-center rounded-full bg-sunk-2 text-[9.5px] leading-none font-semibold text-ink-2">{{ member.initials }}</span>
                    <span class="min-w-0 flex-1 text-[12.5px] leading-[1.35]">{{ member.name }}</span>
                    <span class="text-[11.5px] leading-[1.35] text-ink-2">{{ member.roleLabel }}</span>
                    <Button v-if="header.canManageCrew" type="button" variant="ghost" @click="unassign(member)">
                      {{ $t('project.unassign') }}
                    </Button>
                  </div>
                </div>

                <!-- T-10: CheckDateAvailability's third source, already narrowed by
                     ProjectController::shootsOf() to this shoot's date and this
                     shoot's own crew. Warns, same as every other conflict that
                     action produces — the assignment above stays exactly as
                     possible whether or not this shows. -->
                <div
                  v-for="conflict in shoot.crewConflicts"
                  :key="conflict.personId ?? undefined"
                  class="mt-2 flex items-center gap-1.5 text-[11.5px] leading-[1.35] font-medium text-due"
                >
                  {{ $t('project.crewAbsence', { label: conflict.label }) }}
                </div>
              </Section>

              <Section v-if="shoot.date || shoot.timeline.length" :title="$t('project.timeline')">
                <template #actions>
                  <Button v-if="header.canManageShoots" type="button" variant="outline" @click="toggleTimelineEdit(shoot)">
                    {{ $t(editingTimeline === shoot.id ? 'project.timelineDone' : 'project.timelineEdit') }}
                  </Button>
                  <Button as="a" variant="outline" :href="shoot.pdfUrl" download>{{ $t('project.timelinePdf') }}</Button>
                  <Button as="a" variant="outline" :href="shoot.icsUrl" download>{{ $t('project.timelineIcs') }}</Button>
                </template>

                <div v-for="entry in shoot.timeline" :key="entry.id" class="flex gap-3 py-1.5 text-[12.5px] leading-[1.35]"
                  :class="entry.isTransit && 'text-ink-2 italic'">
                  <span class="w-[52px] flex-none tabular-nums text-ink-2">{{ entry.time }}</span>
                  <span class="min-w-0 flex-1">
                    {{ entry.title }}
                    <span v-if="entry.location" class="ml-1.5 text-ink-2">{{ entry.location }}</span>
                    <span v-if="entry.note" class="ml-1.5 text-faint">{{ entry.note }}</span>
                  </span>
                </div>

                <form
                  v-if="shoot.date && editingTimeline === shoot.id && header.canManageShoots"
                  class="mt-2.5 grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4"
                  @submit.prevent="addTimelineEntry(shoot)"
                >
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="tl-time">{{ $t('project.timelineTime') }}</Label>
                    <TimePicker id="tl-time" v-model="timelineForm.time" required />
                  </div>
                  <div class="col-span-full flex min-w-0 flex-col gap-1.5">
                    <Label for="tl-title">{{ $t('project.timelineTitle') }}</Label>
                    <Input id="tl-title" v-model="timelineForm.title" type="text" required />
                  </div>
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="tl-location">
                      {{ $t('project.timelineLocation') }}<em>{{ $t('project.optional') }}</em>
                    </Label>
                    <Input id="tl-location" v-model="timelineForm.location" type="text" />
                  </div>
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="tl-note">
                      {{ $t('project.timelineNote') }}<em>{{ $t('project.optional') }}</em>
                    </Label>
                    <Input id="tl-note" v-model="timelineForm.note" type="text" />
                  </div>
                  <div class="col-span-full flex items-center gap-1.5">
                    <Checkbox id="tl-transit" v-model="timelineForm.is_transit" />
                    <Label variant="inline" for="tl-transit">{{ $t('project.timelineTransit') }}</Label>
                  </div>

                  <div class="col-span-full flex items-center gap-4 [&_[data-slot=button][data-variant=default]]:ml-0">
                    <Button type="submit" :disabled="timelineForm.processing">
                      {{ $t('common.save') }}
                    </Button>
                  </div>
                </form>

                <div v-if="editingTimeline === shoot.id && header.canManageShoots" class="mt-4 flex flex-col gap-2">
                  <div v-for="entry in shoot.timeline" :key="entry.id" class="flex items-center gap-4">
                    <span>{{ entry.time }} · {{ entry.title }}</span>
                    <Button type="button" variant="outline" tone="accent" class="ml-auto" @click="removeTimelineEntry(entry)">
                      {{ $t('project.timelineRemove') }}
                    </Button>
                  </div>
                </div>
              </Section>

              <div v-if="header.canManageShoots" class="mt-5 flex items-center gap-2.5 [&_[data-slot=button][data-variant=default]]:ml-0">
                <!-- A menu of actions, not a field: nothing stores what was
                     picked, so model-value is pinned to '' and the control falls
                     straight back to its own name. -->
                <Select
                  model-value=""
                  :disabled="!shoot.forwardStatuses.length"
                  @update:model-value="(value) => advance(shoot, String(value))"
                >
                  <SelectTrigger size="sm" style="width: auto" :aria-label="$t('project.advance')">
                    <SelectValue :placeholder="$t('project.advance')" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="status in shoot.forwardStatuses" :key="status.value" :value="status.value">
                      {{ status.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>

                <Button v-if="!shoot.isPrimary" type="button" variant="outline" @click="designatePrimary(shoot)">
                  {{ $t('project.makePrimary') }}
                </Button>

                <Button type="button" variant="outline" tone="accent" @click="openCancel(shoot)">
                  {{ $t('project.cancel') }}
                </Button>
              </div>
            </template>
          </section>

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

        <Dialog
          :open="assigning !== null"
          @update:open="(open) => { if (!open) assigning = null }"
        >
          <DialogContent class="max-w-[440px]">
            <DialogHeader>
              <DialogTitle>{{ $t('project.assignCrew') }}</DialogTitle>
              <DialogDescription v-if="assigning">
                {{ assigning.label ?? assigning.kind }}
              </DialogDescription>
            </DialogHeader>

            <DialogBody>
              <div class="flex flex-col gap-0.5">
                <button
                  v-for="member in candidates"
                  :key="member.id"
                  type="button"
                  class="flex items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-[12.5px] leading-[1.35] hover:bg-muted"
                  :class="crewUser === member.id && 'bg-muted'"
                  @click="crewUser = member.id"
                >
                  <span class="min-w-0 flex-1">{{ member.name }}</span>
                  <span v-if="assignedIds.has(member.id)" class="text-[11.5px] text-ink-2">{{ $t('project.crewAssigned') }}</span>
                  <span v-else-if="member.status === 'absent'" class="text-[11.5px] text-due">
                    {{ $t('project.crewAway') }} · {{ member.note }}
                  </span>
                  <span v-else-if="member.status === 'busy'" class="text-[11.5px] text-ink-2">
                    {{ $t('project.crewBusy') }} · {{ member.note }}
                  </span>
                </button>
              </div>

              <div class="mt-4 flex min-w-0 flex-col gap-1.5">
                <Label for="crew-role">{{ $t('project.crewRoleLabel') }}</Label>
                <Select :model-value="crewRole" @update:model-value="(value) => (crewRole = String(value))">
                  <SelectTrigger id="crew-role">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="role in crewRoles" :key="role.value" :value="role.value">
                      {{ role.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </DialogBody>

            <DialogFooter>
              <Button type="button" :disabled="!crewUser" @click="confirmAssign">
                {{ $t('common.save') }}
              </Button>
              <DialogClose as-child>
                <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
              </DialogClose>
            </DialogFooter>
          </DialogContent>
        </Dialog>
    </ProjectShell>
  </AdminLayout>
</template>
