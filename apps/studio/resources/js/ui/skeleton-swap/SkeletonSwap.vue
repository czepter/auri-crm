<script setup lang="ts">
import { toRef } from "vue"
import { useSkeletonSwap } from "./useSkeletonSwap"

const props = defineProps<{ loading: boolean }>()

const visible = useSkeletonSwap(toRef(props, "loading"))
const widths = [100, 93, 97, 88, 95, 91, 97, 90]
</script>

<template>
  <!-- Laid over the content it stands in for, so nothing is added to the
       flow and nothing below can move when it goes. -->
  <div
    aria-hidden="true"
    class="pointer-events-none absolute inset-y-0 right-0 left-12 z-10 max-[640px]:left-0 overflow-hidden bg-background px-6 pt-5 transition-opacity duration-200 ease-[cubic-bezier(0.23,1,0.32,1)] motion-reduce:transition-none"
    :class="visible ? 'opacity-100' : 'opacity-0'"
  >
    <div class="h-6 w-48 rounded-md bg-sunk" />
    <div class="mt-6 flex flex-col">
      <div v-for="(width, row) in widths" :key="row" class="flex h-11 items-center border-b border-rule">
        <div class="h-2.5 rounded-sm bg-sunk" :style="{ width: `${width}%` }" />
      </div>
    </div>
  </div>
</template>
