<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useMediaQuery } from '@vueuse/core'
import { helpPanel } from '@/lib/helpPanel'
import HelpRail from './HelpRail.vue'
import HelpButton from './HelpButton.vue'

type Eintrag = { slug: string; titel: string; art: string }

/*
 * A case is capped at --container-case, or the rows run the width of the
 * screen and stop being readable. The board is the one screen that is not
 * read but scanned across, and its three columns want every pixel: `wide`.
 */
defineProps<{ wide?: boolean }>()

const slots = useSlots()
const page = usePage()

const eintraege = computed(() => (page.props.help as Eintrag[] | null) ?? [])

// Two width refs, not one computed keyed on $slots.right: Vue never fires a
// reactive trigger for slot changes (only a forced re-render), so a computed
// built on slots.right would compute once and cache that value forever.
// mitRail is read straight off `slots` at each use below, which stays fresh
// across that forced re-render even though it is never a tracked dependency.
const breitOhneRail = useMediaQuery('(min-width: 1080px)')
const breitMitRail = useMediaQuery('(min-width: 1536px)')

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
            class="relative flex min-w-0 flex-1 items-stretch max-[640px]:basis-full"
            :class="wide ? undefined : 'max-w-case'"
        >
            <HelpButton
                v-if="!helpPanel.offen.value"
                class="absolute top-4 right-5 z-10"
                :eintraege="eintraege"
                :mit-rail="Boolean(slots.right)"
            />
            <slot />
        </div>

        <slot name="right" />

        <!-- Die Hilfespalte. HelpRail zeigt sich selbst nur, wenn Einträge
             und Panel-Zustand es hergeben. -->
        <slot name="help"><HelpRail v-if="slots.right ? breitMitRail : breitOhneRail" /></slot>
    </div>
</template>
