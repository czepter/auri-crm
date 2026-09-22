<script setup lang="ts">
import { computed } from 'vue'
import { refDebounced } from '@vueuse/core'
import PreviewFrame from './PreviewFrame.vue'

// Die Portal-Startseite selbst, wie auf der Seite Portal: was im Formular
// steht, reist als Query mit und der Rahmen lädt entprellt neu.
const props = defineProps<{ query: Record<string, string> }>()

const source = computed(() => `/einstellungen/portal/vorschau?${new URLSearchParams(props.query)}`)
const src = refDebounced(source, 400)
</script>

<template>
  <PreviewFrame :label="$t('settings.previewPortal')" wide>
    <iframe :src class="block h-[760px] w-full rounded-md border-0 bg-background" title="" />
  </PreviewFrame>
</template>
