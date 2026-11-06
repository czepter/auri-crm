<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

withDefaults(defineProps<{ href?: string; label?: string }>(), { href: '/', label: undefined })
</script>

<template>
  <Link
    class="-ml-2 inline-flex min-h-11 items-center gap-1.5 px-2 text-[14px] text-muted-foreground no-underline hover:text-foreground"
    :href="href"
  >
    <svg class="size-4 fill-none stroke-current stroke-[1.7] [stroke-linecap:round] [stroke-linejoin:round]" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6" /></svg>
    {{ label ?? $t('portal.navBooking') }}
  </Link>
</template>
