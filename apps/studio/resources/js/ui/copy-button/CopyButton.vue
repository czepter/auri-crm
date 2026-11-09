<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import type { ButtonVariants } from "@/ui/button"
import { computed, onBeforeUnmount, ref } from "vue"
import { useI18n } from "vue-i18n"
import { Button } from "@/ui/button"
import { copyText } from "./copyText"

const props = withDefaults(defineProps<{
  value: string
  label?: string
  variant?: ButtonVariants["variant"]
  class?: HTMLAttributes["class"]
}>(), { variant: "pill-muted" })

const { t } = useI18n()
const labels = computed(() => [
  { state: "idle", text: props.label ?? t("gallery.copy") },
  { state: "copied", text: t("gallery.copied") },
  { state: "failed", text: t("gallery.copyFailed") },
])

const state = ref<"idle" | "copied" | "failed">("idle")
let timer: ReturnType<typeof setTimeout> | undefined

async function copy() {
  const ok = await copyText(props.value)
  state.value = ok ? "copied" : "failed"
  clearTimeout(timer)
  timer = setTimeout(() => (state.value = "idle"), 2000)
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <Button type="button" :variant="variant" :class="props.class" @click="copy">
    <span class="inline-grid justify-items-center">
      <span
        v-for="label in labels"
        :key="label.state"
        class="col-start-1 row-start-1 transition-opacity duration-150"
        :class="state === label.state ? 'opacity-100' : 'opacity-0'"
        :aria-hidden="state !== label.state"
      >{{ label.text }}</span>
    </span>
  </Button>
</template>
