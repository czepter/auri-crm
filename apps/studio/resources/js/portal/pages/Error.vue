<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { Button } from '@/ui/button'

/*
 * Rendered by bootstrap/app.php for the two closed doors on this surface.
 *
 * 403 and 404 say the same thing to a client and deliberately so: on the
 * portal, a booking that is not theirs and one that does not exist are the
 * same answer, because the difference is not this surface's to give away.
 */
defineProps<{ status: number }>()

const oneBooking = computed(() => (usePage().props.portalNav as { oneBooking: boolean } | null | undefined)?.oneBooking)
</script>

<template>
  <PortalShell>
    <PortalHeader />

    <PortalWrap class="flex items-center py-24">
      <div class="animate-rise motion-reduce:animate-none w-full max-w-[620px]">
        <p class="mb-4 font-serif text-[26px] leading-none text-muted-foreground tabular-nums">{{ status }}</p>
        <h1 class="font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.notFoundTitle') }}
        </h1>

        <Button class="mt-10" as="a" variant="portal" href="/">
          {{ $t(oneBooking ? 'portal.navBooking' : 'portal.navBookings') }}
          <svg class="fill-none stroke-current" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </Button>
      </div>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
