<script setup lang="ts">
import { router } from '@inertiajs/vue3'

type Shoot = {
  id: string
  title: string
  date: string | null
  callTime: string | null
  venue: string | null
  status: string
}

defineProps<{
  contact: { name: string }
  projects: { id: string; name: string; type: string; shoots: Shoot[] }[]
}>()

function longDate(date: string | null) {
  return date
    ? new Date(date).toLocaleDateString('de-DE', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      })
    : null
}
</script>

<template>
  <header class="phead">
    <div class="pbar">
      <span class="pmark"><i />Atelier Nordlicht <em>Fotografie · Hamburg</em></span>
      <div class="pme">
        <span>{{ $t('portal.signedInAs') }} <b>{{ contact.name }}</b></span>
        <button type="button" @click="router.post('/abmelden')">
          <svg viewBox="0 0 24 24">
            <path d="M15 17l5-5-5-5" />
            <path d="M20 12H9" />
            <path d="M12 20H6a2 2 0 01-2-2V6a2 2 0 012-2h6" />
          </svg>
          {{ $t('portal.signOut') }}
        </button>
      </div>
    </div>
  </header>

  <main class="pwrap">
    <h1>{{ $t('portal.greeting', { name: contact.name.split(' ')[0] }) }}</h1>

    <!-- Mockup 06 fills this page with a questionnaire, a contract and a
         gallery. None of those exist yet — they arrive with Q-*, M-* and G-*
         — so the page shows what the studio can actually stand behind and
         says nothing it cannot. -->
    <section v-for="project in projects" :key="project.id" class="dsec">
      <div class="kick">{{ project.type }}</div>
      <h2 style="font: 600 19px/1.3 var(--font); letter-spacing: -.02em; margin: 0 0 var(--s4)">
        {{ project.name }}
      </h2>

      <div class="sess">
        <div v-for="shoot in project.shoots" :key="shoot.id" class="srow">
          <div class="stop">
            <div class="sname">
              {{ shoot.title }}
              <span>
                {{
                  [longDate(shoot.date) ?? $t('portal.dateOpen'), shoot.callTime, shoot.venue]
                    .filter(Boolean)
                    .join(' · ')
                }}
              </span>
            </div>
            <!-- Stated, never coloured as a warning: the studio chases, and
                 the client is not chased in their own portal. -->
            <span class="chip next">{{ shoot.status }}</span>
          </div>
        </div>

        <p v-if="!project.shoots.length" class="hint">{{ $t('portal.noShoots') }}</p>
      </div>
    </section>

    <p v-if="!projects.length" class="hint">{{ $t('portal.nothingShared') }}</p>
  </main>

  <footer class="pfoot">
    <span>Atelier Nordlicht · Hamburg</span>
  </footer>
</template>
