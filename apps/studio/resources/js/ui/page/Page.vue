<script setup lang="ts">
import HelpRail from './HelpRail.vue'

/*
 * A case is capped at --container-case, or the rows run the width of the
 * screen and stop being readable. The board is the one screen that is not
 * read but scanned across, and its three columns want every pixel: `wide`.
 */
defineProps<{ wide?: boolean }>()
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
        <slot name="help"><HelpRail /></slot>
    </div>
</template>
