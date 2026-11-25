<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { ChevronDown, Ellipsis } from 'lucide-vue-next'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/crm/ProjectShell.vue'
import ShootAdvance from '@/admin/components/crm/ShootAdvance.vue'
import ShootTimeline from '@/admin/components/crm/ShootTimeline.vue'
import { chipClass, filterShoots, shootDays, type ShootFilter } from '@/admin/lib/shoots'
import { dayWithWeekday } from '@/admin/lib/dates'
import { Alert } from '@/ui/alert'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { TimePicker } from '@/ui/time-picker'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { useLiveProps } from '@/admin/lib/liveReload'
import Chip, { type ChipTone } from '@/ui/page/Chip.vue'

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


const today = new Date().toISOString().slice(0, 10)

function metrics(shoot: App.Crm.Data.ShootData) {
  return [
    { label: 'project.shootKind', value: shoot.kind },
    { label: 'project.callTime', value: shoot.callTime ?? '—' },
    { label: 'project.endTime', value: shoot.endTime ?? '—' },
    { label: 'project.venueName', value: shoot.venue ?? '—' },
  ]
}

const filter = ref<ShootFilter>('all')

const filters = computed(() => [
  { key: 'all' as const, label: 'project.filterAll' },
  { key: 'upcoming' as const, label: 'project.filterUpcoming' },
  { key: 'delivered' as const, label: 'project.filterDelivered' },
].map((tab) => ({ ...tab, count: filterShoots(props.shoots, tab.key, today).length })))

const days = computed(() => shootDays(filterShoots(props.shoots, filter.value, today)))

const opened = reactive(new Set<string>())

function toggle(shoot: App.Crm.Data.ShootData) {
  opened.has(shoot.id) ? opened.delete(shoot.id) : opened.add(shoot.id)
}

function create() {
  form.post(`/auftraege/${props.header.id}/shootings`, {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      adding.value = false
    },
  })
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

const STATIC_TONE: Record<string, ChipTone> = { done: 'success', now: 'warn', next: 'neutral', off: 'neutral' }
</script>

<template>
  <AdminLayout :active="header.isBooked ? 'projekte' : 'anfragen'">
    <ProjectShell :header="header" active="shoots">
        <template v-if="header.canManageShoots" #actions>
          <Dialog v-model:open="adding">
            <DialogTrigger as-child>
              <Button variant="outline" type="button">
                + {{ $t('project.shootAdd') }}
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
                <Button class="ml-auto" type="submit" form="shoot-create-form" :disabled="form.processing">
                  {{ $t('common.save') }}
                </Button>
                <DialogClose as-child>
                  <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
                </DialogClose>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </template>

        <ChoiceGroup
          v-model="filter"
          class="mb-3 w-fit"
          :options="filters.map((tab) => ({ value: tab.key, label: `${$t(tab.label)} ${tab.count}` }))"
        />

        <div class="flex flex-col gap-3">
          <section v-for="day in days" :key="day.key" class="surface-card">
            <header class="flex items-baseline gap-3 px-6 pt-4 pb-2 max-[640px]:px-4">
              <h2 class="m-0 text-[15px] leading-[1.2] font-semibold tracking-[-0.015em]">
                {{ dayWithWeekday(day.key || null, null) ?? $t('board.openDate') }}
              </h2>
              <span v-if="day.items.length > 1" class="text-small text-ink-2">{{ $t('project.dayCount', day.items.length) }}</span>
            </header>

            <div v-for="shoot in day.items" :key="shoot.id" class="shadow-[inset_0_1px_0_var(--rule)]">
              <div class="flex items-center gap-3.5 px-6 py-1.5 max-[640px]:flex-wrap max-[640px]:px-4">
                <button
                  type="button"
                  class="flex min-h-10 min-w-0 flex-1 cursor-pointer items-center gap-2 text-left"
                  :aria-expanded="opened.has(shoot.id)"
                  @click="toggle(shoot)"
                >
                  <ChevronDown class="size-[15px] flex-none text-ink-2 transition-transform duration-[150ms] ease-out" :class="opened.has(shoot.id) && 'rotate-180'" />
                  <span class="min-w-0 flex-1 text-ui leading-[1.47]">
                    {{ shoot.label ?? shoot.kind }}
                    <span v-if="shoot.venue" class="block truncate text-small leading-[1.47] text-ink-2">{{ shoot.venue }}</span>
                  </span>
                </button>

                <span v-if="!soloStudio" class="w-[140px] flex-none truncate text-small leading-[1.47] text-ink-2 max-[640px]:w-auto">
                  {{ shoot.crew.map((member) => member.name).join(', ') || '—' }}
                </span>

                <span v-if="shoot.isPrimary" class="text-small leading-[1.5] font-semibold text-foreground">
                  {{ $t('project.primary') }}
                </span>
                <Chip :tone="STATIC_TONE[chipClass(shoot, header.position.value)]">
                  {{ shoot.statusLabel }}
                </Chip>

                <template v-if="!shoot.isCancelled && header.canManageShoots">
                  <ShootAdvance :shoot="shoot" />

                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="secondary" size="icon" type="button" class="max-[640px]:min-h-11 max-[640px]:min-w-11" :aria-label="$t('project.more')">
                        <Ellipsis class="size-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[208px]">
                      <DropdownMenuItem v-if="!shoot.isPrimary" @select="designatePrimary(shoot)">
                        {{ $t('project.makePrimary') }}
                      </DropdownMenuItem>
                      <DropdownMenuItem class="text-destructive" @select="openCancel(shoot)">
                        {{ $t('project.cancel') }}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </template>
              </div>

              <div v-if="opened.has(shoot.id)" class="px-6 pt-1 pb-5 max-[640px]:px-4">
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

                  <ShootTimeline :shoot="shoot" :manageable="header.canManageShoots" />
                </template>
              </div>
            </div>
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
              <Button class="ml-auto" type="submit" form="shoot-cancel-form" tone="destructive">
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
                <Button class="ml-auto" type="button">{{ $t('common.close') }}</Button>
              </DialogClose>
            </DialogFooter>
          </DialogContent>
        </Dialog>
    </ProjectShell>
  </AdminLayout>
</template>
