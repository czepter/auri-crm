<script setup lang="ts">
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'

/*
 * Die Portal-Startseite selbst, im iframe des Einstellungs-Bildschirms —
 * dieselben Bauteile, die der Kunde sieht, nicht ihr Abbild. Was eine
 * Einstellung nicht steuert (der Name des Kunden, seine Buchungen), steht
 * als graue Fläche da, wie in jeder anderen Vorschau dieses Bildschirms
 * auch: gezeigt wird, was der Schalter bewirkt.
 *
 * Der Kopf und der Fuß lesen ihre Marke und ihre Rechtstexte aus den
 * geteilten Props, die PortalSettingsController::preview hier genauso setzt
 * wie HandleInertiaRequests sie auf der Portal-Oberfläche setzt.
 */
defineProps<{ welcomeText: string | null; maintenanceMessage: string | null; closed: boolean }>()
</script>

<template>
  <!-- inert: ein Klick im Rahmen soll die Einstellungen darunter nicht verlassen. -->
  <div data-theme="portal" inert>
    <PortalShell>
      <PortalHeader />

      <PortalWrap class="pb-[var(--band)]">
        <template v-if="closed">
          <p class="pt-[var(--band)] text-[17px] leading-[1.55] text-muted-foreground">{{ maintenanceMessage }}</p>
        </template>

        <template v-else>
          <section class="pt-[var(--band)]">
            <div class="h-[64px] w-[440px] max-w-full rounded-[var(--r)] bg-sunk" />
            <p v-if="welcomeText" class="mt-6 max-w-[52ch] text-[17px] leading-[1.55] text-muted-foreground">
              {{ welcomeText }}
            </p>
          </section>

          <div class="grid items-start gap-[var(--band)] pt-[var(--band)] min-[1080px]:grid-cols-[minmax(0,1fr)_348px] min-[1080px]:gap-x-12">
            <div class="min-[1080px]:col-start-2">
              <PortalLabel class="mb-6">{{ $t('portal.pendingTitle') }}</PortalLabel>
              <div class="flex flex-col gap-3">
                <div v-for="row in 2" :key="row" class="h-[56px] rounded-[var(--r)] bg-sunk" />
              </div>
            </div>

            <div class="min-[1080px]:col-start-1 min-[1080px]:row-start-1">
              <PortalLabel class="mb-6">{{ $t('portal.bookingsTitle') }}</PortalLabel>
              <div class="flex flex-col gap-3">
                <div v-for="row in 3" :key="row" class="h-[88px] rounded-[var(--r)] bg-sunk" />
              </div>
            </div>
          </div>
        </template>
      </PortalWrap>

      <PortalFooter />
    </PortalShell>
  </div>
</template>
