<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'

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
  project: {
    id: string
    name: string
    type: string
    shoots: Shoot[]
    hasContract: boolean
    hasPaymentPlan: boolean
    messagingEnabled: boolean
  }
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
  <PortalHeader :contact="contact" :show-switcher="showSwitcher" />

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

    <!-- E-06, gated by T-08's own "messaging" switch (default off) — the
         affordance half of ThreadController's own
         `portal.capability:messaging`, the same shape hasContract/
         hasPaymentPlan already take for their own links below. -->
    <section v-if="project.messagingEnabled" class="psec">
      <Link class="blkfoot" :href="`/projekte/${project.id}/nachrichten`">
        {{ $t('portal.messagesLinkTitle') }} →
      </Link>
    </section>

    <section v-if="project.hasContract" class="psec">
      <Link class="blkfoot" :href="`/projekte/${project.id}/vertrag`">
        {{ $t('portal.contractTitle') }} →
      </Link>
    </section>

    <section v-if="project.hasPaymentPlan" class="psec">
      <Link class="blkfoot" :href="`/projekte/${project.id}/zahlungen`">
        {{ $t('portal.paymentsLinkTitle') }} →
      </Link>
    </section>
  </main>

  <PortalFooter />
</template>
