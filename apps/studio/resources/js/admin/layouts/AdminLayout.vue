<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import CommandPalette from '@/admin/components/CommandPalette.vue'

const props = defineProps<{ active: string }>()

const palette = ref<InstanceType<typeof CommandPalette> | null>(null)

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

// C-02 (#236): two groups, and the order follows a job's life — inquiry,
// project, contact. Eleven equal-ranked entries were a list of everything the
// product has, in the order the screens happened to be built.
//
// Only entries with a screen behind them appear, which is the same ruling C-06
// applies to its tabs and S-01 to the Übersicht: an item that goes nowhere
// teaches staff to distrust the nav. Galerien is in the draft's own sidebar
// and absent here for exactly that reason — the admin gallery record is #254.
//
// From S-06 the same rule covers authority: a nav item this role would be
// refused is absent rather than rendered into a 403.
//
// Icons: 17px, stroke 1.6, round ends, and the stroke follows the text —
// grey, black in the active row. Anfragen is a board of three columns,
// Posteingang an envelope, Dokumentvorlagen a sheet with a folded corner,
// Auswertungen bars. Kontakte got the address book, or it would be the same
// person symbol as Team.
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
    icon: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 8v8M13 8v5M18 8v10"/>',
  },
  {
    key: 'projekte',
    label: 'Projekte',
    href: '/projekte',
    count: nav.value?.projects,
    icon: '<path d="M4 8h16v12H4z"/><path d="M9 8V5h6v3"/><path d="M4 13h16"/>',
  },
  {
    key: 'kontakte',
    label: 'Kontakte',
    href: '/kontakte',
    needs: 'manage_contacts',
    icon: '<path d="M6 3h12v18H6z"/><circle cx="12" cy="10" r="2.4"/><path d="M8.7 17c.6-1.7 1.8-2.6 3.3-2.6s2.7.9 3.3 2.6"/><path d="M6 7H3.5M6 12H3.5M6 17H3.5"/>',
  },
  {
    key: 'kalender',
    label: 'Kalender',
    href: '/kalender',
    icon: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
  },
  {
    // T-10. Not in the draft's sidebar, because C-13 (#249) folds absences
    // into the calendar's month grid as dimmed entries. Until it does, this
    // is the only way to the screen.
    key: 'abwesenheiten',
    label: 'Abwesenheiten',
    href: '/abwesenheiten',
    icon: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M8 14h3M13 14h3M8 17h3"/>',
  },
  {
    // E-12: the tray for mail that arrived and matched nothing. Same
    // capability as replying to a client, because triaging one is deciding
    // which conversation it belongs to.
    key: 'posteingang',
    label: 'Posteingang',
    href: '/posteingang',
    needs: 'manage_messages',
    icon: '<path d="M3 6.5h18v11H3z"/><path d="m3 7.5 9 6 9-6"/>',
  },
  { group: 'Studio' },
  {
    key: 'pakete',
    label: 'Pakete',
    href: '/pakete',
    needs: 'manage_packages',
    studio: true,
    icon: '<path d="M21 8.5v7l-9 4.5-9-4.5v-7L12 4z"/><path d="M3 8.5 12 13l9-4.5M12 13v7"/>',
  },
  {
    key: 'dokumentvorlagen',
    label: 'Dokumentvorlagen',
    href: '/dokumentvorlagen',
    needs: 'manage_contracts',
    studio: true,
    icon: '<path d="M6 3h7l5 5v13H6z"/><path d="M13 3v5h5"/><path d="M9 13h6M9 17h4"/>',
  },
  {
    key: 'team',
    label: 'Team',
    href: '/team',
    needs: 'manage_staff',
    studio: true,
    icon: '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16.5 5.5a3.2 3.2 0 010 5.6M17.5 14.8c2 .7 3.5 2.6 3.5 5.2"/>',
  },
  {
    // S-13: whole-book figures, so the same capability that lets a role see
    // every project is the one that lets them see the totals over all of them.
    key: 'auswertungen',
    label: 'Auswertungen',
    href: '/auswertungen',
    needs: 'view_all_projects',
    studio: true,
    icon: '<path d="M3.5 20h17"/><path d="M7 20v-6M12 20V6M17 20v-9"/>',
  },
  {
    key: 'einstellungen',
    label: 'Einstellungen',
    href: '/einstellungen',
    needs: 'manage_settings',
    studio: true,
    icon: '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="18" r="2"/>',
  },
].filter((item) => !item.needs || can.value.includes(item.needs as App.Enums.Capability)))
</script>

<template>
  <div class="app">
    <!-- The theme root sits on the sidebar alone while the screens come over
         one at a time (C-01, #235): crm.css and the older tokens.css share
         forty class names, so a root on .app would restyle every unported
         screen at once. -->
    <div data-theme="crm" style="flex: none">
      <nav class="side">
        <Link class="wm" href="/">Nordlicht</Link>

        <!-- Above the navigation, not in it: it does not change page, it
             opens a tool. The shortcut stands where a count would. -->
        <button class="find" type="button" @click="palette?.open()">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4.5 4.5" /></svg>
          {{ $t('palette.search') }}
          <kbd>⌘K</kbd>
        </button>

        <template v-for="item in items" :key="item.key ?? item.group">
          <template v-if="item.group">
            <div class="group" />
            <div class="lab">{{ item.group }}</div>
          </template>

          <!-- The label rides in its own span so the narrow breakpoint can
               drop it: ten truncated words are harder to hit than ten icons.
               `studio` marks the second group, which the phone layout leaves
               out — nobody sets up a studio from a phone. -->
          <Link
            v-else
            :class="{ on: item.key === props.active, studio: item.studio }"
            :href="item.href!"
          >
            <svg viewBox="0 0 24 24" v-html="item.icon" />
            <span>{{ item.label }}</span>
            <span v-if="item.count !== undefined" class="ct">{{ item.count }}</span>
          </Link>
        </template>

        <div class="me">
          <span class="av">{{ initials }}</span>
          <span class="nm">{{ user?.name }}</span>
          <!-- The draft's row is a face and a name, and 214px does not hold a
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

      <CommandPalette ref="palette" />
    </div>

    <main class="main">
      <slot />
    </main>
  </div>
</template>
