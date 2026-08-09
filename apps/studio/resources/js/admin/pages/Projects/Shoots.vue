<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import CrewStack from '@/admin/components/CrewStack.vue'
import { Button } from '@/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/ui/card'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

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

// The nine cases with their German labels come from the server on every shoot,
// already filtered to the legal forward moves — the rule lives in ShootStatus,
// not here.
const kinds = [
  { value: 'engagement', label: 'Verlobung' },
  { value: 'wedding', label: 'Hochzeit' },
  { value: 'reception', label: 'Empfang' },
  { value: 'portrait', label: 'Portrait' },
  { value: 'other', label: 'Sonstiges' },
]

function longDate(date: string | null) {
  return date
    ? new Date(date).toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit' })
    : null
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
  router.post(`/shootings/${shoot.id}/crew`, { user, role }, { preserveScroll: true })
}

function unassign(member: App.Crm.Data.CrewMemberData) {
  router.delete(`/crew/${member.id}`, { preserveScroll: true })
}
</script>

<template>
  <main class="mx-auto max-w-4xl space-y-6 p-6">
    <ProjectHeader :header="header" active="shoots" />

    <div class="flex items-center justify-between">
      <p class="text-xs uppercase tracking-wide text-muted-foreground">
        {{ $t('board.shootCount', header.shootCount) }}
      </p>
      <Button size="sm" @click="adding = !adding">{{ $t('project.addShoot') }}</Button>
    </div>

    <Card v-if="adding">
      <CardHeader><CardTitle class="text-sm font-medium">{{ $t('project.addShoot') }}</CardTitle></CardHeader>
      <CardContent>
        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="create">
          <div class="space-y-1.5">
            <Label for="kind">{{ $t('project.shootKind') }}</Label>
            <select id="kind" v-model="form.kind" class="h-9 w-full rounded-md border px-3 text-sm">
              <option v-for="kind in kinds" :key="kind.value" :value="kind.value">
                {{ kind.label }}
              </option>
            </select>
          </div>

          <div class="space-y-1.5">
            <Label for="label">{{ $t('project.shootLabel') }}</Label>
            <Input id="label" v-model="form.label" />
          </div>

          <div class="space-y-1.5">
            <Label for="date">{{ $t('project.shootDate') }}</Label>
            <Input id="date" v-model="form.date" type="date" />
            <p class="text-xs text-muted-foreground">{{ $t('project.shootDateHint') }}</p>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1.5">
              <Label for="call_time">{{ $t('project.callTime') }}</Label>
              <Input id="call_time" v-model="form.call_time" type="time" />
            </div>
            <div class="space-y-1.5">
              <Label for="end_time">{{ $t('project.endTime') }}</Label>
              <Input id="end_time" v-model="form.end_time" type="time" />
              <p v-if="form.errors.end_time" class="text-xs text-destructive">
                {{ form.errors.end_time }}
              </p>
            </div>
          </div>

          <div class="space-y-1.5">
            <Label for="venue_name">{{ $t('project.venueName') }}</Label>
            <Input id="venue_name" v-model="form.venue_name" />
          </div>

          <div class="space-y-1.5">
            <Label for="venue_city">{{ $t('project.venueCity') }}</Label>
            <Input id="venue_city" v-model="form.venue_city" />
          </div>

          <div class="sm:col-span-2">
            <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
          </div>
        </form>
      </CardContent>
    </Card>

    <section
      v-for="shoot in shoots"
      :key="shoot.id"
      class="space-y-3 rounded-lg border p-4"
      :class="shoot.isCancelled && 'opacity-60'"
    >
      <div class="flex items-start gap-3">
        <div class="w-28 shrink-0 text-sm tabular-nums">
          {{ longDate(shoot.date) ?? $t('board.openDate') }}
        </div>

        <div class="min-w-0 flex-1">
          <h2 class="text-sm font-medium">
            {{ shoot.label ?? shoot.kind }}
            <span v-if="shoot.isPrimary" class="text-xs font-normal text-muted-foreground">
              · {{ $t('project.primary') }}
            </span>
          </h2>
          <p class="text-xs text-muted-foreground">
            <template v-if="shoot.venue">{{ shoot.venue }} · </template>
            <template v-if="shoot.callTime">{{ shoot.callTime }}–{{ shoot.endTime ?? '' }}</template>
          </p>
        </div>

        <CrewStack :crew="shoot.crew" />
        <span class="shrink-0 rounded border px-1.5 py-0.5 text-xs">{{ shoot.statusLabel }}</span>
      </div>

      <!-- A cancelled shoot keeps the status it died in, and gains no controls
           back: there is no un-cancel, deliberately. -->
      <p v-if="shoot.isCancelled" class="rounded border px-2 py-1 text-xs text-muted-foreground">
        {{ $t('project.cancelled', { reason: shoot.cancelledReason }) }}
      </p>

      <div v-else class="flex flex-wrap items-center gap-2 border-t pt-3">
        <select
          class="h-8 rounded-md border px-2 text-xs"
          :disabled="!shoot.forwardStatuses.length"
          @change="advance(shoot, ($event.target as HTMLSelectElement).value)"
        >
          <option value="">{{ $t('project.advance') }}</option>
          <option v-for="status in shoot.forwardStatuses" :key="status.value" :value="status.value">
            {{ status.label }}
          </option>
        </select>

        <Button v-if="!shoot.isPrimary" size="xs" variant="outline" @click="designatePrimary(shoot)">
          {{ $t('project.makePrimary') }}
        </Button>

        <Button size="xs" variant="ghost" @click="cancel(shoot)">
          {{ $t('project.cancel') }}
        </Button>

        <select
          class="ml-auto h-8 rounded-md border px-2 text-xs"
          @change="assign(shoot, ($event.target as HTMLSelectElement).value, crewRoles[0].value)"
        >
          <option value="">{{ $t('project.assignCrew') }}</option>
          <option v-for="member in staff" :key="member.id" :value="member.id">
            {{ member.name }}
          </option>
        </select>
      </div>

      <ul v-if="shoot.crew.length && !shoot.isCancelled" class="space-y-1 text-xs">
        <li v-for="member in shoot.crew" :key="member.id" class="flex items-center gap-2">
          <span class="font-medium">{{ member.name }}</span>
          <select
            class="h-6 rounded border px-1 text-xs"
            :value="member.role"
            @change="assign(shoot, member.userId, ($event.target as HTMLSelectElement).value)"
          >
            <option v-for="role in crewRoles" :key="role.value" :value="role.value">
              {{ role.label }}
            </option>
          </select>
          <button type="button" class="text-muted-foreground underline" @click="unassign(member)">
            {{ $t('project.unassign') }}
          </button>
        </li>
      </ul>
    </section>

    <p v-if="!shoots.length" class="text-sm text-muted-foreground">{{ $t('project.noShoots') }}</p>
  </main>
</template>
