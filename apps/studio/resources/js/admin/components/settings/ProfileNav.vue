<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{ active: string }>()

const NAV = [
  { key: 'konto', href: '/profil', label: 'profile.navAccount' },
  { key: 'sicherheit', href: '/profil/sicherheit', label: 'profile.navSecurity' },
  { key: 'benachrichtigungen', href: '/profil/benachrichtigungen', label: 'profile.navNotifications' },
  { key: 'kalender', href: '/profil/kalender', label: 'calendar.subscribe' },
  { key: 'abwesenheiten', href: '/abwesenheiten', label: 'profile.navAbsences' },
]
</script>

<template>
  <nav class="flex w-[196px] flex-none flex-col gap-0.5">
    <Link
      v-for="item in NAV"
      :key="item.key"
      class="flex items-center gap-2.5 rounded-[7px] px-2.5 py-[9px] text-ui leading-none no-underline transition-[background-color] duration-[.12s] ease-out hover:bg-muted [&_svg]:size-[17px]"
      :class="item.key === active ? 'font-semibold text-foreground [&_svg]:opacity-100' : 'font-normal text-muted-foreground [&_svg]:opacity-75'"
      :href="item.href"
    >{{ $t(item.label) }}</Link>
  </nav>
</template>
