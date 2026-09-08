<script setup lang="ts">
import { useSlots } from 'vue'
import { useMediaQuery } from '@vueuse/core'
import { helpPanel } from '@/lib/helpPanel'
import HelpRail from './HelpRail.vue'

/*
 * A case is capped at --container-case, or the rows run the width of the
 * screen and stop being readable. The board is the one screen that is not
 * read but scanned across, and its three columns want every pixel: `wide`.
 */
defineProps<{ wide?: boolean }>()

const slots = useSlots()

const breitOhneRail = useMediaQuery('(min-width: 1080px)')
const breitMitRail = useMediaQuery('(min-width: 1536px)')

// AdminLayout's sidebar button needs this same decision but sits above this
// component and never sees #right — publish it to helpPanel's module state
// on every render.
//
// Do NOT wrap `slots.right` in a `computed()` here, even though it looks
// like the obvious tidy-up. `updateSlots` in @vue/runtime-core only calls
// `trigger(instance, 'set', '$slots')` inside its `isHmrUpdating` branch, so
// outside of dev/HMR a slot appearing or disappearing never fires a
// reactive trigger — a computed reading `slots.right` would compute once
// and freeze on that value forever in production. Vue still force-reruns
// this render via the DYNAMIC_SLOTS patch flag whenever a parent's
// v-if-wrapped named slot changes (Posteingang.vue's #right is exactly
// this case), which is why reading `slots.right` directly here, on every
// call, stays correct — a computed would not. No test in this repo catches
// a regression back to `computed`: `environment: 'node'` (vitest.config.ts)
// makes Vite compile every SFC SSR-only (ssrRender, no client render), so
// nothing here can mount a real client instance and force a second render
// of it to tell the two forms apart — renderToString-based tests build a
// fresh instance per call and cannot distinguish "read fresh" from "cached
// on first read" either way. This comment is the only guard; keep it.
function hilfeDockt() {
  const dockt = slots.right ? breitMitRail.value : breitOhneRail.value
  helpPanel.dockt.value = dockt
  return dockt
}

// ponytail: the 1080px no-rail threshold was checked live against the
// Anfragen board (5 columns, `wide`) — at 1280px with the panel open, the
// last column's header wraps to two lines and its card text truncates
// against the rail edge. Legible from ~1440px on. Raise the no-rail
// threshold for `wide` screens specifically if that cramped range turns out
// to matter in practice.
</script>

<template>
    <!-- Queue, case and trace stand side by side and each fills the height —
         as in mockups/redesign/crm-anfrage.html, where .queue, .detail and
         .rail are the flex children themselves. The case sits in a wrapper of
         its own because it is the one column with a measure; queue and rail
         carry their own width and stay flex children. -->
    <!-- Wraps below 640px so a full-width navigation column in #left takes its
         own line instead of squeezing the case into what is left. -->
    <div class="flex min-h-full items-stretch max-[640px]:flex-wrap">
        <slot name="left" />

        <div
            class="flex min-w-0 flex-1 items-stretch max-[640px]:basis-full"
            :class="wide ? undefined : 'max-w-case'"
        >
            <slot />
        </div>

        <slot name="right" />

        <!-- Die Hilfespalte. HelpRail zeigt sich selbst nur, wenn Einträge
             und Panel-Zustand es hergeben. -->
        <slot name="help"><HelpRail v-if="hilfeDockt()" /></slot>
    </div>
</template>
