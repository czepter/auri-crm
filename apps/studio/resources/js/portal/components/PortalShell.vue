<script setup lang="ts">
/*
 * Every portal screen's outermost element, and the reason this surface needs
 * no stylesheet of its own.
 *
 * Three things live here, and they are the whole reason:
 *
 *   The column that holds the footer at the bottom of a short page. Inertia
 *   mounts into a bare #app, so the column has to be the first thing inside
 *   it — min-h-dvh works from there regardless, and whichever child carries
 *   `grow` is the one that takes up the slack (PortalWrap does).
 *
 *   One size step up from the admin: read once a year at arm's length, not
 *   all day at a desk.
 *
 *   Clearance for PortalTabbar, which is fixed below 760px and would
 *   otherwise stand on the footer.
 *
 * data-tokens is what takes everything below out of tokens.css's unlayered
 * h1 and svg element rules — those are the admin's, and they beat any
 * utility whatever its specificity.
 */

/*
 * `fill` is the mailbox: a screen whose panes scroll against each other
 * rather than the window. Pinning the column to exactly one viewport is
 * what gives the children something finite to divide, and it stays off
 * below 980px, where those panes stack back into one document.
 */
defineProps<{ fill?: boolean }>()
</script>

<template>
  <div
    data-tokens
    class="flex min-h-dvh flex-col text-[15px] leading-[1.6] max-[760px]:pb-[calc(56px+env(safe-area-inset-bottom))]"
    :class="fill && 'min-[980px]:h-dvh min-[980px]:overflow-hidden'"
  >
    <slot />
  </div>
</template>
