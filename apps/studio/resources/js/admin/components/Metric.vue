<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { drawDots, type Tone } from '@/admin/lib/metricDots'

/**
 * C-05 (#239): one metric, everywhere in the CRM.
 *
 * The label stands above the number so that every tile in a row sits on one
 * baseline — the row reads as a row, not as four stacks. Unit and reference
 * ("€", "von 12") ride at 0.52em and grey.
 *
 * No metric without its proof: `4 Shootings im September` is struck from the
 * draft because the number shows nothing anyone could check. Whoever adds a
 * metric brings its dots or leaves it out — which is why `dots` is required
 * and the proof line is a slot the caller has to fill.
 */
const props = defineProps<{
  label: string
  value?: string
  unit?: string
  warn?: boolean
  dots: Tone[]
  /** The empty screen's placeholder: the tile keeps the grid, the figure is absent. */
  ghost?: boolean
  /** The "Als nächstes" form: a date plate instead of a number. */
  day?: { number: string; weekday: string; what: string; who: string }
  href?: string
}>()

const proof = computed(() => drawDots(props.dots))

/* crm.css .dots i.on / .s2 / .s3 / .warn — how far a case has come, as a
   shade rather than a label. */
const DOT: Record<string, string> = {
  on: 'bg-foreground',
  s2: 'bg-[#5F5F63]',
  s3: 'bg-[#A1A1A6]',
  warn: 'bg-action',
}
</script>

<template>
  <component
    :is="href ? Link : 'div'"
    :href="href"
    class="flex min-h-[130px] flex-col rounded-md no-underline text-foreground"
    :class="[
      ghost ? 'border border-dashed border-border bg-transparent' : 'bg-sunk',
      day ? 'overflow-hidden p-0' : 'px-[17px] pt-[15px] pb-3.5',
      href && 'hover:bg-mark',
    ]"
  >
    <template v-if="day">
      <div class="flex items-start gap-[11px] px-[17px] pt-[15px]">
        <div
          class="flex size-[46px] flex-none flex-col items-center justify-center rounded-[12px] bg-[linear-gradient(158deg,#4D3A2C,#C0A184)] leading-none text-white"
        >
          <b class="text-[18px] leading-none font-semibold tracking-[-0.03em]">{{ day.number }}</b>
          <i class="mt-[3px] text-[8px] not-italic tracking-[0.09em] uppercase opacity-85">{{ day.weekday }}</i>
        </div>
        <div>
          <!-- 11px, not the 10.5px of the metric's own label: .metric.date
               .what span outranks .metric .lab on this one. -->
          <span class="text-[11px] tracking-[0.07em] uppercase text-ink-2">{{ label }}</span>
          <b class="mt-0.5 block text-[13.5px] leading-[1.25] font-semibold tracking-[-0.018em]">{{ day.what }}</b>
          <span class="text-[11px] text-ink-2">{{ day.who }}</span>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="text-[10.5px] tracking-[0.07em] uppercase text-ink-2">{{ label }}</div>
      <div
        class="mt-2 flex items-baseline gap-[3px] text-[28px] leading-none tracking-[-0.034em] tabular-nums"
        :class="[ghost ? 'font-normal text-faint' : 'font-semibold', warn && 'text-action']"
      >
        {{ ghost ? '—' : value }}<span v-if="unit" class="text-[0.52em] font-medium text-ink-2">{{ unit }}</span>
      </div>
    </template>

    <!-- The slot's paragraphs were styled by .metric .proof p; without the
         class the rule stops matching, so the wrapper says it itself. -->
    <div
      class="mt-auto [&_p]:mt-[7px] [&_p]:text-[11.5px] [&_p]:text-ink-2 [&_p_b]:font-semibold [&_p_b]:text-foreground"
      :class="day ? 'px-[17px] pt-0 pb-3.5' : 'pt-3'"
    >
      <div class="flex items-center gap-[5px]">
        <i
          v-for="(tone, index) in proof.shown"
          :key="index"
          class="size-2 flex-none rounded-full"
          :class="ghost ? 'bg-sunk-2' : (DOT[tone] ?? 'bg-mark')"
        />
        <span
          v-if="proof.overflow"
          class="ml-[3px] text-[11px] leading-none font-semibold tracking-[-0.01em] tabular-nums text-ink-2"
        >+{{ proof.overflow }}</span>
      </div>
      <slot />
    </div>
  </component>
</template>
