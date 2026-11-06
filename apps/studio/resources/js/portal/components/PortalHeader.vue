<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import PortalTabbar from '@/portal/components/PortalTabbar.vue'
import { Button } from '@/ui/button'

/*
 * mockups/redesign/tokens.css `.bar`. One definition, every screen — before
 * this each page carried its own, and a client moving between two of them
 * had to find the navigation again.
 *
 * Over a title image it is the same bar with `over`: transparent and light
 * instead of paper, not a second component.
 *
 * Branding and the nav's own two facts (is there a mailbox, is anything
 * waiting) are shared portal-wide by HandleInertiaRequests rather than
 * passed down per page, because every screen wears the same chrome.
 * `switcher` stays a prop: only the screens that stand inside one booking
 * have a name to put there.
 */
withDefaults(defineProps<{
  /** Laid over a title image: transparent ground, white type. */
  over?: boolean
  /** The booking this screen stands in. Absent from the bar unless there are others to go back to. */
  switcher?: { label: string; toAll: boolean } | null
  /**
   * A sub-screen carries its own head below 980px — see PortalSubhead. The
   * bar steps aside there; the tabbar below it does not, which is why this
   * is a prop rather than the caller dropping the component.
   */
  subhead?: boolean
}>(), {
  over: false,
  switcher: null,
  subhead: false,
})

type Branding = { displayName: string | null; logoUrl: string | null; accentColor: string | null }
type Nav = { messaging: boolean; unread: number; oneBooking: boolean }

const branding = computed(() => usePage().props.portalBranding as Branding | undefined)
const nav = computed(() => usePage().props.portalNav as Nav | null | undefined)

// P-09 (#228): impersonation nobody can forget they are in. Every write is
// refused anyway (EnsureAudience), but a studio owner should not have to
// discover that by being refused.
const preview = computed(() => usePage().props.portalPreview === true)

const path = computed(() => usePage().url.split('?')[0])
const bookings = computed(() => usePage().component !== 'Error' && (path.value === '/' || path.value.startsWith('/projekte')))

// Every portal screen mounts this component once, so applying the accent
// colour here — rather than in each page — is enough to cover the whole
// surface.
function applyAccent() {
  const accent = branding.value?.accentColor

  if (accent) {
    document.documentElement.style.setProperty('--accent', accent)
  }
}

onMounted(applyAccent)
watch(branding, applyAccent)
</script>

<template>
  <div :class="[over ? 'absolute inset-x-0 top-0 z-30' : 'sticky top-0 z-30', subhead && 'max-[979px]:hidden']">
    <div
      v-if="preview"
      class="flex items-center justify-center gap-5 border-b border-border bg-due-bg px-6 py-2.5 text-[12.5px] leading-none font-medium text-due"
    >
      <span>{{ $t('portal.previewBanner') }}</span>
      <a class="text-due underline" href="/vorschau-beenden">{{ $t('portal.previewLeave') }}</a>
    </div>

    <div
      :class="
        over
          ? 'text-white'
          : 'border-b border-border bg-[var(--bg)]'
      "
    >
      <div class="mx-auto flex max-w-[var(--page)] items-center gap-6 px-6 py-4">
        <Link
          class="flex flex-none items-center gap-2.25 text-[15px] leading-none font-semibold tracking-[-0.012em] text-inherit no-underline"
          href="/"
        >
          <img v-if="branding?.logoUrl" :src="branding.logoUrl" alt="" class="h-[18px] w-auto" />
          <i v-else class="size-[7px] flex-none rounded-full bg-action" />
          <span>{{ branding?.displayName }}</span>
        </Link>

        <!-- The way back to the other bookings, named after the one you
             are in. A client who has only this one is shown nothing: a badge
             that leads nowhere reads as a label they cannot place.
             ponytail: a link back to all bookings, not the mockup's dropdown
             panel; that needs every booking shared on every request. Build it
             when a client with a dozen bookings complains about the detour. -->
        <Link
          v-if="switcher?.toAll"
          href="/"
          :class="[
            'inline-flex flex-none items-center gap-2.25 rounded-[var(--r-pill)] border px-4 py-2 text-[13.5px] leading-none font-medium whitespace-nowrap no-underline',
            over
              ? 'border-white/28 bg-[rgba(12,10,9,.28)] text-white backdrop-blur-[10px] hover:bg-[rgba(12,10,9,.5)]'
              : 'border-line-hover text-foreground hover:bg-sunk',
          ]"
        >
          {{ switcher.label }}
        </Link>

        <nav
          v-if="nav"
          class="ml-auto flex items-center gap-6 text-[13.5px] leading-none max-[760px]:hidden [&_a]:no-underline"
        >
          <Link :class="bookings ? 'font-semibold' : 'opacity-72 hover:opacity-100'" href="/">
            {{ nav.oneBooking ? $t('portal.navBooking') : $t('portal.navBookings') }}
          </Link>
          <Link
            v-if="nav.messaging"
            class="inline-flex items-center gap-2"
            :class="path.startsWith('/nachrichten') ? 'font-semibold' : 'opacity-72 hover:opacity-100'"
            href="/nachrichten"
          >
            {{ $t('portal.messagesTitle') }}
            <span
              v-if="nav.unread"
              class="min-w-[18px] rounded-[var(--r-pill)] bg-action px-[5px] text-center text-[11px] leading-[18px] font-semibold text-white"
            >{{ nav.unread }}</span>
          </Link>
          <Link :class="path.startsWith('/einstellungen') ? 'font-semibold' : 'opacity-72 hover:opacity-100'" href="/einstellungen">
            {{ $t('portal.accountTitle') }}
          </Link>
          <Button type="button" variant="portal-nav" @click="router.post('/abmelden')">
            {{ $t('portal.signOut') }}
          </Button>
        </nav>
      </div>
    </div>
  </div>

  <PortalTabbar />
</template>
