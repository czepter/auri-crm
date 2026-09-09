<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { helpPanel } from '@/lib/helpPanel'

type Eintrag = { slug: string; titel: string; art: string }

const page = usePage()
const eintraege = computed(() => (page.props.help as Eintrag[] | null) ?? [])
</script>

<template>
  <button
    v-if="eintraege.length && helpPanel.dockt.value"
    class="mb-4 flex w-full cursor-pointer items-center gap-2.25 rounded-lg border-0 bg-sunk px-2.5 py-1.5 text-left text-[13px] leading-[1.47] tracking-[-0.01em] text-ink-2 transition-colors duration-[120ms] ease-out hover:bg-sunk-2 max-[1080px]:justify-center max-[1080px]:px-0 max-[1080px]:py-2 max-[640px]:hidden"
    type="button"
    @click="helpPanel.oeffnen()"
  >
    <svg class="size-4.25 flex-none fill-none stroke-ink-2 stroke-[1.6] [stroke-linecap:round]" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="9" />
      <path d="M9.5 9a2.5 2.5 0 1 1 3.7 2.2c-.8.5-1.2 1-1.2 1.8" />
      <path d="M12 17.3h.01" />
    </svg>
    {{ $t('help.open') }}
  </button>

  <Link
    v-else-if="eintraege.length"
    class="mb-4 flex w-full cursor-pointer items-center gap-2.25 rounded-lg border-0 bg-sunk px-2.5 py-1.5 text-left text-[13px] leading-[1.47] tracking-[-0.01em] text-ink-2 no-underline transition-colors duration-[120ms] ease-out hover:bg-sunk-2 max-[1080px]:justify-center max-[1080px]:px-0 max-[1080px]:py-2 max-[640px]:hidden"
    :href="`/hilfe/${eintraege[0].slug}`"
  >
    <svg class="size-4.25 flex-none fill-none stroke-ink-2 stroke-[1.6] [stroke-linecap:round]" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="9" />
      <path d="M9.5 9a2.5 2.5 0 1 1 3.7 2.2c-.8.5-1.2 1-1.2 1.8" />
      <path d="M12 17.3h.01" />
    </svg>
    {{ $t('help.open') }}
  </Link>
</template>
