<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { hexToHsv, hsvToHex, isHex, type Hsv } from "@/lib/color"
import { InputGroup, InputGroupInput } from "@/ui/input-group"
import { Popover, PopoverContent, PopoverTrigger } from "@/ui/popover"

/*
 * Eine Farbe als Hex-Feld mit Auswahlfläche dahinter: Helligkeit und Sättigung
 * in einem Quadrat, der Farbton darunter. Das Feld bleibt die Wahrheit, die
 * Fläche schreibt nur hinein.
 */
const props = defineProps<{
  id?: string
  modelValue?: string
  placeholder?: string
  rule?: "color"
  ariaLabel?: string
}>()

const emits = defineEmits<{ (e: "update:modelValue", value: string): void }>()

const value = computed({
  get: () => props.modelValue ?? "",
  set: (next) => emits("update:modelValue", String(next)),
})

const hsv = ref<Hsv>(isHex(value.value) ? hexToHsv(value.value) : { h: 180, s: 0.6, v: 0.5 })

// Ein Grau hat keinen Farbton; der gemerkte bleibt, sonst springt der Regler.
watch(value, (hex) => {
  if (!isHex(hex) || hex.toUpperCase() === hsvToHex(hsv.value)) return

  const next = hexToHsv(hex)

  hsv.value = { h: next.s === 0 || next.v === 0 ? hsv.value.h : next.h, s: next.s, v: next.v }
})

function set(next: Partial<Hsv>) {
  hsv.value = { ...hsv.value, ...next }
  value.value = hsvToHex(hsv.value)
}

const clamp = (n: number) => Math.min(1, Math.max(0, n))

function drag(event: PointerEvent, apply: (x: number, y: number) => void) {
  const el = event.currentTarget as HTMLElement
  const rect = el.getBoundingClientRect()
  const move = (e: PointerEvent) => apply(clamp((e.clientX - rect.left) / rect.width), clamp((e.clientY - rect.top) / rect.height))

  el.setPointerCapture(event.pointerId)
  move(event)
  el.onpointermove = move
  el.onpointerup = () => {
    el.onpointermove = null
    el.onpointerup = null
  }
}

const onArea = (event: PointerEvent) => drag(event, (x, y) => set({ s: x, v: 1 - y }))
const onHue = (event: PointerEvent) => drag(event, (x) => set({ h: x * 360 }))

function keyArea(event: KeyboardEvent) {
  const step = event.shiftKey ? 0.1 : 0.02
  const moves: Record<string, Partial<Hsv>> = {
    ArrowLeft: { s: clamp(hsv.value.s - step) },
    ArrowRight: { s: clamp(hsv.value.s + step) },
    ArrowUp: { v: clamp(hsv.value.v + step) },
    ArrowDown: { v: clamp(hsv.value.v - step) },
  }

  if (moves[event.key]) {
    event.preventDefault()
    set(moves[event.key])
  }
}

function keyHue(event: KeyboardEvent) {
  const step = event.shiftKey ? 20 : 4
  const delta = event.key === "ArrowRight" || event.key === "ArrowUp" ? step : event.key === "ArrowLeft" || event.key === "ArrowDown" ? -step : 0

  if (delta) {
    event.preventDefault()
    set({ h: (hsv.value.h + delta + 360) % 360 })
  }
}

const swatch = computed(() => (isHex(value.value) ? value.value : "transparent"))
const hueColor = computed(() => hsvToHex({ h: hsv.value.h, s: 1, v: 1 }))
</script>

<template>
  <InputGroup>
    <Popover>
      <PopoverTrigger as-child>
        <button
          type="button"
          :aria-label="ariaLabel"
          class="flex items-center pl-[11px] outline-none"
        >
          <span class="size-4 flex-none rounded-[3px] border border-border" :style="{ background: swatch }" />
        </button>
      </PopoverTrigger>
      <PopoverContent align="start" class="w-[224px]">
        <div
          role="slider"
          tabindex="0"
          :aria-label="ariaLabel"
          :aria-valuetext="value"
          class="relative h-[136px] w-full cursor-crosshair touch-none rounded-[4px] outline-none focus-visible:ring-2 focus-visible:ring-ring"
          :style="{
            background: `linear-gradient(to top, #000, transparent), linear-gradient(to right, #fff, ${hueColor})`,
          }"
          @pointerdown="onArea"
          @keydown="keyArea"
        >
          <span
            class="pointer-events-none absolute size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgb(0_0_0_/_0.35)]"
            :style="{ left: `${hsv.s * 100}%`, top: `${(1 - hsv.v) * 100}%`, background: swatch }"
          />
        </div>
        <div
          role="slider"
          tabindex="0"
          aria-valuemin="0"
          aria-valuemax="360"
          :aria-valuenow="Math.round(hsv.h)"
          class="relative mt-3 h-3 w-full cursor-pointer touch-none rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring"
          style="background: linear-gradient(to right, #f00, #ff0, #0f0, #0ff, #00f, #f0f, #f00)"
          @pointerdown="onHue"
          @keydown="keyHue"
        >
          <span
            class="pointer-events-none absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgb(0_0_0_/_0.35)]"
            :style="{ left: `${(hsv.h / 360) * 100}%`, background: hueColor }"
          />
        </div>
      </PopoverContent>
    </Popover>
    <InputGroupInput :id v-model="value" class="tabular-nums" :rule type="text" :placeholder />
  </InputGroup>
</template>
