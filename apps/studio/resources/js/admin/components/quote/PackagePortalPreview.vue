<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PortalField from '@/portal/components/PortalField.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { vatAmount } from '@/lib/quoteTotals'
import { formatEuros, formatPercent } from '@/lib/money'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'

// Was ein Bestandskunde unter /leistungen/{id} sieht — siehe
// App\Catalog\Data\PortalServiceData::fromModel() und
// resources/js/portal/pages/Services/Show.vue, dessen Kopf und Fuß hier
// fehlen: die Vorschau steckt schon in einem Dialog der Adminoberfläche, sie
// braucht keine zweite Navigation und keinen zweiten Fußzeilen-Rechtstext.
export type PreviewItem = { title: string; description: string | null; selectable: boolean; selected: boolean; addOnPrice: number | null }

const props = defineProps<{
  name: string
  description: string | null
  price: number | null
  priceMode: string
  requiresDate: boolean
  items: PreviewItem[]
  vatRate?: number | null
}>()

const { t } = useI18n()

// Ein Kunde sieht einen Preis, der schon feststeht — brutto, nicht "zzgl.
// Umsatzsteuer" wie eine Rechnung an ein anderes Unternehmen. Das gilt B2C
// wie B2B: das Portal stellt hier aus, es rechnet nicht ab.
function gross(net: number): number {
  return props.vatRate ? net + vatAmount(net, props.vatRate) : net
}

const price = computed(() => {
  if (props.price === null) return t('portal.priceOnRequest')

  const amount = gross(props.price)

  return props.priceMode === 'from' ? t('portal.priceFrom', { price: formatEuros(amount) }) : formatEuros(amount)
})

const included = computed(() => props.items.filter((item) => !item.selectable || item.selected))
const optional = computed(() => props.items.filter((item) => item.selectable && !item.selected))
</script>

<template>
  <div data-tokens data-theme="portal" class="bg-background text-body leading-[1.6] text-foreground">
    <PortalWrap class="py-10">
      <section class="max-w-[20ch]">
        <h1 class="font-serif text-[clamp(32px,20px+1.6vw,44px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ name || $t('packages.itemTitle') }}
        </h1>
      </section>

      <!-- Ein Grid statt zwei Spalten: die Vorschau steckt in einer schmalen
           Schiene, nicht auf der vollen Portalbreite, und der `min-[900px]`-
           Umbruch der echten Seite fragt die Fensterbreite, nicht die eigene
           — er hätte den Preisblock hier immer zu breit gemacht. -->
      <div class="grid items-start gap-10 pt-10">
        <div>
          <p v-if="description" class="max-w-[54ch] text-body leading-[1.55] text-muted-foreground">{{ description }}</p>

          <section v-if="included.length" class="mt-10">
            <PortalLabel class="mb-5">{{ $t('portal.includedTitle') }}</PortalLabel>
            <div class="border-t border-border">
              <p v-for="(item, index) in included" :key="index" class="border-b border-border py-3.5 text-body">
                {{ [item.title, item.description].filter(Boolean).join(' · ') }}
              </p>
            </div>
          </section>

          <section v-if="optional.length" class="mt-10">
            <PortalLabel class="mb-5">{{ $t('portal.optionalTitle') }}</PortalLabel>
            <div class="border-t border-border">
              <p v-for="(item, index) in optional" :key="index" class="flex gap-4 border-b border-border py-3.5 text-body">
                <span class="flex-1">{{ [item.title, item.description].filter(Boolean).join(' · ') }}</span>
                <span class="flex-none tabular-nums text-muted-foreground">{{ $t('portal.addOnPrice', { price: formatEuros(gross(item.addOnPrice ?? 0)) }) }}</span>
              </p>
            </div>
          </section>
        </div>

        <div>
          <PortalLabel class="mb-2">{{ $t('portal.totalTitle') }}</PortalLabel>
          <span class="block text-heading leading-none font-semibold tracking-[-0.022em] tabular-nums">{{ price }}</span>
          <span v-if="vatRate && props.price !== null" class="mt-[7px] block text-small text-muted-foreground">
            {{ $t('portal.vatNote', { rate: formatPercent(vatRate) }) }}
          </span>

          <div class="mt-6 rounded-md border border-line-hover p-6">
            <div class="grid gap-4">
              <PortalField v-if="requiresDate" :label="$t('portal.preferredDate')">
                <DatePicker model-value="" disabled />
              </PortalField>
              <PortalField v-if="requiresDate" :label="$t('portal.venueName')">
                <Input type="text" disabled />
              </PortalField>
              <PortalField :label="$t('portal.inquiryText')">
                <Textarea rows="4" disabled />
              </PortalField>
            </div>

            <Button class="mt-6 w-full justify-center" type="button" disabled>
              {{ requiresDate ? $t('portal.requestAppointment') : $t('portal.request') }}
            </Button>
          </div>
        </div>
      </div>
    </PortalWrap>
  </div>
</template>
