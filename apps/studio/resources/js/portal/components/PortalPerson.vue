<script setup lang="ts">
import { initials } from '@/portal/lib/initials'

/*
 * portal.css `.person`, as a component. Three screens end on the same block —
 * the home switcher, a booking and an inquiry — and all three say the same
 * thing: who the studio is, and one way to reach them.
 *
 * The link is a slot rather than a prop, because the three do not agree on
 * what it is: an Inertia <Link>, a plain href carrying a project, or nothing
 * at all when messaging is off. Its type styling is a descendant variant, so
 * a caller passes the link it has and not the classes for it.
 */
defineProps<{ name: string | null }>()
</script>

<template>
  <div
    class="flex items-center gap-3.5 [&_a]:text-[12px] [&_a]:leading-[1.4] [&_a]:font-normal [&_a]:text-muted-foreground"
  >
    <span
      class="grid size-[38px] flex-none place-items-center rounded-full bg-[var(--accent-bg)] text-[12.5px] leading-none font-semibold text-action"
    >
      {{ initials(name) }}
    </span>
    <span>
      <b class="block text-[14px] leading-[1.3] font-semibold">{{ name }}</b>
      <slot />
    </span>
  </div>
</template>
