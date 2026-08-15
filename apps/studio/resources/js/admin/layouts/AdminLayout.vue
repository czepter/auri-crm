<script setup lang="ts">
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/ui/button'

const props = defineProps<{ active: string }>()

const page = usePage()
const user = computed(() => page.props.auth.user)
const nav = computed(() => page.props.nav)

// Initials, since users carries no avatar column — the same fallback the crew
// stack uses, for the same reason.
const initials = computed(() => {
  const words = (user.value?.name ?? '').trim().split(/\s+/).filter(Boolean)
  const picked = words.length > 1 ? [words[0], words[words.length - 1]] : words

  return picked.map((word) => word[0]).join('').toUpperCase()
})

const can = computed(() => page.props.auth.can ?? [])

// Mockup 01's sidebar lists twelve destinations. Only the ones with a screen
// behind them appear, which is the ruling C-06 applies to its tabs and S-01 to
// the Übersicht — an item that goes nowhere teaches staff to distrust the nav.
// Each remaining entry arrives with its own story.
//
// From S-06 the same rule covers authority: a nav item this role would be
// refused is absent rather than rendered into a 403.
const items = computed(() => [
  {
    key: 'uebersicht',
    label: 'Übersicht',
    href: '/',
    icon: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
  },
  {
    key: 'anfragen',
    label: 'Anfragen',
    href: '/anfragen',
    count: nav.value?.leads,
    needs: 'manage_leads',
    icon: '<path d="M3 12h5l2 3h4l2-3h5"/><path d="M5.5 5h13l2.5 7v7H3v-7z"/>',
  },
  {
    key: 'kontakte',
    label: 'Kontakte',
    href: '/kontakte',
    needs: 'manage_contacts',
    icon: '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16.5 5.5a3.2 3.2 0 010 5.6M17.5 14.8c2 .7 3.5 2.6 3.5 5.2"/>',
  },
  {
    key: 'pakete',
    label: 'Pakete',
    href: '/pakete',
    needs: 'manage_packages',
    icon: '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
  },
  {
    key: 'dokumentvorlagen',
    label: 'Dokumentvorlagen',
    href: '/dokumentvorlagen',
    needs: 'manage_contracts',
    icon: '<path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4"/><path d="M9 13h6M9 17h6"/>',
  },
  {
    key: 'team',
    label: 'Team',
    href: '/team',
    needs: 'manage_staff',
    icon: '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M19 8v6M22 11h-6"/>',
  },
  {
    // S-13: whole-book figures, so the same capability that lets a role see
    // every project is the one that lets them see the totals over all of them.
    key: 'auswertungen',
    label: 'Auswertungen',
    href: '/auswertungen',
    needs: 'view_all_projects',
    icon: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
  },
  {
    key: 'kalender',
    label: 'Kalender',
    href: '/kalender',
    icon: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
  },
  {
    // T-10: open to every role, same as Kalender above — AbsenceController
    // itself decides "own row, or ManageCrew" per request rather than the
    // nav hiding the destination from anyone.
    key: 'abwesenheiten',
    label: 'Abwesenheiten',
    href: '/abwesenheiten',
    icon: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M8 14h3M13 14h3M8 17h3"/>',
  },
  {
    key: 'einstellungen',
    label: 'Einstellungen',
    href: '/einstellungen',
    needs: 'manage_settings',
    icon: '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="18" r="2"/>',
  },
].filter((item) => !item.needs || can.value.includes(item.needs as App.Enums.Capability)))
</script>

<template>
  <div class="app">
    <nav class="side">
      <Link class="brand" href="/">Nordlicht</Link>

      <Link
        v-for="item in items"
        :key="item.key"
        class="nav"
        :class="{ on: item.key === props.active }"
        :href="item.href"
      >
        <svg viewBox="0 0 24 24" v-html="item.icon" />
        {{ item.label }}
        <span v-if="item.count !== undefined" class="ct">{{ item.count }}</span>
      </Link>

      <div class="me">
        <span class="av">{{ initials }}</span>
        <span class="nm">{{ user?.name }}</span>
        <!-- The mockup's row is a face and a name, and 184px does not hold a
             third text label without truncating the name to "Dev Lo…". The
             action survives as its glyph, named for screen readers. -->
        <Button
          type="button"
          variant="ghost"
          style="margin-left: auto"
          :aria-label="$t('common.signOut')"
          :title="$t('common.signOut')"
          @click="router.post('/logout')"
        >
          <svg viewBox="0 0 24 24">
            <path d="M15 4h4a1 1 0 011 1v14a1 1 0 01-1 1h-4" />
            <path d="M10 16l-4-4 4-4M6 12h9" />
          </svg>
        </Button>
      </div>
    </nav>

    <main class="main">
      <slot />
    </main>
  </div>
</template>
