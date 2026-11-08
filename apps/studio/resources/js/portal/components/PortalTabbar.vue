<script lang="ts">
// The bar remounts with every page, so the last position outlives it and the
// indicator can slide from there instead of appearing in place.
let lastIndex: number | null = null
</script>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { bookingKey } from '@/portal/lib/booking'

/*
 * mockups/redesign/tokens.css `.tabbar`. Four text links do not fit in 390px
 * and a 22px tall target is not hit on glass, so the phone gets its own
 * navigation rather than a shrunken copy of the header's: bottom, in the
 * thumb's reach, three destinations at 56px.
 *
 * "Abmelden" is not one of them. It lives in the settings, where the rest of
 * the access does.
 */
const nav = computed(() => usePage().props.portalNav as { messaging: boolean; unread: number; oneBooking: boolean } | null | undefined)

const path = computed(() => usePage().url.split('?')[0])

const bookings = computed(() => usePage().component !== 'Error' && (path.value === '/' || path.value.startsWith('/projekte')))

const keys = computed(() => (nav.value?.messaging ? ['b', 'm', 's'] : ['b', 's']))
const active = computed(() => {
  const current = bookings.value ? 'b' : path.value.startsWith('/nachrichten') ? 'm' : path.value.startsWith('/einstellungen') ? 's' : null

  return current === null ? -1 : keys.value.indexOf(current)
})
const shown = ref(lastIndex ?? active.value)

onMounted(() => {
  requestAnimationFrame(() => (shown.value = active.value))
})
watch(active, (index) => (shown.value = index))
watch(shown, (index) => (lastIndex = index), { immediate: true })

function on(active: boolean) {
  return active ? 'font-semibold text-foreground' : 'text-muted-foreground'
}

const LINK
  = 'flex min-h-[56px] flex-col items-center justify-center gap-[5px] px-1 pt-[9px] pb-2.5 text-[11px] leading-none font-medium no-underline [&_svg]:size-5 [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:stroke-[1.6]'
</script>

<template>
  <nav
    v-if="nav"
    class="fixed inset-x-0 bottom-0 z-40 grid auto-cols-fr grid-flow-col border-t border-border bg-[var(--bg)] pb-[env(safe-area-inset-bottom)] min-[761px]:hidden"
  >
    <span
      v-if="shown >= 0"
      aria-hidden="true"
      class="pointer-events-none absolute -top-px left-0 flex h-0.5 transition-transform duration-300 ease-[cubic-bezier(.22,.61,.36,1)] motion-reduce:transition-none"
      :style="{ width: `${100 / keys.length}%`, transform: `translateX(${shown * 100}%)` }"
    >
      <i class="mx-auto w-8 bg-foreground" />
    </span>

    <Link :class="[LINK, on(bookings)]" href="/">
      <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10" /></svg>
      {{ $t(bookingKey(nav.oneBooking ? 'navBooking' : 'navBookings')) }}
    </Link>

    <Link v-if="nav.messaging" :class="[LINK, on(path.startsWith('/nachrichten'))]" href="/nachrichten">
      <span class="relative">
        <svg viewBox="0 0 24 24"><path d="M4 6h16v12H4zM4 7l8 6 8-6" /></svg>
        <i v-if="nav.unread" class="absolute -top-px -right-[5px] size-[7px] rounded-full bg-action" />
      </span>
      {{ $t('portal.messagesTitle') }}
    </Link>

    <Link :class="[LINK, on(path.startsWith('/einstellungen'))]" href="/einstellungen">
      <svg viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="3" />
        <path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" />
      </svg>
      {{ $t('portal.accountTitle') }}
    </Link>
  </nav>
</template>
