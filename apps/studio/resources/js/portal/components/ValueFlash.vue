<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { flashDirection, type FlashDirection } from '@/portal/lib/valueFlash'

const props = defineProps<{ value: number }>()

const HOLD = 900

const direction = ref<FlashDirection | null>(null)
const changes = ref(0)
let timer: ReturnType<typeof setTimeout> | undefined

watch(
  () => props.value,
  (next, previous) => {
    const changed = flashDirection(previous, next)
    if (!changed) return

    direction.value = changed
    changes.value++
    clearTimeout(timer)
    timer = setTimeout(() => (direction.value = null), HOLD)
  },
)

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <span
    class="relative inline-block transition-colors duration-200"
    :class="direction === 'up' ? 'text-emerald-600' : direction === 'down' ? 'text-red-600' : ''"
  >
    <span
      aria-hidden="true"
      class="absolute -inset-x-1.5 inset-y-0 rounded-[5px] transition-opacity duration-150 motion-reduce:transition-none"
      :class="[direction ? 'opacity-100' : 'opacity-0', direction === 'down' ? 'bg-red-500/12' : 'bg-emerald-500/12']"
    />
    <span :key="changes" class="value-flash relative inline-block" :data-direction="direction">
      <slot />
    </span>
  </span>
</template>

<style scoped>
.value-flash[data-direction] {
  animation: value-flash-in 240ms cubic-bezier(0.22, 0.61, 0.36, 1) both;
}
.value-flash[data-direction='up'] {
  --from: 0.45em;
}
.value-flash[data-direction='down'] {
  --from: -0.45em;
}

@keyframes value-flash-in {
  from {
    opacity: 0;
    transform: translateY(var(--from));
    filter: blur(3px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .value-flash[data-direction] {
    animation-name: value-flash-fade;
  }
  @keyframes value-flash-fade {
    from {
      opacity: 0;
    }
  }
}
</style>
