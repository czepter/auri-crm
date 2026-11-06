<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { helpPanel } from '@/lib/helpPanel'

const page = usePage()
const vorhanden = computed(() => ((page.props.help as unknown[] | null) ?? []).length > 0)
</script>

<template>
  <button
    v-if="vorhanden"
    class="flex cursor-pointer items-center gap-[7px] rounded-lg border-0 bg-transparent px-2.5 py-1.5 text-small leading-none text-ink-2 transition-colors duration-[120ms] ease-out hover:bg-sunk hover:text-foreground"
    type="button"
    :aria-expanded="helpPanel.offen.value"
    @click="helpPanel.umschalten()"
  >
    <svg class="size-3.5 flex-none fill-none stroke-ink-2 stroke-[1.6] [stroke-linecap:round]" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="9" />
      <path d="M9.5 9a2.5 2.5 0 1 1 3.7 2.2c-.8.5-1.2 1-1.2 1.8" />
      <path d="M12 17.3h.01" />
    </svg>
    {{ $t('help.open') }}
  </button>
</template>
