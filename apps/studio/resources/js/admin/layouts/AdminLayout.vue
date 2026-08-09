<script setup lang="ts">
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

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
    key: 'kalender',
    label: 'Kalender',
    href: '/kalender',
    icon: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
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
        {{ user?.name }}
        <button type="button" class="txtbtn" style="margin-left: auto" @click="router.post('/logout')">
          {{ $t('common.signOut') }}
        </button>
      </div>
    </nav>

    <main class="main">
      <slot />
    </main>
  </div>
</template>
