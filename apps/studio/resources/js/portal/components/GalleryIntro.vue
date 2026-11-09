<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { Button } from '@/ui/button'
import type { IntroRow } from '@/portal/lib/galleryIntro'

defineProps<{ rows: IntroRow[] }>()
const emit = defineEmits<{ done: [] }>()

const ICONS: Record<IntroRow, string> = {
  favorite: 'M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z',
  comment: 'M5 6h14v10H9l-4 3z',
  hide: 'M4 4l16 16M10 6.2A9 9 0 0121 12a9 9 0 01-3 3.5M6.5 7.5A9 9 0 003 12c1.5 3.5 5 6 9 6 1.1 0 2.1-.2 3-.5',
}

const button = ref<HTMLElement | null>(null)

function onKey(event: KeyboardEvent) {
  if (event.key === 'Escape') emit('done')
}

onMounted(() => {
  window.addEventListener('keydown', onKey)
  button.value?.focus()
})
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <!-- Unter 761px ein Sheet am unteren Rand, darüber ein Dialog. -->
  <div
    data-tokens
    role="dialog"
    aria-modal="true"
    :aria-label="$t('gallery.introTitle')"
    class="fixed inset-0 z-60 flex items-end justify-center bg-[rgba(12,10,9,.7)] min-[761px]:items-center"
    @click.self="emit('done')"
  >
    <div class="w-full rounded-t-xl bg-background px-6 pt-6 pb-[calc(24px+env(safe-area-inset-bottom))] min-[761px]:max-w-[400px] min-[761px]:rounded-xl min-[761px]:pb-6">
      <h2 class="m-0 mb-5 text-[18px] leading-[1.3] font-semibold text-foreground">{{ $t('gallery.introTitle') }}</h2>

      <ul class="m-0 mb-6 flex list-none flex-col gap-4 p-0">
        <li v-for="row in rows" :key="row" class="flex items-center gap-3.5 text-[15px] leading-[1.3] text-foreground">
          <svg viewBox="0 0 24 24" class="size-5 flex-none fill-none stroke-current stroke-[1.5] [stroke-linecap:round] [stroke-linejoin:round]"><path :d="ICONS[row]" /></svg>
          {{ $t(`gallery.intro${row === 'favorite' ? 'Favorite' : row === 'comment' ? 'Comment' : 'Hide'}`) }}
        </li>
      </ul>

      <Button ref="button" class="w-full justify-center" type="button" @click="emit('done')">{{ $t('gallery.introDone') }}</Button>
    </div>
  </div>
</template>
