<script setup lang="ts">
import { computed } from 'vue'
import { useObjectUrl } from '@vueuse/core'
import PreviewFrame from './PreviewFrame.vue'

const props = defineProps<{
  displayName: string
  website: string
  accentColor: string
  fontPair: string
  logoFile: File | null
  logoUrl: string | null
}>()

const picked = useObjectUrl(computed(() => props.logoFile ?? undefined))

const logo = computed(() => picked.value ?? props.logoUrl)

// Half-typed input is the normal state of a colour field: paint with it only
// once it is a colour, otherwise the sample flickers through invalid values.
const accent = computed(() => (/^#[0-9A-Fa-f]{6}$/.test(props.accentColor) ? props.accentColor : null))

const host = computed(() => props.website.replace(/^https?:\/\//, '').replace(/\/+$/, ''))
</script>

<template>
  <PreviewFrame :label="$t('settings.previewBrand')">
    <div
      class="rounded-md bg-background p-5"
      :data-font="fontPair"
      :style="accent ? { '--accent': accent } : undefined"
    >
      <div class="flex items-center gap-2.5 border-b border-border pb-3.5">
        <img v-if="logo" :src="logo" alt="" class="size-7 flex-none object-contain" />
        <span
          class="min-w-0 truncate font-[family-name:var(--font-display)] text-[19px] leading-[var(--display-leading)] tracking-[var(--display-tracking)] text-foreground"
        >{{ displayName }}</span>
      </div>

      <div class="pt-4 font-[family-name:var(--font)]">
        <div class="text-[13px] font-medium text-foreground">{{ $t('portal.quoteTitle') }}</div>
        <div class="mt-2.5 h-2 w-full rounded-full bg-muted" />
        <div class="mt-1.5 h-2 w-4/5 rounded-full bg-muted" />
        <button
          type="button"
          tabindex="-1"
          class="mt-4 rounded-md bg-action px-3 py-1.5 text-[12.5px] leading-none text-action-foreground"
        >{{ $t('portal.quoteAccept') }}</button>
      </div>

      <div v-if="host" class="mt-4 border-t border-border pt-3 text-[11.5px] text-faint">{{ host }}</div>
    </div>
  </PreviewFrame>
</template>
