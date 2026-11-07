<script setup lang="ts">
import { Check, CircleAlert, Loader } from 'lucide-vue-next'
import type { SaveState } from '@/admin/lib/autosave'

defineProps<{ state: SaveState }>()

const LABEL = { saved: 'common.saved', saving: 'common.saving', error: 'common.savingError', dirty: 'common.saveDirty' } as const
</script>

<template>
  <span
    class="flex flex-none items-center gap-1.5 text-small leading-none"
    :class="state === 'error' ? 'text-destructive' : state === 'dirty' ? 'text-due' : 'text-ink-2'"
    role="status"
    data-save-state
    :data-state="state"
  >
    <Loader v-if="state === 'saving'" class="size-3.5 animate-spin" />
    <CircleAlert v-else-if="state === 'error' || state === 'dirty'" class="size-3.5" />
    <Check v-else class="size-3.5" />
    {{ $t(LABEL[state]) }}
  </span>
</template>
