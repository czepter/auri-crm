<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import CrewStack from '@/admin/components/CrewStack.vue'
import { dayAndMonth, weekday } from '@/admin/lib/shoots'

const props = defineProps<{
  shoots: App.Crm.Data.ShootData[]
  projects: Record<string, { id: string; name: string }>
  mine: string[]
  feedUrl: string
}>()

const { t } = useI18n()
const copied = ref(false)

// Grouped by month, because a shooting calendar is read a month at a time and
// the gaps between days are the point — a flat list hides them.
const months = computed(() => {
  const groups = new Map<string, App.Crm.Data.ShootData[]>()

  for (const shoot of props.shoots) {
    if (!shoot.date) continue

    const label = new Date(shoot.date).toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
    groups.set(label, [...(groups.get(label) ?? []), shoot])
  }

  return [...groups].map(([label, shoots]) => ({ label, shoots }))
})

async function copyFeed() {
  await navigator.clipboard.writeText(props.feedUrl)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

function regenerate() {
  if (confirm(t('calendar.revokeConfirm'))) {
    router.post('/kalender/abo', {}, { preserveScroll: true })
  }
}
</script>

<template>
  <AdminLayout active="kalender">
    <div class="hrow" style="margin-bottom: var(--s5)">
      <h1>{{ $t('calendar.title') }}</h1>
    </div>

    <!-- The subscription URL is a credential: anyone holding it can read this
         person's schedule. It is shown once, beside the way to revoke it. -->
    <div class="card static" style="margin-bottom: var(--s5)">
      <div class="cardhead">
        <span class="kick">{{ $t('calendar.subscribe') }}</span>
        <button type="button" class="abtn" @click="regenerate">{{ $t('calendar.revoke') }}</button>
      </div>
      <p class="hint" style="margin-bottom: var(--s3)">{{ $t('calendar.subscribeHint') }}</p>
      <div class="picked">
        <span class="nm" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap">
          {{ feedUrl }}
        </span>
        <button type="button" class="chg" @click="copyFeed">
          {{ copied ? $t('calendar.copied') : $t('calendar.copy') }}
        </button>
      </div>
    </div>

    <section v-for="month in months" :key="month.label" class="dsec">
      <div class="dlab">{{ month.label }}</div>

      <div class="sess">
        <Link
          v-for="shoot in month.shoots"
          :key="shoot.id"
          class="srow"
          :class="{ open: mine.includes(shoot.id) }"
          :href="`/auftraege/${projects[shoot.id].id}/shootings`"
          style="display: block"
        >
          <div class="stop">
            <div class="sdate">
              {{ dayAndMonth(shoot.date) }}
              <em>{{ weekday(shoot.date) }}</em>
            </div>
            <div class="sname">
              {{ shoot.label ?? shoot.kind }}
              <span v-if="mine.includes(shoot.id)" class="prim">{{ $t('calendar.mine') }}</span>
              <span>
                {{
                  [projects[shoot.id].name, shoot.venue, shoot.callTime && `${shoot.callTime}–${shoot.endTime ?? ''}`]
                    .filter(Boolean)
                    .join(' · ')
                }}
              </span>
            </div>
            <CrewStack :crew="shoot.crew" />
            <span class="chip next">{{ shoot.statusLabel }}</span>
          </div>
        </Link>
      </div>
    </section>

    <p v-if="!months.length" class="hint">{{ $t('calendar.empty') }}</p>
  </AdminLayout>
</template>
