<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'

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
  showSwitcher: boolean
  project: { id: string; name: string; type: string; shoots: Shoot[]; hasContract: boolean }
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
        <Link v-if="showSwitcher" href="/">{{ $t('portal.allProjects') }}</Link>
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
    <div class="pgreet">{{ $t('portal.greeting', { name: contact.name.split(' ')[0] }) }}</div>

    <!-- Mockup 06 fills this page with a hero photograph, a derived checklist
         and a contract; none of those exist yet — they arrive with Q-*, M-*
         and G-* — so the page shows what the studio can actually stand
         behind and says nothing it cannot. -->
    <section class="dsec">
      <div class="kick">{{ project.type }}</div>
      <h1>{{ project.name }}</h1>
    </section>

    <section class="psec">
      <div class="psech">
        <h2>{{ $t('portal.shootsTitle') }}</h2>
        <span>{{ $t('portal.shootCount', project.shoots.length) }}</span>
      </div>

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

    <section v-if="project.hasContract" class="psec">
      <Link class="blkfoot" :href="`/projekte/${project.id}/vertrag`">
        {{ $t('portal.contractTitle') }} →
      </Link>
    </section>
  </main>

  <footer class="pfoot">
    <span>Atelier Nordlicht · Hamburg</span>
  </footer>
</template>
