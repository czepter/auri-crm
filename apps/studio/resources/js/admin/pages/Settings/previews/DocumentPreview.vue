<script setup lang="ts">
import { computed } from 'vue'
import PreviewFrame from './PreviewFrame.vue'

/**
 * The one sheet Rechtliches, Bankverbindung, Zahlungsdaten & USt and
 * Dokumente all land on — four panes whose fields were, until now, only
 * visible in a rendered PDF. Index.vue merges the live form on top of the
 * saved settings, so the pane being edited moves this in place.
 */
const props = defineProps<{
  doc: {
    displayName: string
    logoUrl: string | null
    fontPair: string
    accentColor: string | null
    legalName: string
    legalForm: string
    street: string
    postcode: string
    city: string
    representative: string
    taxNumber: string
    vatId: string
    registerNumber: string
    registerCourt: string
    bankAccountHolder: string
    bankIban: string
    bankBic: string
    vatMode: string
    vatRate: string
    quoteValidityDays: number
    paymentTermDays: number
    depositPercentage: string
    widerrufIncluded: boolean
  }
}>()

const senderLine = computed(() =>
  [props.doc.legalName, props.doc.street, [props.doc.postcode, props.doc.city].filter(Boolean).join(' ')]
    .filter(Boolean)
    .join(' · '))

const registerLine = computed(() =>
  [props.doc.registerNumber, props.doc.registerCourt].filter(Boolean).join(', '))

const taxLine = computed(() => props.doc.vatId || props.doc.taxNumber)

const iban = computed(() => props.doc.bankIban.replace(/(.{4})/g, '$1 ').trim())
</script>

<template>
  <PreviewFrame :label="$t('settings.previewDocument')">
    <div
      class="rounded-md bg-background px-5 py-6 text-[8.5px] leading-[1.5] text-foreground"
      :data-font="doc.fontPair"
      :style="doc.accentColor ? { '--accent': doc.accentColor } : undefined"
    >
      <div class="flex items-start justify-between gap-3">
        <span
          class="min-w-0 truncate font-[family-name:var(--font-display)] text-[15px] leading-[var(--display-leading)] tracking-[var(--display-tracking)]"
        >{{ doc.displayName || doc.legalName }}</span>
        <img v-if="doc.logoUrl" :src="doc.logoUrl" alt="" class="size-6 flex-none object-contain" />
      </div>

      <div class="mt-6 truncate border-b border-border pb-0.5 text-[6.5px] text-faint">{{ senderLine }}</div>
      <div class="mt-2 space-y-1">
        <div class="h-1.5 w-24 rounded-full bg-muted" />
        <div class="h-1.5 w-20 rounded-full bg-muted" />
        <div class="h-1.5 w-16 rounded-full bg-muted" />
      </div>

      <div class="mt-6 flex items-baseline justify-between">
        <span class="text-[11px] font-semibold">{{ $t('portal.quoteTitle') }}</span>
        <span class="h-1.5 w-16 rounded-full bg-muted" />
      </div>

      <div class="mt-3 space-y-2 border-t border-border pt-2">
        <div v-for="row in 3" :key="row" class="flex items-center gap-3">
          <span class="h-1.5 flex-1 rounded-full bg-muted" :style="{ maxWidth: `${90 - row * 12}%` }" />
          <span class="h-1.5 w-8 flex-none rounded-full bg-muted" />
        </div>
      </div>

      <div class="mt-3 space-y-1 border-t border-border pt-2">
        <div class="flex items-center justify-between gap-3 text-faint">
          <span v-if="doc.vatMode === 'standard'">{{ $t('settings.vatRateLabel') }} {{ doc.vatRate }} %</span>
          <span v-else>{{ $t('settings.previewSmallBusiness') }}</span>
          <span class="h-1.5 w-10 flex-none rounded-full bg-muted" />
        </div>
        <div class="flex items-center justify-between gap-3 font-semibold">
          <span>{{ $t('settings.previewTotal') }}</span>
          <span class="h-2 w-12 flex-none rounded-full bg-muted" />
        </div>
      </div>

      <div class="mt-4 text-[7.5px] text-faint">
        {{ $t('settings.quoteValidityLabel') }} {{ doc.quoteValidityDays }} · {{ $t('settings.paymentTermLabel') }}
        {{ doc.paymentTermDays }} · {{ $t('settings.depositPercentageLabel') }} {{ doc.depositPercentage }} %
      </div>
      <div v-if="doc.widerrufIncluded" class="mt-1 text-[7.5px] text-faint">{{ $t('settings.widerrufLabel') }}</div>

      <div class="mt-6 grid grid-cols-3 gap-2 border-t border-border pt-2 text-[6.5px] leading-[1.55] text-faint">
        <div class="min-w-0 truncate">
          <div>{{ [doc.legalName, doc.legalForm].filter(Boolean).join(' ') }}</div>
          <div>{{ doc.street }}</div>
          <div>{{ [doc.postcode, doc.city].filter(Boolean).join(' ') }}</div>
        </div>
        <div class="min-w-0 truncate">
          <div v-if="doc.representative">{{ doc.representative }}</div>
          <div v-if="registerLine">{{ registerLine }}</div>
          <div v-if="taxLine">{{ taxLine }}</div>
        </div>
        <div class="min-w-0 truncate">
          <div v-if="doc.bankAccountHolder">{{ doc.bankAccountHolder }}</div>
          <div v-if="iban">{{ iban }}</div>
          <div v-if="doc.bankBic">{{ doc.bankBic }}</div>
        </div>
      </div>
    </div>
  </PreviewFrame>
</template>
