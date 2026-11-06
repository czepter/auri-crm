<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalOffers from '@/portal/components/PortalOffers.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalRow from '@/portal/components/PortalRow.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { useLiveProps } from '@/admin/lib/liveReload'

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
type Booking = { title: string; note: string; state: 'open' | 'done' | 'neutral'; aside: string | null; year: string }

const props = defineProps<{
  welcomeText: string | null
  maintenanceMessage: string | null
  closed: boolean
  fontPair: string
  client: string
  bookings: Booking[]
  pending: { title: string; note: string; aside: string }[]
  shelf: App.Catalog.Data.PortalServiceData[]
  clientRegister: string
}>()
// Wie portal/main.ts: ein Studio mit Sie bekommt die Sie-Texte.
if (props.clientRegister === 'formal') useI18n().mergeLocaleMessage('de', { portal: de.portalFormal })

useLiveProps(['welcomeText', 'maintenanceMessage', 'shelf', 'fontPair', 'portalBranding', 'portalLegal'])

// Nach Jahr, wie die Startseite selbst: eine Liste ohne die Jahreszeile
// zeigte eine Ordnung, die es dort nicht gibt.
const years = computed(() => [...new Set(props.bookings.map((row) => row.year))]
  .map((year) => ({ year, rows: props.bookings.filter((row) => row.year === year) })))
</script>

<template>
  <!-- inert: ein Klick im Rahmen soll die Einstellungen darunter nicht verlassen. -->
  <div data-theme="portal" :data-font="fontPair" inert>
    <PortalShell>
      <PortalHeader />

      <PortalWrap class="pb-[var(--band)]">
        <p v-if="closed" class="pt-[var(--band)] text-title leading-[1.55] text-muted-foreground">
          {{ maintenanceMessage }}
        </p>

        <template v-else>
          <section class="pt-[var(--band)]">
            <h1 class="font-serif text-[clamp(38px,14.3px+6.08vw,86px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
              {{ client }}
            </h1>
            <p v-if="welcomeText" class="mt-6 max-w-[52ch] text-title leading-[1.55] text-muted-foreground">
              {{ welcomeText }}
            </p>
          </section>

          <div class="grid items-start gap-[var(--band)] pt-[var(--band)] min-[1080px]:grid-cols-[minmax(0,1fr)_348px] min-[1080px]:gap-x-12">
            <div v-if="pending.length" class="min-[1080px]:col-start-2">
              <PortalLabel class="mb-6">{{ $t('portal.pendingTitle') }}</PortalLabel>
              <div class="border-t border-border">
                <PortalRow
                  v-for="row in pending"
                  :key="row.title"
                  :title="row.title"
                  :note="row.note"
                  :aside="row.aside"
                  aside-open
                  state="open"
                />
              </div>
            </div>

            <div v-if="bookings.length" class="min-[1080px]:col-start-1 min-[1080px]:row-start-1">
              <PortalLabel>{{ $t('portal.bookingsTitle') }}</PortalLabel>

              <template v-for="group in years" :key="group.year">
                <div class="flex items-baseline gap-4 pt-8 pb-3 text-small leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">
                  <span>{{ group.year }}</span>
                  <i class="h-px flex-1 bg-border" />
                </div>
                <PortalRow
                  v-for="row in group.rows"
                  :key="row.title"
                  :title="row.title"
                  :note="row.note"
                  :aside="row.aside"
                  :state="row.state"
                />
              </template>
            </div>

            <PortalOffers :shelf="shelf" class="min-[1080px]:col-start-1" />
          </div>
        </template>
      </PortalWrap>

      <PortalFooter />
    </PortalShell>
  </div>
</template>
