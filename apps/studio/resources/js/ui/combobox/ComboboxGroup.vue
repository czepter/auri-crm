<script setup lang="ts">
import type { ComboboxGroupProps } from "reka-ui"
import { ComboboxGroup, useForwardProps } from "reka-ui"

const props = defineProps<ComboboxGroupProps>()

const forwarded = useForwardProps(props)
</script>

<template>
  <ComboboxGroup data-slot="combobox-group" v-bind="forwarded">
    <slot />
  </ComboboxGroup>
</template>
