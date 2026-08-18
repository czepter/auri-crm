<script setup lang="ts">
import { computed } from 'vue'
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
  /** The "Als nächstes" form: a date plate instead of a number. */
  day?: { number: string; weekday: string; what: string; who: string }
}>()

const proof = computed(() => drawDots(props.dots))
</script>

<template>
  <div class="metric" :class="{ date: day }">
    <template v-if="day">
      <div class="head">
        <div class="day">
          <b>{{ day.number }}</b><i>{{ day.weekday }}</i>
        </div>
        <div class="what">
          <span class="lab">{{ label }}</span>
          <b>{{ day.what }}</b>
          <span>{{ day.who }}</span>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="lab">{{ label }}</div>
      <div class="num" :class="{ warn }">
        {{ value }}<span v-if="unit" class="unit">{{ unit }}</span>
      </div>
    </template>

    <div class="proof">
      <div class="dots">
        <i v-for="(tone, index) in proof.shown" :key="index" :class="tone" />
        <span v-if="proof.overflow" class="more">+{{ proof.overflow }}</span>
      </div>
      <slot />
    </div>
  </div>
</template>
