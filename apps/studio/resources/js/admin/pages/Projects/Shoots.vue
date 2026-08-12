<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import CrewStack from '@/admin/components/CrewStack.vue'
import { chipClass, dayAndMonth, weekday } from '@/admin/lib/shoots'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  shoots: App.Crm.Data.ShootData[]
  staff: App.Data.StaffProfileData[]
  crewRoles: { value: string; label: string }[]
}>()

const adding = ref(false)
const form = useForm({
  kind: 'portrait',
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

function designatePrimary(shoot: App.Crm.Data.ShootData) {
  router.post(`/shootings/${shoot.id}/haupttermin`, {}, { preserveScroll: true })
}

function assign(shoot: App.Crm.Data.ShootData, user: string, role: string) {
  if (user) {
    router.post(`/shootings/${shoot.id}/crew`, { user, role }, { preserveScroll: true })
  }
}

function unassign(member: App.Crm.Data.CrewMemberData) {
  router.delete(`/crew/${member.id}`, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectHeader :header="header" active="shoots" />

    <div class="hrow" style="margin-bottom: var(--s4)">
      <span class="kick" style="margin-bottom: 0">
        {{ $t('board.shootCount', header.shootCount) }}
      </span>
      <!-- Adding an occasion is a discrete act against a list you are reading:
           inline, the form pushed every existing shoot down the page and the
           list you were comparing against scrolled out of sight. -->
      <Dialog v-if="header.canManageShoots" v-model:open="adding">
        <DialogTrigger as-child>
          <Button type="button">
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
            <form id="shoot-create-form" class="fgrid" @submit.prevent="create">
              <div class="fld">
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

              <div class="fld">
                <Label for="label">{{ $t('project.shootLabel') }}</Label>
                <Input id="label" v-model="form.label" type="text" />
              </div>

              <div class="fld">
                <Label for="date">
                  {{ $t('project.shootDate') }}<em>{{ $t('project.optional') }}</em>
                </Label>
                <Input id="date" v-model="form.date" type="date" />
                <p class="hint">{{ $t('project.shootDateHint') }}</p>
              </div>

              <div class="two">
                <div class="fld">
                  <Label for="call_time">{{ $t('project.callTime') }}</Label>
                  <Input id="call_time" v-model="form.call_time" class="tabular-nums" type="time" />
                </div>
                <div class="fld">
                  <Label for="end_time">{{ $t('project.endTime') }}</Label>
                  <Input id="end_time" v-model="form.end_time" class="tabular-nums" type="time" />
                  <p v-if="form.errors.end_time" class="err">{{ form.errors.end_time }}</p>
                </div>
              </div>

              <div class="fld">
                <Label for="venue_name">{{ $t('project.venueName') }}</Label>
                <Input id="venue_name" v-model="form.venue_name" type="text" />
              </div>

              <div class="fld">
                <Label for="venue_city">{{ $t('project.venueCity') }}</Label>
                <Input id="venue_city" v-model="form.venue_city" type="text" />
              </div>

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
    <div class="sess">
      <section
        v-for="shoot in shoots"
        :key="shoot.id"
        class="srow"
        :class="{ open: !shoot.isCancelled && shoot.isPrimary, cancelled: shoot.isCancelled }"
      >
        <div class="stop">
          <div class="sdate">
            {{ dayAndMonth(shoot.date) ?? '—' }}
            <em>{{ weekday(shoot.date) ?? $t('board.openDate') }}</em>
          </div>
          <div class="sname">
            {{ shoot.label ?? shoot.kind }}
            <span v-if="shoot.isPrimary" class="prim">{{ $t('project.primary') }}</span>
            <span>{{ [shoot.venue, shoot.callTime && `${shoot.callTime}–${shoot.endTime ?? ''}`].filter(Boolean).join(' · ') || '—' }}</span>
          </div>
          <CrewStack :crew="shoot.crew" />
          <span class="chip" :class="chipClass(shoot, header.position.value)">
            {{ shoot.statusLabel }}
          </span>
        </div>

        <div v-if="shoot.isCancelled" class="flag">
          {{ $t('project.cancelled', { reason: shoot.cancelledReason }) }}
        </div>

        <template v-else>
          <div class="plist" style="margin-top: var(--s3)">
            <div v-for="member in shoot.crew" :key="member.id" class="prow">
              <span class="pavatar">{{ member.initials }}</span>
              <div class="pinfo">
                <div class="pname">{{ member.name }}</div>
              </div>
              <div class="proles">
                <Select
                  v-if="header.canManageCrew"
                  :model-value="member.role"
                  @update:model-value="(value) => assign(shoot, member.userId, String(value))"
                >
                  <SelectTrigger size="sm" :aria-label="$t('project.crewRole', { name: member.name })">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="role in crewRoles" :key="role.value" :value="role.value">
                      {{ role.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <Button v-if="header.canManageCrew" type="button" variant="outline" @click="unassign(member)">
                  {{ $t('project.unassign') }}
                </Button>
              </div>
            </div>
          </div>

          <!-- T-10: CheckDateAvailability's third source, already narrowed by
               ProjectController::shootsOf() to this shoot's date and this
               shoot's own crew. Warns, same as every other conflict that
               action produces — the assignment above stays exactly as
               possible whether or not this shows. -->
          <div v-if="shoot.crewConflicts.length" style="margin-top: var(--s3)">
            <div v-for="conflict in shoot.crewConflicts" :key="conflict.personId ?? undefined" class="flag">
              {{ $t('project.crewAbsence', { label: conflict.label }) }}
            </div>
          </div>

          <div v-if="header.canManageShoots || header.canManageCrew" class="formfoot" style="margin-top: var(--s3); gap: var(--s3)">
            <!-- These two are menus of actions, not fields: nothing stores
                 what was picked, so model-value is pinned to '' and the
                 control falls straight back to its own name. That is what the
                 <option value=""> label did on the native select. -->
            <Select
              v-if="header.canManageShoots"
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

            <Select
              v-if="header.canManageCrew"
              model-value=""
              @update:model-value="(value) => assign(shoot, String(value), crewRoles[0].value)"
            >
              <SelectTrigger size="sm" style="width: auto" :aria-label="$t('project.assignCrew')">
                <SelectValue :placeholder="$t('project.assignCrew')" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="member in staff" :key="member.id" :value="member.id">
                  {{ member.name }}
                </SelectItem>
              </SelectContent>
            </Select>

            <Button v-if="!shoot.isPrimary && header.canManageShoots" type="button" variant="outline" @click="designatePrimary(shoot)">
              {{ $t('project.makePrimary') }}
            </Button>

            <Button
              v-if="header.canManageShoots"
              type="button"
              variant="outline"
              tone="accent"
              @click="openCancel(shoot)"
            >
              {{ $t('project.cancel') }}
            </Button>
          </div>

          <!-- E-04: the day-of schedule. Read-only structure matches mockup
               04's .tl block exactly — editing and export sit in their own
               formfoot below it, the same grammar the crew section above
               already uses. -->
          <div
            v-if="shoot.timeline.length"
            class="tl"
            style="margin-top: var(--s3); padding-top: var(--s3); border-top: 1px solid var(--line)"
          >
            <div v-for="entry in shoot.timeline" :key="entry.id" class="tlrow" :class="{ move: entry.isTransit }">
              <div class="tltime">{{ entry.time }}</div>
              <div class="tlrail"><span class="dot"></span></div>
              <div class="tlbody">
                <div class="tlname">{{ entry.title }}</div>
                <div v-if="entry.location" class="tlwhere">{{ entry.location }}</div>
                <div v-if="entry.note" class="tlwho">{{ entry.note }}</div>
              </div>
            </div>
          </div>

          <div v-if="shoot.date" class="formfoot" style="margin-top: var(--s3)">
            <Button v-if="header.canManageShoots" type="button" variant="ghost" @click="toggleTimelineEdit(shoot)">
              {{ $t(editingTimeline === shoot.id ? 'project.timelineDone' : 'project.timelineEdit') }}
            </Button>
            <Button as="a" variant="ghost" :href="shoot.pdfUrl" download>{{ $t('project.timelinePdf') }}</Button>
            <Button as="a" variant="ghost" :href="shoot.icsUrl" download>{{ $t('project.timelineIcs') }}</Button>
          </div>

          <div v-if="shoot.date && editingTimeline === shoot.id && header.canManageShoots" class="card static" style="margin-top: var(--s3)">
            <form class="fgrid" @submit.prevent="addTimelineEntry(shoot)">
              <div class="fld">
                <Label for="tl-time">{{ $t('project.timelineTime') }}</Label>
                <Input id="tl-time" v-model="timelineForm.time" class="tabular-nums" type="time" required />
              </div>
              <div class="fld wide">
                <Label for="tl-title">{{ $t('project.timelineTitle') }}</Label>
                <Input id="tl-title" v-model="timelineForm.title" type="text" required />
              </div>
              <div class="fld">
                <Label for="tl-location">
                  {{ $t('project.timelineLocation') }}<em>{{ $t('project.optional') }}</em>
                </Label>
                <Input id="tl-location" v-model="timelineForm.location" type="text" />
              </div>
              <div class="fld">
                <Label for="tl-note">
                  {{ $t('project.timelineNote') }}<em>{{ $t('project.optional') }}</em>
                </Label>
                <Input id="tl-note" v-model="timelineForm.note" type="text" />
              </div>
              <div class="fld wide">
                <div class="cbrow" style="gap: 6px">
                  <Checkbox id="tl-transit" v-model="timelineForm.is_transit" />
                  <Label variant="inline" for="tl-transit">{{ $t('project.timelineTransit') }}</Label>
                </div>
              </div>

              <div class="fld wide">
                <div class="formfoot" style="margin-top: 0">
                  <Button type="submit" :disabled="timelineForm.processing">
                    {{ $t('common.save') }}
                  </Button>
                </div>
              </div>
            </form>

            <div v-if="shoot.timeline.length" style="margin-top: var(--s4); display: flex; flex-direction: column; gap: var(--s2)">
              <div v-for="entry in shoot.timeline" :key="entry.id" class="hrow">
                <span>{{ entry.time }} · {{ entry.title }}</span>
                <Button type="button" variant="outline" tone="accent" style="margin-left: auto" @click="removeTimelineEntry(entry)">
                  {{ $t('project.timelineRemove') }}
                </Button>
              </div>
            </div>
            <p v-else class="hint">{{ $t('project.timelineEmpty') }}</p>
          </div>
        </template>
      </section>

      <p v-if="!shoots.length" class="hint">{{ $t('project.noShoots') }}</p>
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
          <form id="shoot-cancel-form" class="fld" @submit.prevent="confirmCancel">
            <Label for="cancel-reason">{{ $t('project.cancelReason') }}</Label>
            <Textarea id="cancel-reason" v-model="cancelReason" rows="3" required />
          </form>
        </DialogBody>

        <DialogFooter>
          <!-- the reason is not optional: it is what the row and the client's
               portal show in place of the shoot afterwards -->
          <Button type="submit" form="shoot-cancel-form" tone="warm" :disabled="!cancelReason.trim()">
            {{ $t('project.cancel') }}
          </Button>
          <DialogClose as-child>
            <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>
