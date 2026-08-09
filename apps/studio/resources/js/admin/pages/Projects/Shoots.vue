<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import CrewStack from '@/admin/components/CrewStack.vue'
import { chipClass, dayAndMonth, weekday } from '@/admin/lib/shoots'

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  shoots: App.Crm.Data.ShootData[]
  staff: App.Data.StaffProfileData[]
  crewRoles: { value: string; label: string }[]
}>()

const { t } = useI18n()

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

function cancel(shoot: App.Crm.Data.ShootData) {
  const reason = prompt(t('project.cancelReason'))

  if (reason) {
    router.post(`/shootings/${shoot.id}/absage`, { reason }, { preserveScroll: true })
  }
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
      <button v-if="header.canManageShoots" class="btn" type="button" @click="adding = !adding">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('project.addShoot') }}
      </button>
    </div>

    <div v-if="adding && header.canManageShoots" class="card static" style="margin-bottom: var(--s4)">
      <div class="cardhead"><span class="kick">{{ $t('project.addShoot') }}</span></div>

      <form class="fgrid" @submit.prevent="create">
        <div class="fld">
          <label for="kind">{{ $t('project.shootKind') }}</label>
          <select id="kind" v-model="form.kind" class="inp">
            <option v-for="kind in kinds" :key="kind.value" :value="kind.value">
              {{ kind.label }}
            </option>
          </select>
        </div>

        <div class="fld">
          <label for="label">{{ $t('project.shootLabel') }}</label>
          <input id="label" v-model="form.label" class="inp" type="text" />
        </div>

        <div class="fld">
          <label for="date">
            {{ $t('project.shootDate') }}<em>{{ $t('project.optional') }}</em>
          </label>
          <input id="date" v-model="form.date" class="inp" type="date" />
          <p class="hint">{{ $t('project.shootDateHint') }}</p>
        </div>

        <div class="two">
          <div class="fld">
            <label for="call_time">{{ $t('project.callTime') }}</label>
            <input id="call_time" v-model="form.call_time" class="inp num" type="time" />
          </div>
          <div class="fld">
            <label for="end_time">{{ $t('project.endTime') }}</label>
            <input id="end_time" v-model="form.end_time" class="inp num" type="time" />
            <p v-if="form.errors.end_time" class="err">{{ form.errors.end_time }}</p>
          </div>
        </div>

        <div class="fld">
          <label for="venue_name">{{ $t('project.venueName') }}</label>
          <input id="venue_name" v-model="form.venue_name" class="inp" type="text" />
        </div>

        <div class="fld">
          <label for="venue_city">{{ $t('project.venueCity') }}</label>
          <input id="venue_city" v-model="form.venue_city" class="inp" type="text" />
        </div>

        <div class="fld wide">
          <div class="formfoot" style="margin-top: 0">
            <button class="btn" type="submit" :disabled="form.processing">
              {{ $t('common.save') }}
            </button>
            <button class="txtbtn" type="button" @click="adding = false">
              {{ $t('common.cancel') }}
            </button>
          </div>
        </div>
      </form>
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
                <select
                  v-if="header.canManageCrew"
                  class="inp sm"
                  :value="member.role"
                  :aria-label="$t('project.crewRole', { name: member.name })"
                  @change="assign(shoot, member.userId, ($event.target as HTMLSelectElement).value)"
                >
                  <option v-for="role in crewRoles" :key="role.value" :value="role.value">
                    {{ role.label }}
                  </option>
                </select>
                <button v-if="header.canManageCrew" type="button" class="abtn" @click="unassign(member)">
                  {{ $t('project.unassign') }}
                </button>
              </div>
            </div>
          </div>

          <div v-if="header.canManageShoots || header.canManageCrew" class="formfoot" style="margin-top: var(--s3); gap: var(--s3)">
            <select
              v-if="header.canManageShoots"
              class="inp sm"
              style="width: auto"
              :disabled="!shoot.forwardStatuses.length"
              :aria-label="$t('project.advance')"
              @change="advance(shoot, ($event.target as HTMLSelectElement).value)"
            >
              <option value="">{{ $t('project.advance') }}</option>
              <option v-for="status in shoot.forwardStatuses" :key="status.value" :value="status.value">
                {{ status.label }}
              </option>
            </select>

            <select
              v-if="header.canManageCrew"
              class="inp sm"
              style="width: auto"
              :aria-label="$t('project.assignCrew')"
              @change="assign(shoot, ($event.target as HTMLSelectElement).value, crewRoles[0].value)"
            >
              <option value="">{{ $t('project.assignCrew') }}</option>
              <option v-for="member in staff" :key="member.id" :value="member.id">
                {{ member.name }}
              </option>
            </select>

            <button v-if="!shoot.isPrimary && header.canManageShoots" type="button" class="abtn" @click="designatePrimary(shoot)">
              {{ $t('project.makePrimary') }}
            </button>

            <button v-if="header.canManageShoots" type="button" class="abtn hot" @click="cancel(shoot)">
              {{ $t('project.cancel') }}
            </button>
          </div>
        </template>
      </section>

      <p v-if="!shoots.length" class="hint">{{ $t('project.noShoots') }}</p>
    </div>
  </AdminLayout>
</template>
