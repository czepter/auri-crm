<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { CircleCheckIcon, InfoIcon, Loader2Icon, OctagonXIcon, TriangleAlertIcon, XIcon } from "lucide-vue-next"
import { Toaster as Sonner } from "vue-sonner"
import { cn } from "@/lib/utils"

const props = defineProps<ToasterProps>()
</script>

<template>
  <Sonner
    :class="cn('toaster group', props.class)"
    :style="{
      '--normal-bg': 'var(--bg)',
      '--normal-text': 'var(--ink)',
      '--normal-border': 'var(--line-hover)',
      '--border-radius': 'var(--r)',

      '--success-bg': 'var(--ok-bg)',
      '--success-text': 'var(--ok)',
      '--success-border': 'var(--line-hover)',

      '--warning-bg': 'var(--due-bg)',
      '--warning-text': 'var(--due)',
      '--warning-border': 'var(--line-hover)',

      '--error-bg': 'var(--late-bg)',
      '--error-text': 'var(--late)',
      '--error-border': 'var(--line-hover)',

      '--info-bg': 'var(--accent-bg)',
      '--info-text': 'var(--accent)',
      '--info-border': 'var(--line-hover)',
    }"
    v-bind="props"
  >
    <template #success-icon>
      <CircleCheckIcon class="size-4" />
    </template>
    <template #info-icon>
      <InfoIcon class="size-4" />
    </template>
    <template #warning-icon>
      <TriangleAlertIcon class="size-4" />
    </template>
    <template #error-icon>
      <OctagonXIcon class="size-4" />
    </template>
    <template #loading-icon>
      <div>
        <Loader2Icon class="size-4 animate-spin" />
      </div>
    </template>
    <template #close-icon>
      <XIcon class="size-4" />
    </template>
  </Sonner>
</template>
