<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { helpPanel } from '@/lib/helpPanel'

type Eintrag = { slug: string; titel: string; art: string }

const page = usePage()
const eintraege = computed(() => (page.props.help as Eintrag[] | null) ?? [])
const dockt = computed(() => eintraege.value.length > 0 && helpPanel.dockt.value)
const href = computed(() =>
  eintraege.value.length ? `/hilfe/${eintraege.value[0].slug}` : '/hilfe',
)
</script>

<template>
  <button
    v-if="dockt"
    class="flex cursor-pointer items-center gap-[7px] rounded-lg border-0 bg-transparent px-2.5 py-1.5 text-[11.5px] leading-none text-ink-2 transition-colors duration-[120ms] ease-out hover:bg-sunk hover:text-foreground"
    type="button"
    @click="helpPanel.oeffnen()"
  >
    <svg class="size-3.5 flex-none fill-none stroke-ink-2 stroke-[1.6] [stroke-linecap:round]" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="9" />
      <path d="M9.5 9a2.5 2.5 0 1 1 3.7 2.2c-.8.5-1.2 1-1.2 1.8" />
      <path d="M12 17.3h.01" />
    </svg>
    {{ $t('help.open') }}
  </button>

  <Link
    v-else
    class="flex cursor-pointer items-center gap-[7px] rounded-lg px-2.5 py-1.5 text-[11.5px] leading-none text-ink-2 no-underline transition-colors duration-[120ms] ease-out hover:bg-sunk hover:text-foreground"
    :href="href"
  >
    <svg class="size-3.5 flex-none fill-none stroke-ink-2 stroke-[1.6] [stroke-linecap:round]" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="9" />
      <path d="M9.5 9a2.5 2.5 0 1 1 3.7 2.2c-.8.5-1.2 1-1.2 1.8" />
      <path d="M12 17.3h.01" />
    </svg>
    {{ $t('help.open') }}
  </Link>
</template>
