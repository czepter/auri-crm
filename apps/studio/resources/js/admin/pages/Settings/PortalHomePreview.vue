<script setup lang="ts">
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalRow from '@/portal/components/PortalRow.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'

/*
 * Die Portal-Startseite selbst, im Rahmen des Einstellungs-Bildschirms —
 * dieselben Bauteile, die der Kunde sieht, nicht ihr Abbild.
 *
 * Gezeigt wird sie an einer erfundenen Kundin: eine Vorschau braucht jemanden,
 * dem sie gehört, und das Studio soll dafür keinen echten Kontakt suchen
 * müssen. Ihre Buchungen stehen im Zustand, den der Umschalter nennt — ohne
 * sie wäre nicht zu sehen, wovon die drei Begrüßungen sprechen.
 *
 * Kopf und Fuß lesen Marke und Rechtstexte aus den geteilten Props, die
 * PortalSettingsController::preview hier genauso setzt wie
 * HandleInertiaRequests sie auf der Portal-Oberfläche setzt.
 */
const props = defineProps<{
  welcomeText: string | null
  maintenanceMessage: string | null
  closed: boolean
  state: 'inquiry' | 'active' | 'done'
  client: string
}>()

const BOOKINGS = {
  inquiry: [],
  active: [
    { title: 'Hochzeit im Gut Wehlitz', note: '12.06.2026', state: 'open' as const, aside: '2.400,00 €' },
    { title: 'Standesamt Leipzig', note: '08.05.2026', state: 'neutral' as const, aside: null },
  ],
  done: [
    { title: 'Hochzeit im Gut Wehlitz', note: 'Bilder liegen bereit', state: 'done' as const, aside: null },
    { title: 'Paarshooting Auwald', note: 'Bilder liegen bereit', state: 'done' as const, aside: null },
  ],
}

const PENDING = {
  inquiry: [{ title: 'Angebot', note: 'Gültig bis 04.10.2026', aside: '3.900,00 €' }],
  active: [{ title: 'Rate', note: 'Fällig am 01.10.2026', aside: '1.200,00 €' }],
  done: [],
}

const bookings = () => BOOKINGS[props.state]
const pending = () => PENDING[props.state]
</script>

<template>
  <!-- inert: ein Klick im Rahmen soll die Einstellungen darunter nicht verlassen. -->
  <div data-theme="portal" inert>
    <PortalShell>
      <PortalHeader />

      <PortalWrap class="pb-[var(--band)]">
        <p v-if="closed" class="pt-[var(--band)] text-[17px] leading-[1.55] text-muted-foreground">
          {{ maintenanceMessage }}
        </p>

        <template v-else>
          <section class="pt-[var(--band)]">
            <h1 class="font-serif text-[clamp(38px,14.3px+6.08vw,86px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
              {{ client }}
            </h1>
            <p v-if="welcomeText" class="mt-6 max-w-[52ch] text-[17px] leading-[1.55] text-muted-foreground">
              {{ welcomeText }}
            </p>
          </section>

          <div class="grid items-start gap-[var(--band)] pt-[var(--band)] min-[1080px]:grid-cols-[minmax(0,1fr)_348px] min-[1080px]:gap-x-12">
            <div v-if="pending().length" class="min-[1080px]:col-start-2">
              <PortalLabel class="mb-6">{{ $t('portal.pendingTitle') }}</PortalLabel>
              <div class="border-t border-border">
                <PortalRow
                  v-for="row in pending()"
                  :key="row.title"
                  :title="row.title"
                  :note="row.note"
                  :aside="row.aside"
                  aside-open
                  state="open"
                />
              </div>
            </div>

            <div v-if="bookings().length" class="min-[1080px]:col-start-1 min-[1080px]:row-start-1">
              <PortalLabel class="mb-6">{{ $t('portal.bookingsTitle') }}</PortalLabel>
              <div class="border-t border-border">
                <PortalRow
                  v-for="row in bookings()"
                  :key="row.title"
                  :title="row.title"
                  :note="row.note"
                  :aside="row.aside"
                  :state="row.state"
                />
              </div>
            </div>
          </div>
        </template>
      </PortalWrap>

      <PortalFooter />
    </PortalShell>
  </div>
</template>
