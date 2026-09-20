<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Input } from '@/ui/input'

// Q-10: der Bildersatz des Reglerrasters — am Paket der Startsatz, am
// Angebot der, den der Kunde sieht. Beide Wege sind dieselben drei Routen
// unter ihrer eigenen Basis, also trägt der Aufrufer nur die Basis bei.
const props = defineProps<{ base: string; count: number }>()

function add(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]

  if (!file) return

  router.post(props.base, { image: file }, { preserveScroll: true, forceFormData: true })
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <div v-for="index in count" :key="index" class="relative">
      <img class="size-14 rounded-[6px] object-cover" :src="`${base}/${index - 1}`" alt="" />
      <button
        class="absolute -top-1.5 -right-1.5 flex size-5 cursor-pointer items-center justify-center rounded-full bg-sunk-2 text-[11px] leading-none"
        type="button"
        :aria-label="$t('common.remove')"
        @click="router.delete(`${base}/${index - 1}`, { preserveScroll: true })"
      >×</button>
    </div>
    <Input class="max-w-[240px]" type="file" accept="image/png,image/jpeg" @change="add" />
  </div>
</template>
