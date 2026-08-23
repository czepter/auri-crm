<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

/*
 * mockups/redesign/tokens.css `.tabbar`. Four text links do not fit in 390px
 * and a 22px tall target is not hit on glass, so the phone gets its own
 * navigation rather than a shrunken copy of the header's: bottom, in the
 * thumb's reach, three destinations at 56px.
 *
 * "Abmelden" is not one of them. It lives in the settings, where the rest of
 * the access does.
 */
const nav = computed(() => usePage().props.portalNav as { messaging: boolean; unread: number } | null | undefined)

const path = computed(() => usePage().url.split('?')[0])

function on(active: boolean) {
  return active ? 'font-semibold text-foreground' : 'text-muted-foreground'
}

const LINK
  = 'flex min-h-[56px] flex-col items-center justify-center gap-[5px] px-1 pt-[9px] pb-2.5 text-[11px] leading-none font-medium no-underline [&_svg]:size-5 [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:stroke-[1.6]'
</script>

<template>
  <nav
    v-if="nav"
    class="fixed inset-x-0 bottom-0 z-40 grid auto-cols-fr grid-flow-col border-t border-border bg-[color-mix(in_srgb,var(--bg)_92%,transparent)] pb-[env(safe-area-inset-bottom)] backdrop-blur-[16px] min-[761px]:hidden"
  >
    <Link :class="[LINK, on(path === '/' || path.startsWith('/projekte'))]" href="/">
      <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10" /></svg>
      {{ $t('portal.navBookings') }}
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
