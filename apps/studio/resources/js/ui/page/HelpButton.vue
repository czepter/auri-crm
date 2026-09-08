<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useMediaQuery } from '@vueuse/core'
import { helpPanel } from '@/lib/helpPanel'

type Eintrag = { slug: string; titel: string; art: string }

const props = defineProps<{ eintraege: Eintrag[]; mitRail: boolean }>()

// Rail.vue verschwindet unter 1080px. Zwei Rails nebeneinander brauchen mehr
// Raum, sonst bleiben bei 1280px nur 458px Fallakte übrig.
const breitGenug = useMediaQuery(computed(() => props.mitRail
  ? '(min-width: 1536px)'
  : '(min-width: 1080px)'))
</script>

<template>
  <button
    v-if="eintraege.length && breitGenug"
    class="cursor-pointer rounded-[7px] border-0 bg-transparent px-2 py-1 text-[12px] leading-none text-ink-2 hover:bg-sunk"
    type="button"
    @click="helpPanel.oeffnen()"
  >{{ $t('help.open') }}</button>

  <Link
    v-else-if="eintraege.length"
    class="text-[12px] leading-none text-ink-2 no-underline"
    :href="`/hilfe/${eintraege[0].slug}`"
  >{{ $t('help.open') }}</Link>
</template>
