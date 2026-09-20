<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PreviewFrame from './PreviewFrame.vue'
import { ChoiceGroup } from '@/ui/choice-group'

const props = defineProps<{
  /** Welcher der drei Begrüßungszustände gezeigt wird. */
  state: 'inquiry' | 'active' | 'done'
  branding: { displayName: string | null; logoUrl: string | null; fontPair: string | null; accentColor: string | null }
  portal: {
    maintenanceEnabled: boolean
    maintenanceMessage: string
    welcomeText: string
    acceptQuotes: boolean
    signContracts: boolean
    paymentStatus: boolean
    messagingMode: string
    contactPhone: string
    contactEmail: string
    contactResponseTime: string
    datenschutzUrl: string
    agbUrl: string
  }
}>()

const emit = defineEmits<{ 'update:state': [value: string] }>()

const { t } = useI18n()

const stateOptions = ['inquiry', 'active', 'done'].map((state) => ({
  value: state,
  label: t(`portalSettings.welcomeState.${state}`),
}))

const rows = computed(() => [
  { on: props.portal.acceptQuotes, label: 'portalSettings.acceptQuotesEnabledLabel' },
  { on: props.portal.signContracts, label: 'portalSettings.signContractsEnabledLabel' },
  { on: props.portal.paymentStatus, label: 'portalSettings.paymentStatusEnabledLabel' },
  { on: props.portal.messagingMode !== 'off', label: 'portalSettings.messagingLabel' },
].filter((row) => row.on))

const contact = computed(() =>
  [props.portal.contactPhone, props.portal.contactEmail, props.portal.contactResponseTime].filter(Boolean).join(' · '))
</script>

<template>
  <PreviewFrame :label="$t('settings.previewPortal')">
    <ChoiceGroup
      class="mb-4"
      variant="segmented"
      :model-value="state"
      :options="stateOptions"
      @update:model-value="(value) => emit('update:state', String(value))"
    />
    <div
      class="rounded-md bg-background text-[11px] leading-[1.5] text-foreground"
      :data-font="branding.fontPair ?? undefined"
      :style="branding.accentColor ? { '--accent': branding.accentColor } : undefined"
    >
      <div class="flex items-center gap-2 border-b border-border px-4 py-3">
        <img v-if="branding.logoUrl" :src="branding.logoUrl" alt="" class="size-5 flex-none object-contain" />
        <span
          class="min-w-0 truncate font-[family-name:var(--font-display)] text-[15px] leading-[var(--display-leading)] tracking-[var(--display-tracking)]"
        >{{ branding.displayName }}</span>
      </div>

      <!-- What a closed portal actually shows: the notice, and nothing under
           it. Leaving the rooms visible behind a switch that hides them would
           make the preview lie. -->
      <div v-if="portal.maintenanceEnabled" class="px-4 py-5 text-muted-foreground">
        {{ portal.maintenanceMessage }}
      </div>

      <template v-else>
        <div class="px-4 pt-4">
          <p v-if="portal.welcomeText" class="text-muted-foreground">{{ portal.welcomeText }}</p>
          <div v-else class="space-y-1.5">
            <div class="h-1.5 w-full rounded-full bg-muted" />
            <div class="h-1.5 w-3/5 rounded-full bg-muted" />
          </div>
        </div>

        <div class="space-y-1.5 px-4 py-4">
          <div
            v-for="row in rows"
            :key="row.label"
            class="flex items-center justify-between gap-2 rounded-md bg-muted px-2.5 py-2"
          >
            <span class="min-w-0 truncate">{{ $t(row.label) }}</span>
            <span class="size-1.5 flex-none rounded-full bg-action" />
          </div>
        </div>
      </template>

      <div class="flex items-baseline justify-between gap-2 border-t border-border px-4 py-2.5 text-[10px] text-faint">
        <span class="min-w-0 truncate">{{ contact }}</span>
        <span class="flex-none">
          <template v-if="portal.datenschutzUrl">{{ $t('portalSettings.datenschutzUrlLabel') }}</template>
          <template v-if="portal.datenschutzUrl && portal.agbUrl"> · </template>
          <template v-if="portal.agbUrl">{{ $t('portalSettings.agbUrlLabel') }}</template>
        </span>
      </div>
    </div>
  </PreviewFrame>
</template>
