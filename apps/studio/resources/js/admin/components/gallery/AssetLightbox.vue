<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'

const props = defineProps<{ src: string; filename: string }>()
const emit = defineEmits<{ close: []; prev: []; next: [] }>()

function onKey(event: KeyboardEvent) {
  if (event.key === 'Escape') emit('close')
  if (event.key === 'ArrowLeft') emit('prev')
  if (event.key === 'ArrowRight') emit('next')
}

onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="fixed inset-0 z-50 flex flex-col bg-[rgba(15,15,15,0.92)]" @click.self="emit('close')">
    <div class="flex items-center gap-4 px-6 py-4 text-small text-paper">
      {{ filename }}
      <button class="ml-auto cursor-pointer border-0 bg-transparent text-paper" type="button" @click="emit('close')">
        {{ $t('common.close') }}
      </button>
    </div>
    <img class="min-h-0 flex-1 object-contain px-6 pb-6" :src="`${props.src}?w=2000&fmt=jpeg`" alt="" />
  </div>
</template>
